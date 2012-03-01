#!/usr/bin/php
<?php

############################################################################
#
# check_mssql - Checks various aspect of MSSQL servers
#
# Version 0.6.6, Copyright (c) 2008 Gary Danko <gdanko@gmail.com>
#
# Notes:
#
#   Version 0.1.0 - 2008/08/14
#   Initial release. Accepts hostname, username, password, port,
#   database name, and an optional query to run.
#
#   Version 0.2.0 - 2008/08/15
#   You can now execute a query or stored procedure and report
#   on expected results. Queries should be simplistic since
#   only the first row is returned.
#
#   Version 0.2.2 - 2008/08/18
#   Nothing major. Just a couple of cosmetic fixes.
#
#   Version 0.5.0 - 2008/09/29
#   Major rewrite. No new functionality. RegEx added to
#   validate command line options.
#
#   Version 0.6.0 - 2008/10/23
#   Allows the user to specify a SQL file with --query
#
#   Version 0.6.3 - 2008/10/26
#   Removed the -r requirement with -q.
#
#   Version 0.6.4 - 2008/10/31
#   Fixed a bug that would nullify an expected result of "0"
#
#   Version 0.6.5 - 2008/10/31
#   Minor fix for better display of error output.
#
#   Version 0.6.6 - 2008/10/31
#   Prepends "exec " to --storedproc if it doesn't exist.
#
#   This plugin will check the general health of an MSSQL
#   server. It will also report the query duration and allows
#   you to set warning and critical thresholds based on the
#   duration.
#
#   Requires:
#       yphp_cli-5.2.5_1 *
#       yphp_mssql-5.2.5_1 *
#	freetds *
#
# License Information:
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
#
############################################################################

$progname = "check_mssql";
$version = "0.6.6";

// Parse the command line options
for ($i = 1; $i < $_SERVER['argc']; $i++) {
	$arg = $_SERVER["argv"][$i];
	switch($arg) {
		case '-h':
		case '--help':
			help();
		break;

		case '-V':
		case '--version':
			version();
		break;

		case '-H':
		case '--hostname':
			$db_host = check_command_line_option($_SERVER["argv"][$i], $i);
		break;

		case '-u':
		case '-U':
		case '--username':
			$db_user = check_command_line_option($_SERVER["argv"][$i], $i);
		break;

		case '-P':
		case '--password':
			$db_pass = check_command_line_option($_SERVER["argv"][$i], $i);
		break;

		case '-p':
		case '--port':
			$db_port = check_command_line_option($_SERVER["argv"][$i], $i);
		break;

		case '-d':
		case '--database':
			$db_name = check_command_line_option($_SERVER["argv"][$i], $i);
		break;

		case '-q':
		case '--query':
			$query = check_command_line_option($_SERVER["argv"][$i], $i);
			$querytype = "query";
		break;

		case '-s':
		case '--storedproc':
			$storedproc = check_command_line_option($_SERVER["argv"][$i], $i);
			$querytype = "stored procedure";
		break;

		case '-r':
		case '--result':
			$expected_result = check_command_line_option($_SERVER["argv"][$i], $i);
		break;

		case '-w':
		case '--warning':
			$warning = check_command_line_option($_SERVER["argv"][$i], $i);
		break;

		case '-c':
		case '--critical':
			$critical = check_command_line_option($_SERVER["argv"][$i], $i);
		break;
	}
}

// Error out if mssql support is not present.
if (!function_exists('mssql_connect')) {
	print "UNKNOWN: MSSQL support is not installed on this server.\n";
	exit(3);
}

// If no options are set, display the help
if ($_SERVER['argc'] == 1) {
	print "$progname: Could not parse arguments\n";
	usage();
	exit;
}

// Determine if the query is a SQL file or a text query
if (isset($query)) {
	if (file_exists($query)) {
		$query = file_get_contents($query);
	}
}

