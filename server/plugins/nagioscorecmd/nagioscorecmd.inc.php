<?php
//
// Nagios Core Command NRDP Plugin
//
// Copyright (c) 2010-2017 - Nagios Enterprises, LLC. All rights reserved.
// License: Nagios Open Software License <http://www.nagios.com/legal/licenses>
//

require_once(dirname(__FILE__).'/../../config.inc.php');
require_once(dirname(__FILE__).'/../../includes/utils.inc.php');


register_callback(CALLBACK_PROCESS_REQUEST,'nagioscorecmd_process_request');


function nagioscorecmd_process_request($cbtype, $args)
{
    $cmd = grab_array_var($args, "cmd");
    _debug("nagioscorecmd_process_request(cbtype = {$cbtype}, args[cmd] = {$cmd}");

    switch ($cmd)
    {
        // Raw nagios external commands
        case "submitrawcmd":
            nagioscorecmd_submit_nagios_command(true);
            break;

        // Normal nagios external commands
        case "submitcmd":
            nagioscorecmd_submit_nagios_command(false);
            break;

        // Something else we don't handle...
        default:
            break;
    }

    _debug("nagioscorecmd_process_request() had no registered callbacks, returning");
}


function nagioscorecmd_submit_nagios_command($raw=false)
{
    global $cfg;
    _debug("nagioscorecmd_submit_nagios_command(raw=" . ($raw ? 'TRUE' : 'FALSE') . ")");

    // If commands are disallowed in the config...
    if ($cfg["disable_external_commands"] === true) {
        _debug('cfg[disable_external_commands] == true, bailing');
        handle_api_error(ERROR_DISABLED_COMMAND);
        return;
    }

    $command = grab_request_var("command");
    $token = grab_request_var("token");

    // Make sure we have a command
    if (!have_value($command)) {
        _debug('we have no command, bailing');
        handle_api_error(ERROR_NO_COMMAND);
    }

    if (!nagioscorecmd_is_token_authorized($token, $command)) {
        _debug("token has been denied, bailing");
        handle_api_error(ERROR_DENIED_TOKEN);
    }

    // Make sure we can write to external command file
    if (!isset($cfg["command_file"])) {
        _debug('we have no cfg[command_file], bailing');
        handle_api_error(ERROR_NO_COMMAND_FILE);
    }
    if (!file_exists($cfg["command_file"])) {
        _debug("cfg[command_file] ({$cfg['command_file']}) doesn't exist, bailing");
        handle_api_error(ERROR_BAD_COMMAND_FILE);
    }
    if (!is_writeable($cfg["command_file"])) {
        _debug("cfg[command_file] ({$cfg['command_file']}) isn't writable, bailing");
        handle_api_error(ERROR_COMMAND_FILE_OPEN_WRITE);
    }
        
    // Open external command file
    if (($handle = @fopen($cfg["command_file"],"w+")) === false) {
        _debug("couldn't open cfg[command_file] ({$cfg['command_file']}), bailing");
        handle_api_error(ERROR_COMMAND_FILE_OPEN);
    }

    _debug("so far so good.. moving ahead with writing the command!");

    // Get current time
    $ts = time();

    // Write the external command(s)
    $error = false;
    if (!is_array($command)) {
        if ($raw == false) {
            fwrite($handle, "[".$ts."] ");
        }
        $result = fwrite($handle, $command."\n");
    } else {
        foreach ($command as $cmd) {
            if ($raw == false) {
                fwrite($handle, "[".$ts."] ");
            }
            $result = fwrite($handle, $cmd."\n");
            if ($result === false) {
                break;
            }
        }
    }

    // Close the file
    fclose($handle);

    if ($result === false) {
        _debug("fwrite() result was false, bailing");
        handle_api_error(ERROR_BAD_WRITE);
    }

    _debug("fwrite() was successful!");
    output_api_header();

    echo "<result>\n";
    echo "  <status>0</status>\n";
    echo "  <message>OK</message>\n";
    echo "</result>\n";

    exit();
}

function nagioscorecmd_is_token_authorized($token, $command) {

    global $cfg;

    $command = strtoupper($command);

    // get rid of the host/service data
    $command = strtok($command, ';');

    _debug("nagioscorecmd_is_token_authorized(token={$token}, command={$command})");

    $deny_tokens = grab_array_var($cfg, "external_commands_deny_tokens", "");
    if (empty($deny_tokens) || !is_array($deny_tokens)) {
        _debug("no deny_tokens specified, everything is okay! returning..");
        return true;
    }

    // make it easier to check against strings
    $deny_tokens = array_change_key_case($deny_tokens, CASE_UPPER);

    // was anything specified to deny for all?
    $all = grab_array_var($deny_tokens, "ALL", "");
    if (!empty($all)) {

        _debug("got ALL deny tokens.. checking here first");

        // make it an array so we can loop through it
        if (!is_array($all)) {
            $all = array($all);
        }

        foreach ($all as $deny_token) {
            _debug("checking against deny token={$token}");
            if ($token == $deny_token) {
                _debug("deny match found!");
                return false;
            }
        }

        // we dont wanna go through it again
        unset($deny_tokens['ALL']);
    }


    // now loop through the commands=>tokens
    // and convert the command to a regexp suitable for wildcard
    // then check each of the tokens if there is a command match
    foreach ($deny_tokens as $deny_command => $deny_token_list) {

        if (empty($deny_token_list))
            continue;

        _debug("checking deny_command={$deny_command}");
        $deny_command = str_replace('*', '.*', $deny_command);
        $deny_command = '/^' . $deny_command . '$/';
        _debug(" * transformed to [{$deny_command}]");
        if (preg_match($deny_command, $command) === 1) {

            _debug(" * DENY COMMAND MATCHES COMMAND SUBMITTED");

            if (!is_array($deny_token_list))
                $deny_token_list = array($deny_token_list);

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


?>