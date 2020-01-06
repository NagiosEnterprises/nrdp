<?php
/*****************************************************************************
 *
 *
 *  NRDP Nagios Core Command Plugin
 *
 *
 *  Copyright (c) 2008-2020 - Nagios Enterprises, LLC. All rights reserved.
 *
 *  License: GNU General Public License version 3
 *
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *****************************************************************************/

require_once(dirname(__FILE__) . "/../../config.inc.php");
require_once(dirname(__FILE__) . "/../../includes/utils.inc.php");


register_callback(CALLBACK_PROCESS_REQUEST,"nagioscorecmd_process_request");



// process a command for this plugin
function nagioscorecmd_process_request($cbtype, $args)
{
    $cmd = grab_array_var($args, "cmd");
    _debug("nagioscorecmd_process_request(cbtype = {$cbtype}, args[cmd] = {$cmd}");


    $submit_cmd = false;


    if ($cmd == "submitrawcmd") {
        $submit_cmd = true;
        $raw = true;
    }
    if ($cmd == "submitcmd") {
        $submit_cmd = true;
        $raw = false;
    }


    if ($submit_cmd) {
        nagioscorecmd_submit_nagios_command($raw);
    }


    _debug("nagioscorecmd_process_request() had no registered callbacks, returning");
}



// attempt to submit a nagios command
function nagioscorecmd_submit_nagios_command($raw = false)
{
    $raw_str = "FALSE";
    if ($raw == true) {
        $raw_str = "TRUE";
    }

    _debug("nagioscorecmd_submit_nagios_command(raw = {$raw_str})");

    // If commands are disallowed in the config...
    if (grab_cfg_var("disable_external_commands") === true) {
        _debug("\$cfg[disable_external_commands] == true, bailing");
        handle_api_error(ERROR_DISABLED_COMMAND);
        return;
    }

    $command = grab_request_var("command");
    $token = grab_request_var("token");

    // Make sure we have a command
    if (empty($command)) {
        _debug("we have no command, bailing");
        handle_api_error(ERROR_NO_COMMAND);
    }

    if (!nagioscorecmd_is_token_authorized($token, $command)) {
        _debug("token has been denied, bailing");
        handle_api_error(ERROR_DENIED_TOKEN);
    }

    // Make sure we can write to external command file
    $command_file = grab_cfg_var("command_file");

    if (empty($command_file)) {
        _debug("we have no \$cfg[command_file], bailing");
        handle_api_error(ERROR_NO_COMMAND_FILE);
    }

    if (!file_exists($command_file)) {
        _debug("\$cfg[command_file] ({$command_file}) doesn't exist, bailing");
        handle_api_error(ERROR_BAD_COMMAND_FILE);
    }

    if (!is_writeable($command_file)) {
        _debug("\$cfg[command_file] ({$command_file}) isn't writable, bailing");
        handle_api_error(ERROR_COMMAND_FILE_OPEN_WRITE);
    }

    _debug("so far so good.. moving ahead with writing the command!");

    // convert to an array if not one
    // to not duplicate the file writing
    $command = required_array($command);

    $successful = 0;
    $failed = false;

    foreach ($command as $cmd) {

        if (empty($cmd)) {
            continue;
        }

        $time = time();

        $written = file_put_contents($command_file, "[{$time}] $cmd \n");

        if (empty($written)) {

            if ($written === false) {
                _debug("\$cfg[command_file] ({$command_file}) unable to write!");
                handle_api_error(ERROR_COMMAND_FILE_OPEN);
            }

            $failed = true;
            break;
        }

        $successful++;
    }

    if ($failed == true) {
        _debug("\$cfg[command_file] ({$command_file}) failed after {$successful} attempts");
        handle_api_error(ERROR_BAD_WRITE);
    }

    _debug("{$successful} commands written");

    $response = array(
        "result" => array(
            "status"    => 0,
            "message"   => "OK",
            ),
        );

    output_response($response);

    exit();
}



// check if a specified token is authorized for a given command
function nagioscorecmd_is_token_authorized($token, $command)
{
    $command = strtoupper($command);

    // get rid of the host/service data
    $command = strtok($command, ";");

    _debug("nagioscorecmd_is_token_authorized(token={$token}, command={$command})");


    $deny_tokens = grab_cfg_var("external_commands_deny_tokens");

    if (empty($deny_tokens) || !is_array($deny_tokens)) {
        _debug("no deny_tokens specified, everything is okay! returning..");
        return true;
    }


    // make it easier to check against strings
    $deny_tokens = array_change_key_case($deny_tokens, CASE_UPPER);


    // was anything specified to deny for all?
    $all = grab_array_var($deny_tokens, "ALL");

    if (!empty($all)) {

        _debug("got ALL deny tokens.. checking here first");

        // make it an array so we can loop through it
        $all = required_array($all);

        foreach ($all as $deny_token) {

            _debug("checking against deny token={$token}");

            if ($token == $deny_token) {
                _debug("deny match found!");
                return false;
            }
        }

        // we dont wanna go through it again
        unset($deny_tokens["ALL"]);
    }


    // now loop through the commands=>tokens
    // and convert the command to a regexp suitable for wildcard
    // then check each of the tokens if there is a command match
    foreach ($deny_tokens as $deny_command => $deny_token_list) {

        if (empty($deny_token_list)) {
            continue;
        }

        _debug("checking deny_command={$deny_command}");

        $deny_command = str_replace("*", ".*", $deny_command);
        $deny_command = "/^{$deny_command}$/";

        _debug(" * transformed to [{$deny_command}]");

        if (preg_match($deny_command, $command) === 1) {

            _debug(" * DENY COMMAND MATCHES COMMAND SUBMITTED");

            $deny_token_list = required_array($deny_token_list);

            _debug(" * Checking against deny tokens: " . print_r($deny_token_list, true));

            foreach ($deny_token_list as $deny_token) {

                if ($token == $deny_token) {
                    _debug(" * Token found in list, DENIED!");
                    return false;
                }
            }
        }
    }

    return true;
}