// Add "exec" to the beginning of the stored proc if it doesnt exist.
if (isset($storedproc)) {
	if (substr($storedproc, 0, 5) != "exec ") {
		$storedproc = "exec $storedproc";
	}
}

// Do not allow both -q and -s
if (isset($query) && isset($storedproc)) {
	print "UNKNOWN: The -q and -s switches are mutually exclusive. You may not select both.\n";
	exit(3);
}

// -r demands -q and -q demands -r
if (isset($expected_result) && !isset($query)) {
	print "UNKNOWN: The -r switch requires the -q switch. Please specify a query.\n";
	exit(3);
}

// Validate the hostname
if (isset($db_host)) {
	if (!preg_match("/^([a-zA-Z0-9-]+[\.])+([a-zA-Z0-9]+)$/", $db_host)) {
		print "UNKNOWN: Invalid characters in the hostname.\n";
		exit(3);
	}
} else {
	print "UNKNOWN: The required hostname field is missing.\n";
	exit(3);
}

// Validate the port
if (isset($db_port)) {
	if (!preg_match("/^([0-9]{4,5})$/", $db_port)) {
		print "UNKNOWN: The port field should be numeric and in the range 1000-65535.\n";
		exit(3);
	}
} else {
	$db_port = 1433;
}

// Validate the username
if (isset($db_user)) {
	if (!preg_match("/^[a-zA-Z0-9-]{2,32}$/", $db_user)) {
		print "UNKNOWN: Invalid characters in the username.\n";
		exit(3);
	}
} else {
	print "UNKNOWN: You must specify a username for this DB connection.\n";
	exit(3);
}

// Validate the password
if (empty($db_pass)) {
	print "UNKNOWN: You must specify a password for this DB connection.\n";
	exit(3);
}

// Validate the warning threshold
if (isset($warning)) {
	if (!preg_match("/^([0-9]+){1,3}$/", $warning)) {
		print "UNKNOWN: Invalid warning threshold.\n";
		exit(3);
	}
} else {
	$warning = 2;
}

// Validate the critical threshold
if (isset($critical)) {
	if (!preg_match("/^([0-9]+){1,3}$/", $critical)) {
		print "UNKNOWN: Invalid critical threshold.\n";
		exit(3);
	}
} else {
	$critical = 5;
}

// Is warning greater than critical?
if ($warning > $critical) {
	$exit_code = 3;
	$output_msg = "UNKNOWN: warning value should be lower than critical value.\n";
	display_output($exit_code, $output_msg);
}

// Attempt to connect to the server
$time_start = microtime(true);
if (!$connection = @mssql_connect("$db_host:$db_port", $db_user, $db_pass)) {
	$exit_code = 2;
	$output_msg = "CRITICAL: Could not connect to $db_host as $db_user.";
	display_output($exit_code, $output_msg);
} else {
	$time_end = microtime(true);
	$query_duration = round(($time_end - $time_start), 6);
	// Exit now if no query or stored procedure is specified
	if (empty($storedproc) && empty($query)) {
		$output_msg = "Connect time=$query_duration seconds.\n";
		process_results($query_duration, $warning, $critical, $output_msg);
	}
}

if (empty($db_name)) {
	$exit_code = 3;
	$output_msg = "UNKNOWN: You must specify a database with the -q or -s switches.\n";
	display_output($exit_code, $output_msg);
}

// Attempt to select the database
if(!@mssql_select_db($db_name, $connection)) {
	$exit_code = 2;
	$output_msg = "CRITICAL: Could not connect to $db_name on $db_host.\n";
	display_output($exit_code, $output_msg);
}

// Attempt to execute the query/stored procedure
$time_start = microtime(true);
if (!$query_data = @mssql_query("$query")) {
	$exit_code = 2;
	$output_msg = "CRITICAL: Could not execute the $querytype.\n";
	display_output($exit_code, $output_msg);
} else {
	$time_end = microtime(true);
	$query_duration = round(($time_end - $time_start), 6);
	$output_msg = "Query duration=$query_duration seconds.\n";
}

