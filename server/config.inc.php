<?php
/*****************************************************************************
 *
 *
 *  NRDP Configuration file
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



/////////////////////////////////////////////////////////////
//
// authorized_tokens
//
// An array of one or more tokens that are valid for this NRDP install
// a client request must contain a valid token in order for the NRDP to response or honor the request
// NOTE: Tokens are just alphanumeric strings - make them hard to guess!

$cfg["authorized_tokens"] = array(
    // "mysecrettoken",  // <-- not a good token
    // "90dfs7jwn3",     // <-- a better token (don't use this exact one, make your own)
);



/////////////////////////////////////////////////////////////
//
// external_commands_deny_tokens
//
// By default, all authorized tokens are allowed to submit any
// external command (unless it"s disable below)
// This is a deny mapping in the form of COMMAND => TOKEN or TOKENS
// You can specify a whole command, or use * as a wildcard
// Or you can specify "all" to stop any token from using any external command
// the tokens specified can either be a string with 1 token, or an array of 1 or more tokens

$cfg["external_commands_deny_tokens"] = array(
    // "ACKNOWLEDGE_HOST_PROBLEM" => array("mysecrettoken", "myothertoken"),
    // "ACKNOWLEDGE_SVC_PROBLEM"  => "mysecrettoken",
    // "all"                      => array("mysecrettoken", "myothertoken"),
    // "ACKNOWLEDGE_*"            => "mysecrettoken",
    // "*_HOST_*"                 => array("mysecrettoken", "myothertoken"),
);



/////////////////////////////////////////////////////////////
//
// require_https
// 
// Do we require that HTTPS be used to access NRDP?
// set this value to false to disable HTTPS requirement

$cfg["require_https"] = false;



/////////////////////////////////////////////////////////////
//
// require_basic_auth
//
// Do we require that basic authentication be used to access NRDP?
// set this value to false to disable basic auth requirement 

$cfg["require_basic_auth"] = false;



/////////////////////////////////////////////////////////////
//
// valid_basic_auth_users
//
// What basic authentication users are allowed to access NRDP?
// comment this variable out to allow all authenticated users access to the NRDP

$cfg["valid_basic_auth_users"] = array(
    "nrdpuser"
);



/////////////////////////////////////////////////////////////
//
// nagios_command_group
//
// The name of the system group that has write permissions to the external command file
// this group is also used to set file permissions when writing bulk commands or passive check results
// NOTE: both the Apache and Nagios users must be a member of this group

$cfg["nagios_command_group"] = "nagcmd";



/////////////////////////////////////////////////////////////
//
// command_file
//
// Full path to Nagios external command file

$cfg["command_file"] = "/usr/local/nagios/var/rw/nagios.cmd";



/////////////////////////////////////////////////////////////
//
// check_results_dir
//
// Full path to check results spool directory

$cfg["check_results_dir"] = "/usr/local/nagios/var/spool/checkresults";



/////////////////////////////////////////////////////////////
//
// disable_external_commands
//
// Should we allow external commands? Set to true or false (Boolean, not a string)

$cfg["disable_external_commands"] = false;



/////////////////////////////////////////////////////////////
//
// allow_old_results
//
// Allows Nagios XI to send old check results directly into NDO if configured

$cfg["allow_old_results"] = false;



/////////////////////////////////////////////////////////////
//
// hide_display_page
//
// Should the main NRDP display page that allows submissions be hidden? true/false

$cfg["hide_display_page"] = false;



/////////////////////////////////////////////////////////////
//
// debug
//
// Enable debug logging

$cfg["debug"] = false;



/////////////////////////////////////////////////////////////
//
// debug_log
//
// Where should the logs go?

$cfg["debug_log"] = "/usr/local/nrdp/server/debug.log";
