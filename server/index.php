<?php
//
// Nagios Remote Data Processor (NRDP)
// Copyright (c) 2010 Nagios Enterprises, LLC.
//
// License: Nagios Open Software License <http://www.nagios.com/legal/licenses>
//
// $Id: index.php 12 2010-06-19 04:19:35Z egalstad $

require_once(dirname(__FILE__).'/config.inc.php');
require_once(dirname(__FILE__).'/includes/utils.inc.php');

// load plugins
load_plugins();

// grab GET or POST variables 
grab_request_vars();

// check authorization
check_auth();

// handle the request
route_request();


function route_request(){
	
	$cmd=strtolower(grab_request_var("cmd"));
	
	// token if required for most everyting
	if($cmd!="" && $cmd!="hello")
		check_token();
		
	//echo "CMD='$cmd'<BR>";
		
	switch($cmd){
	
		// say hello
		case "hello":
			say_hello();
			break;
		// display a form for debugging/testing
		case "":
			display_form();
			break;
			
		default:
			//echo "PASSING TO PLUGINS<BR>";
			// let plugins handle the output
			$args=array(
				"cmd" => $cmd,
				);
			do_callbacks(CALLBACK_PROCESS_REQUEST,$args);
			break;
		}
		
	echo "NO REQUEST HANDLER";

	exit();
	}


function submit_nagios_command($raw=false){
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
	}

	
function submit_check_data(){
	global $cfg;
	global $request;
	
	$debug=false;
	
	if($debug){
		echo "REQUEST:<BR>";
		print_r($request);
		echo "<BR>";
		}
	
	// check results are passed as XML data
	$xmldata=grab_request_var("XMLDATA");
	
	if($debug){
		echo "XMLDATA:<BR>";
		print_r($xmldata);
		echo "<BR>";
		}
	
	// make sure we have data
	if(!have_value($xmldata))
		handle_api_error(ERROR_NO_DATA);
		
	// convert to xml
	$xml=@simplexml_load_string($xmldata);
	if(!$xml){
		print_r(libxml_get_errors());
		handle_api_error(ERROR_BAD_XML);
		}
		
	if($debug){
		echo "OUR XML:<BR>";
		print_r($xml);
		echo "<BR>";
		}

	// make sure we can write to check results dir
	if(!isset($cfg["check_results_dir"]))
		handle_api_error(ERROR_NO_CHECK_RESULTS_DIR);
	if(!file_exists($cfg["check_results_dir"]))
		handle_api_error(ERROR_BAD_CHECK_RESULTS_DIR);
		
	$total_checks=0;
		
	// process each result
	foreach($xml->checkresult as $cr){
	
		// get check result type
		$type="host";
		$checktype="1";  // default to passive check 
		foreach($cr->attributes() as $var => $val){
			if($var=="type")
				$type=strval($val);
			if($var=="checktype")
				$checktype=intval($val);
			}
			
		// common elements
		$hostname=strval($cr->hostname);
		$state=intval($cr->state);
		$output=strval($cr->output);
		
		// service checks
		if($type=="service"){
			$servicename=strval($cr->servicename);
			}
			
		////// WRITE THE CHECK RESULT //////
		// create a temp file to write to
		$tmpname=tempnam($cfg["check_results_dir"],"c");
		$fh=fopen($tmpname,"w");
		
		fprintf($fh,"### NRDP Check ###\n");
		fprintf($fh,"# Time: %s\n",date('r'));
		fprintf($fh,"host_name=%s\n",$hostname);
		if($type=="service")
			fprintf($fh,"service_description=%s\n",$servicename);
		fprintf($fh,"check_type=%d\n",$checktype); // 0 for active, 1 for passive
		if(intval($check_type)==0){
			fprintf($fh,"scheduled_check=1\n");
			//fprintf($fh,"reschedule_check=1\n");
			}
		else{
			fprintf($fh,"scheduled_check=0\n");
			}
		fprintf($fh,"early_timeout=0\n");
		fprintf($fh,"exited_ok=1\n");
		fprintf($fh,"return_code=%d\n",$state);
		fprintf($fh,"output=%s\\n\n",$output);
		
		// close the file
		fclose($fh);
		
		// change ownership and perms
		chgrp($tmpname,$cfg["nagios_command_group"]);
		chmod($tmpname,0770);
		
		// create an ok-to-go, so Nagios Core picks it up
		$fh=fopen($tmpname.".ok","w+");
		fclose($fh);
		
		$total_checks++;
		}
	
	
	output_api_header();
	
	echo "<result>\n";
	echo "  <status>0</status>\n";
	echo "  <message>OK</message>\n";
	echo "    <meta>\n";
	echo "       <output>".$total_checks." checks processed.</output>\n";
	echo "    </meta>\n";
	echo "</result>\n";
	}



function say_hello(){
	
	output_api_header();
	
	echo "<response>\n";
	echo "  <status>0</status>\n";
	echo "  <message>OK</message>\n";
	echo "  <product>".get_product_name()."</product>\n";
	echo "  <version>".get_product_version()."</version>\n";
	echo "</response>\n";
	
	exit();
	}


function display_form(){

	

	$mytoken="test";
?>
	<strong>Submit Nagios Command:</strong><br>
	<form action="" method="get">
	<input type="hidden" name="cmd" value="submitcmd">
	Token: <input type="text" name="token" value="" size="15"><br>
	Command: <input type="text" name="command" size="50" value="DISABLE_HOST_NOTIFICATIONS;somehost"><br>
	<input type="submit" name="btnSubmit" value="Submit Command">
	</form>
	
	<hr>
	
	<strong>Submit Check Data</strong><br>
	<form action="" method="post">
	<input type="hidden" name="cmd" value="submitcheck">
	Token: <input type="text" name="token" value="" size="15"><br>
	Check Data:<br>
<?php
$xml="
<?xml version='1.0'?> 
<checkresults>
	<checkresult type='host'>
		<hostname>somehost</hostname>
		<state>0</state>
		<output>Everything looks okay!|perfdata</output>
	</checkresult>
	<checkresult type='service'>
		<hostname>somehost</hostname>
		<servicename>someservice</servicename>
		<state>1</state>
		<output>WARNING: Danger Will Robinson!|perfdata</output>
	</checkresult>
</checkresults>
";
?>
<textarea cols="80" rows="15" name="XMLDATA"><?php echo htmlentities($xml);?></textarea><br>
	<input type="submit" name="btnSubmit" value="Submit Check Data">
	</form>
<?php
	exit();
	}
	

function load_plugins(){

	// include all plugins
	$p=dirname(__FILE__)."/plugins/";
	$subdirs=scandir($p);
	foreach($subdirs as $sd){
		if($sd=="." || $sd=="..")
			continue;
		$d=$p.$sd;
		if(is_dir($d)){
			$pf=$d."/$sd.inc.php";
			if(file_exists($pf)){
				//echo "REGISTERING PLUGIN: $pf<BR>";	
				include_once($pf);
				}
			}
		}
	}
?>