if ($querytype == "query" && !empty($expected_result)) {
	if (mssql_num_rows($query_data) > 0 ) {
		while ($row = mssql_fetch_row($query_data)) {
			$query_result = $row[0];
		}
	}
	if ($query_result == $expected_result) {
		$output_msg = "Query results matched, query duration=$query_duration seconds.\n";
	} else {
		$exit_code = 2;
		$output_msg = "CRITICAL: Query expected \"$expected_result\" but got \"$query_result\".\n";
		display_output($exit_code, $output_msg);
	}
}
process_results($query_duration, $warning, $critical, $output_msg);

//-----------//
// Functions //
//-----------//

// Function to validate a command line option
function check_command_line_option($option, $i) {
	// If the option requires an argument but one isn't sent, bail out
	$next_offset = $i + 1;
	if (!isset($_SERVER['argv'][$next_offset]) || substr($_SERVER['argv'][$next_offset], 0, 1) == "-") {
		print "UNKNOWN: The \"$option\" option requires a value.\n";
	exit(3);
	} else {
		${$option} = $_SERVER['argv'][++$i];
		return ${$option};
	}
}

// Function to process the results
function process_results($query_duration, $warning, $critical, $output_msg) {
	if ($query_duration > $critical) {
		$state = "CRITICAL";
		$exit_code = 2;
	} elseif ($query_duration > $warning) {
		$state = "WARNING";
		$exit_code = 1;
	} else {
		$state = "OK";
		$exit_code = 0;
	}
	$output_msg = "$state: $output_msg";
	display_output($exit_code, $output_msg);
}

// Function to display the output
function display_output($exit_code, $output_msg) {
	print $output_msg;
	exit($exit_code);
}

// Function to display usage information
function usage() {
global $progname, $version;
print <<<EOF
Usage: $progname -H <hostname> --username <username> --password <password>
       [--port <port>] [--database <database>] [--query <"text">|filename]
       [--storeproc <"text">] [--result <text>] [--warning <warn time>]
       [--critical <critical time>] [--help] [--version]

EOF;
}

// Function to display copyright information
function copyright() {
global $progname, $version;
print <<<EOF
Copyright (c) 2008 Gary Danko (gdanko@gmail.com)

This plugin checks various aspect of an MSSQL server. It will also
execute queries or stored procedures and return results based on
query execution times and expected query results.

EOF;
}

// Function to display detailed help
function help() {
global $progname, $version;
print "$progname, $version\n";
copyright();
print <<<EOF

Options:
 -h, --help
    Print detailed help screen.
 -V, --version
    Print version information.
 -H, --hostname
    Hostname of the MSSQL server.
 -U, --username
    Username to use when logging into the MSSQL server.
 -P, --password
    Password to use when logging into the MSSQL server.
 -p, --port
    Optional MSSQL server port. (Default is 1433).
 -d, --database
    Optional DB name to connect to. 
 -q, --query
    Optional query or SQL file to execute against the MSSQL server.
 -s, --storedproc
    Optional stored procedure to execute against the MSSQL server.
 -r, --result
    Expected result from the specified query, requires -q. The query
    pulls only the first row for comparison, so you should limit
    yourself to small, simple queries.
 -w, --warning
    Warning threshold in seconds on duration of check (Default is 2).
 -c, --critical
    Critical threshold in seconds on duration of check (Default is 5).

Example: $progname -H myserver -U myuser -P mypass -q /tmp/query.sql -w 2 -c 5
Example: $progname -H myserver -U myuser -P mypass -q "select count(*) from mytable" -r "632" -w 2 -c 5
Send any questions regarding this utility to gdanko@gmail.com.

EOF;
exit(0);
}

// Function to display version information
function version() {
global $version;
print <<<EOF
$version

EOF;
exit(0);
}
?>
