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
    _debug("nagioscorecmd_submit_nagios_command(raw=" . ($raw ? 'TRUE' : 'FALSE'));

    // If commands are disallowed in the config...
    if ($cfg["disable_external_commands"] === true) {
        _debug('cfg[disable_external_commands] == true, bailing');
        handle_api_error(ERROR_DISABLED_COMMAND);
        return;
    }

    $command = grab_request_var("command");

    // Make sure we have a command
    if (!have_value($command)) {
        _debug('we have no command, bailing');
        handle_api_error(ERROR_NO_COMMAND);
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


?>