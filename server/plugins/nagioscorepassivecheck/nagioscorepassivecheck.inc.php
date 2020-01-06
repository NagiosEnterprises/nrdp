<?php
/*****************************************************************************
 *
 *
 *  NRDP Nagios Core Passive Check Plugin
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


register_callback(CALLBACK_PROCESS_REQUEST, "nagioscorepassivecheck_process_request");


// process a command for this plugin
function nagioscorepassivecheck_process_request($cbtype, $args)
{
    $cmd = grab_array_var($args, "cmd");
    _debug("nagioscorepassivecheck_process_request(cbtype = {$cbtype}, args[cmd] = {$cmd}");

    // Submit check data
    if ($cmd == "submitcheck") {
        nagioscorepassivecheck_submit_check_data();
    }

    _debug("nagioscorepassivecheck_process_request() had no registered callbacks, returning");
}


// attempt to submit check data
function nagioscorepassivecheck_submit_check_data()
{
    // debug the request data
    global $request;
    foreach ($request as $index => $req) {
        if (is_array($req)) {
            $req = print_r($req, true);
        }
        _debug("REQUEST: [{$index}] {$req}");
    }


    $output_type = output_type();


    if ($output_type == TYPE_XML) {
        
        $data = grab_xml();
        $data = @simplexml_load_string($data);

        if ($data === false) {

            $xmlerr = print_r(libxml_get_errors(), true);
            _debug("conversion to xml failed: {$xmlerr}");
            handle_api_error(ERROR_BAD_XML, $xmlerr);
        }

        _debug("our xml: " . print_r($data, true));
    }

    else if ($output_type == TYPE_JSON) {

        $data = grab_json();

        $data = @json_decode($data, true);

        if ($data == false) {

            if (version_compare(phpversion(), "5.5.0", ">=")) {
                $last_error_cb = "json_last_error_msg";
            } else {
                $last_error_cb = "json_last_error";
            }

            $jsonerr = print_r($last_error_cb(), true);
            _debug("conversion to json failed: {$jsonerr}");
            handle_api_error(ERROR_BAD_JSON, $jsonerr);
        }

        _debug("our json: " . print_r($data, true));
    }
   


    // Make sure we can write to check results dir
    $check_results_dir = grab_cfg_var("check_results_dir");

    if (empty($check_results_dir)) {

        _debug("we have no cfg[check_results_dir], bailing");
        handle_api_error(ERROR_NO_CHECK_RESULTS_DIR);
    }

    if (!file_exists($check_results_dir)) {

        _debug("cfg[check_results_dir] ({$check_results_dir}) doesn't exist, bailing");
        handle_api_error(ERROR_BAD_CHECK_RESULTS_DIR);
    }

    $total_checks = 0;

    // Get our iterator to loop over
    $check_results = get_xml_or_json($output_type, $data, "checkresult", "checkresults");

    foreach ($check_results as $cr) {

        $type = "host";

        $attributes = get_xml_or_json($output_type, $cr, "attributes", "checkresult", true);

        foreach ($attributes as $key => $val) {
            if ($key == "type") {
                $type = strval($val);
            }
        }

        $hostname = strval(get_xml_or_json($output_type, $cr, "hostname", "hostname"));
        $servicename = strval(get_xml_or_json($output_type, $cr, "servicename", "servicename"));
        $state = strval(get_xml_or_json($output_type, $cr, "state", "state"));
        $output = strval(get_xml_or_json($output_type, $cr, "output", "output"));
        $output = str_replace("\n", "\\n", $output);

        $allow_old_results = grab_cfg_var("allow_old_results");

        $time = intval(get_xml_or_json($output_type, $cr, "time", "time"));


        $debug_msg = "gathered the following datas:\n";
        $debug_msg .= "type: {$type}\n";
        $debug_msg .= "hostname: {$hostname}\n";
        $debug_msg .= "servicename: {$servicename}\n";
        $debug_msg .= "state: {$state}\n";
        $debug_msg .= "output: {$output}\n";
        $debug_msg .= "allow_old_results: {$allow_old_results}\n";
        $debug_msg .= "time: {$time}\n";

        _debug($debug_msg);
        

        if (!empty($time) && $allow_old_results == true) {
            nrdp_write_check_output_to_ndo($hostname, $servicename, $state, $output, $type, $time);
        } else {
            nrdp_write_check_output_to_cmd($hostname, $servicename, $state, $output, $type);
        }

        $total_checks++;
    }

    _debug("all nrdp checks have been written");

    $result = array(
        "result" => array(
            "status" => 0,
            "message" => "OK",
            "meta" => array(
                "output" => "{$total_checks} checks processed",
                ),
            ),
        );

    output_response($result);
    exit();
}



// write out the check command to core
function nrdp_write_check_output_to_cmd($hostname, $servicename, $state, $output, $type)
{
    _debug("nrdp_write_check_output_to_cmd(hostname={$hostname}, servicename={$servicename}, state={$state}, type={$type}, output={$output}");


    // create a temp file to write to
    $check_results_dir = grab_cfg_var("check_results_dir");
    $check_file = tempnam($check_results_dir, "c");


    // check if the file is in the check_results_dir (or its symlink)
    if (strpos($check_file, realpath($check_results_dir)) === false) {

        unlink($check_file);
        _debug("check_file({$check_file}) not in cfg[check_results_dir] ({$check_results_dir}), (or a symlink) bailing");
        handle_api_error(ERROR_BAD_CHECK_RESULTS_DIR);
    }


    // we only need this if we're a service
    $service_line = "";
    if ($type == "service") {
        $service_line = "service_description={$servicename}\n";
    }


    // file contents
    $check_result_contents = "### NRDP Check ###\n";
    $check_result_contents .= "start_time=" . time() . ".0\n";
    $check_result_contents .= "# Time: " . date("r") . "\n";
    $check_result_contents .= "host_name={$hostname}\n";
    $check_result_contents .= $service_line;
    $check_result_contents .= "check_type=1\n";
    $check_result_contents .= "early_timeout=1\n";
    $check_result_contents .= "exited_ok=1\n";
    $check_result_contents .= "return_code={$state}\n";
    $check_result_contents .= "output=${output}\\n\n";

    // put check result into the check file    
    file_put_contents($check_file, $check_result_contents);


    // Change ownership and perms
    $command_group = grab_cfg_var("nagios_command_group", "nagcmd");


    // chgrp if the function we want doesn't exist
    // or if it does exist and doesn't return false
    if (function_exists("posix_getgrnam")) {
        if (posix_getgrnam($command_group) !== false) {

            $group_changed = chgrp($check_file, $command_group);
            if ($group_changed == false) {
                _debug("unable to change group on {$check_file} to {$command_group}");
            }
        }
    }
    else {
        _debug("nagios_command_group={$command_group} does not exist, not chgrp()ing");
    }


    $mod_changed = chmod($check_file, 0770);
    if ($mod_changed == false) {
        _debug("unable to change permissions on {$check_file} to 0770");
    }
    

    // Create an ok-to-go, so Nagios Core picks it up
    $touched = touch("{$check_file}.ok");
    if ($touched == false) {
        _debug("unable to create {$check_file}.ok (ok file)");
    }


    _debug("nrdp_write_check_output_to_cmd() completed");
}



// writes check data directly into ndo bypassing core
// so that we can input old (past checks) data into the database
function nrdp_write_check_output_to_ndo($hostname, $servicename, $state, $output, $type, $time)
{
    _debug("nrdp_write_check_output_to_ndo(hostname={$hostname}, servicename={$servicename}, state={$state}, type={$type}, time={$time}, output={$output}");


    // check that the type passed is valid
    $valid_types = array("host", "service");
    if (!in_array($type, $valid_types)) {
        _debug("invalid type: {$type}");
        return false;
    }


    // use nagiosxi $cfg var for connecting to ndo
    require("/usr/local/nagiosxi/html/config.inc.php");


    // we aren't grabbing from our global cfg,
    // which is why we aren't using grab_cfg_var
    $db_info  = grab_array_var($cfg, "db_info");
    $ndodb    = grab_array_var($db_info, "ndoutils");

    $dbserver = grab_array_var($ndodb, "dbserver");
    $user     = grab_array_var($ndodb, "user");
    $pass     = grab_array_var($ndodb, "pass");
    $db       = grab_array_var($ndodb, "db");


    // get the nagios install configuration
    $nagios_cfg = read_nagios_config_file();


    // connect straight to db
    $db = new MySQLi($dbserver, $user, $pass, $db);
    if ($db->connect_errno) {
        _debug("Couldn't connect to database, bailing");
        return false;
    }


    $log_state_change = false;
    $state = intval($state);

    // out of bounds
    if ($state > 3) {
        return;
    }


    // split output from long output
    $long_output = "";
    if (strpos("\n", $output) !== false) {
        $outputs = explode("\n", $output, 2);
        $output = $outputs[0];
        $long_output = $outputs[1];
    }


    // drop off the perfdata if it exists, we won't need it
    $output = strtok($output, "|");
    $long_output = strtok($long_output, "|");


    // we only need this if we're a host
    $passive_host_checks_are_soft = grab_array_var($cfg, "passive_host_checks_are_soft", true);


    // get the object id
    $sql = "SELECT object_id FROM nagios_objects WHERE name1 = '{$hostname}' AND name2 = '{$servicename}'";
    $result = $db->query($sql);

    if ($result->num_rows > 0) {

        $r = $result->fetch_object();
        $object_id = intval($r->object_id);

        // We now have the object_id so let's start the processing
        $sql = "SELECT * FROM nagios_{$type}status WHERE {$type}_object_id = {$object_id}";
        $result = $db->query($sql);
        $status = $result->fetch_object();


        $update_status_sql = "";
        $add_last_hard_state_change = false;
        $last_hard_state = 0;
        $add_last_state_change = false;


        $state_type = 0;
        $current_attempt = $status->current_check_attempt + 1;
        if ($current_attempt >= $status->max_check_attempts) {

            $current_attempt = $status->max_check_attempts;
            $state_type = 1;
            $add_last_hard_state_change = true;
            $log_state_change = true;
        }


        // check if state type changed from soft <-> hard
        if ($state != $status->current_state) {

            if ($status->current_state == 0) {
                $current_attempt = 1;
            }

            $log_state_change = true;
            $add_last_state_change = true;
        }


        // if state is 0 - force a hard check
        if ($state == 0) {
            $state_type = 1;
            $current_attempt = 1;

            if ($log_state_change) {

                $add_last_hard_state_change = true;
                $last_hard_state = 1;
            }
        }


        // if this is a host and passive host checks are hard..
        if ($type == "host" && $passive_host_checks_are_soft == false) {

            $state_type = 1;
            $current_attempt = 1;

            if ($state != $status->current_state) {

                $log_state_change = true;
                $add_last_hard_state_change = true;
                $add_last_state_change = true;
            }
        }


        // start building part of the update status query


        $update_status_sql .= "current_check_attempt = {$current_attempt}, ";

        if ($add_last_hard_state_change) {
            $last_hard_state = $status->current_state;
            $update_status_sql .= "last_hard_state_change = FROM_UNIXTIME({$time}), ";
        }

        if ($add_last_state_change) {
            $update_status_sql .= "last_state_change = FROM_UNIXTIME({$time}), ";
        }


        $lt_state = "up";

        switch ($state) {
        case 0:
            $lt_state = "up";
            if ($type == "service") {
                $lt_state = "ok";
            }
            break;

        case 1:
            $lt_state = "down";
            if ($type == "service") {
                $lt_state = "warning";
            }
            break;

        case 2:
            $lt_state = "unreachable";
            if ($type == "service") {
                $lt_state = "critical";
            }
            break;

        default:
            $lt_state = "unreachable";
            if ($type == "service") {
                $lt_state = "unknown";
            }
            break;
        }
        $update_status_sql .= "last_time_{$lt_state} = FROM_UNIXTIME({$time}), ";


        // escape important strings
        $output      = $db->real_escape_string($output);
        $long_output = $db->real_escape_string($long_output);

        $sql = "UPDATE nagios_{$type}status
                SET status_update_time = FROM_UNIXTIME({$time}),
                has_been_checked = 1,
                output = '{$output}',
                long_output = '{$long_output}',
                current_state = {$state},
                state_type = {$state_type},
                last_check = FROM_UNIXTIME({$time}),
                check_type = 1,
                execution_time = 0,
                {$update_status_sql}
                latency = 0
                WHERE service_object_id = {$object_id}";
        
        $db->query($sql);


        // update the state history
        if ($log_state_change) {

            $max_check_attempts = $status->max_check_attempts;
            $current_state = $status->current_state;

            $sql = "INSERT INTO nagios_statehistory
                    (
                      instance_id, state_time, object_id, state_change, 
                      state, state_type, current_check_attempt, 
                      max_check_attempts, last_state, last_hard_state, 
                      output, long_output
                    )
                    VALUES 
                    (
                      1, FROM_UNIXTIME({$time}), {$object_id}, 1, 
                      {$state}, {$state_type}, {$current_attempt}, 
                      {$max_check_attempts}, {$current_state}, {$last_hard_state}, 
                      '{$output}', '{$long_output}'
                    )";
            $db->query($sql);
        }


        // we only need this if we're a service
        $service_log = "";
        if ($type == "service") {
            $service_log = "{$servicename};";
        }

        $logentry = "SERVICE ALERT: {$hostname};{$service_log}";
        $logentry .= human_readable_state($type, $state) .";";
        $logentry .= human_readable_state_type($state_type) .";";
        $logentry .= "{$state};{$output}";


        // types to reference for logentry type
        $host_up_type          = 1024;
        $host_down_type        = 2048;
        $host_unreachable_type = 4096;
        $service_ok_type       = 8192;
        $service_unknown_type  = 16384;
        $service_warning_type  = 32768;
        $service_critical_type = 65536;


        // get the log id type based on the current state
        switch ($state) {

        case 0:
            if ($type == "host") {
                $logentry_type = $host_up_type;
            } else if ($type == "service") {
                $logentry_type = $service_ok_type;
            }
            break;

        case 1:
            if ($type == "host") {
                $logentry_type = $host_down_type;
            } else if ($type == "service") {
                $logentry_type = $service_warning_type;
            }
            break;

        case 2:
            if ($type == "host") {
                $logentry_type = $host_unreachable_type;
            } else if ($type == "service") {
                $logentry_type = $service_critical_type;
            }
            break;

        default:
            if ($type == "host") {
                $logentry_type = $host_unreachable_type;
            } else if ($type == "service") {
                $logentry_type = $service_unknown_type;
            }
            break;
        }

        $logentry = $db->real_escape_string($logentry);

        // add a row into the log entries table
        $sql = "INSERT INTO nagios_logentries
                (
                  instance_id, logentry_time, entry_time, 
                  entry_time_usec, logentry_type, logentry_data,
                  realtime_data, inferred_data_extracted
                )
                VALUES 
                (
                  1, FROM_UNIXTIME({$time}), FROM_UNIXTIME({$time}),
                  0, {$logentry_type}, '{$logentry}', 
                  1, 1
                )";

        $db->query($sql);


        // add log entry to spool so we can add it into the real log in the proper place
        $root_dir = grab_array_var($cfg, "root_dir", "/usr/local/nagiosxi");
        $spool_dir = "{$root_dir}/tmp/passive_spool";

        if (!file_exists($spool_dir)) {

            _debug("spool directory ({$spool_dir}) does not exist, attempting creation..");
            $made_dir = mkdir($spool_dir);

            // couldn't make dir for some reason
            if ($made_dir == false) {
                _debug("unable to create spool_dir!");
            }

            // otherwise set perms
            else {

                $mod_changed = chmod($spool_dir, 0775);

                if ($mod_changed == false) {
                    _debug("unable to change mod on spool_dir to 0775");
                }
            }
        }

        // Add a new file with the log entries in it ...
        $latest_hour = date("M j, Y H:00", time());
        $spoolfile_time = strtotime($latest_hour);

        $logfile_entry = "[{$time}] {$logentry}\n";

        $spoolfile = "{$spool_dir}/{$spoolfile_time}.spool";

        file_put_contents($spoolfile, $logfile_entry, FILE_APPEND);

        chmod($spoolfile, 0664);
        chgrp($spoolfile, "nagios");
    }

    $db->close();
    _debug("nrdp_write_check_output_to_ndo() successful");
    return;
}



// parse the nagios configuration file and build
// associative array for easy recall
function read_nagios_config_file()
{
    $nagios_cfg = file_get_contents("/usr/local/nagios/etc/nagios.cfg");
    $ncfg = explode("\n", $nagios_cfg);

    $nagios_cfg = array();

    foreach ($ncfg as $line) {

        if (strpos($line, "=") !== false) {

            $var = explode("=", $line);

            $key = trim($var[0]);
            $val = trim($var[1]);

            $nagios_cfg[$key] = $val;
        }
    }

    return $nagios_cfg;
}
