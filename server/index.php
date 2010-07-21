<?php
//
// Nagios Distributed Control Manager
// Copyright (c) 2008 Nagios Enterprises, LLC.  All rights reserved.
//
// $Id: index.php 12 2010-06-19 04:19:35Z egalstad $

require_once(dirname(__FILE__).'/config.inc.php');
require_once(dirname(__FILE__).'/includes/utils.inc.php');

// grab GET or POST variables 
grab_request_vars();

// check authorization
check_auth();

// handle the request
route_request();


function route_request(){
	
	$cmd=strtolower(grab_request_var("cmd"));
		
	switch($cmd){
	
		// raw nagios external commands
		case "submitrawcmd":
			submit_nagios_command(true);
			break;
		// normal nagios external commands
		case "submitcmd":
			submit_nagios_command(false);
			break;
		// say hello
		case "hello":
			say_hello();
			break;
		// display a form for debugging/testing
		default:
			display_form();
			break;
		}

	exit();
	}


function submit_nagios_command($raw=false){
	global $cfg;
	
	$command=grab_request_var("command");
	
	// make sure we have a command
	if(!have_value($command))
		handle_api_error(ERROR_NO_COMMAND);

	// make sure we can write to external command file
	if(!isset($instance_array["command_file"]))
		handle_api_error(ERROR_NO_COMMAND_FILE);
	if(!file_exists($instance_array["command_file"]))
		handle_api_error(ERROR_BAD_COMMAND_FILE);
	if(!is_writeable($instance_array["command_file"]))
		handle_api_error(ERROR_COMMAND_FILE_OPEN_WRITE);
		
	// open external command file
	if(($handle=@fopen($instance_array["command_file"],"w+"))===false)
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
	}


function say_hello(){
	global $cfg;
	
	output_api_header();
	
	echo "<response>\n";
	echo "  <status>0</status>\n";
	echo "  <message>OK</message>\n";
	echo "  <product>".get_product_name()."</product>\n";
	echo "  <version>".get_product_version()."</version>\n";
	echo "</response>\n";
	}


function display_form(){
?>
	<strong>Submit Nagios Command:</strong><br>
	<form action="" method="get">
	<input type="hidden" name="cmd" value"submitcmd">
	Command: <input type="text" name="command" size="15" value="DISABLE_HOST_NOTIFICATIONS;somehost"><br>
	<input type="submit" name="btnSubmit" value="Submit Command">
	</form>
	
	<strong>Submit Check Data</strong><br>
	<form action="" method="post">
	<input type="hidden" name="cmd" value"submitcheck">
	Check Data:<br>
<?php
$xml="
<checkresults>
	<checkresult type='host'>
		<hostname>somehost</hostname>
		<state>0</state>
		<output>Everything looks okay!|perfdata</output>
	<checkresult>
	<checkresult type='service'>
		<hostname>somehost</hostname>
		<servicename>someservice</servicename>
		<state>1</state>
		<output>WARNING: Danger Will Robinson!|perfdata</output>
	<checkresult>
</checkresults>
";
?>
<textarea cols="30" rows="5" name="XMLDATA"><?php echo htmlentities($xml);?></textarea>
	<input type="submit" name="btnSubmit" value="Submit Check Data">
	</form>
<?php
	}
	

?>