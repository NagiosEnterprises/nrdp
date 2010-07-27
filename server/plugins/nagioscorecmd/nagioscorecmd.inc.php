<?php
//
// Nagios Core Command NRDP Plugin
// Copyright (c) 2010 Nagios Enterprises, LLC.
// License: Nagios Open Software License <http://www.nagios.com/legal/licenses>
//
// $Id: utils.inc.php 12 2010-06-19 04:19:35Z egalstad $

require_once(dirname(__FILE__).'/../../config.inc.php');
require_once(dirname(__FILE__).'/../../includes/utils.inc.php');


register_callback(CALLBACK_PROCESS_REQUEST,'nagioscorecmd_process_request');

function nagioscorecmd_process_request($cbtype,$args){

	$cmd=grab_array_var($args,"cmd");
	
	//echo "CMD=$cmd<BR>";
	
	switch($cmd){
		// raw nagios external commands
		case "submitrawcmd":
			nagioscorecmd_submit_nagios_command(true);
			break;
		// normal nagios external commands
		case "submitcmd":
			nagioscorecmd_submit_nagios_command(false);
			break;
		// something else we don't handle...
		default:
			break;
		}
	}

function nagioscorecmd_submit_nagios_command($raw=false){
	global $cfg;
	
	$command=grab_request_var("command");
	
	// make sure we have a command
	if(!have_value($command))
		handle_api_error(ERROR_NO_COMMAND);

	// make sure we can write to external command file
	if(!isset($cfg["command_file"]))
		handle_api_error(ERROR_NO_COMMAND_FILE);
	if(!file_exists($cfg["command_file"]))
		handle_api_error(ERROR_BAD_COMMAND_FILE);
	if(!is_writeable($cfg["command_file"]))
		handle_api_error(ERROR_COMMAND_FILE_OPEN_WRITE);
		
	// open external command file
	if(($handle=@fopen($cfg["command_file"],"w+"))===false)
		handle_api_error(ERROR_COMMAND_FILE_OPEN);
		
	// get current time
	$ts=time();
		
	// write the external command(s)
	$error=false;
	if(!is_array($command)){
		if($raw==false)
			fwrite($handle,"[".$ts."] ");
		$result=fwrite($handle,$command."\n");
		//echo "WROTE: ".$request["command"]."<BR>\n";
		}
	else{
		foreach($command as $cmd){
			if($raw==false)
				fwrite($handle,"[".$ts."] ");
			$result=fwrite($handle,$cmd."\n");
			//echo "WROTE: ".$cmd."<BR>\n";
			if($result===false)
				break;
			}
		}

	// close the file
	fclose($handle);

	if($result===false)
		handle_api_error(ERROR_BAD_WRITE);
	
	output_api_header();
	
	echo "<result>\n";
	echo "  <status>0</status>\n";
	echo "  <message>OK</message>\n";
	echo "</result>\n";

	exit();
	}

	

?>