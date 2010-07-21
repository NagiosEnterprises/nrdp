<?php
//
// Nagios Remote Data Processor (NRDP)
// Copyright (c) 2010 Nagios Enterprises, LLC.  All rights reserved.
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
	
	// token if required for most everyting
	if($cmd!="" && $cmd!="hello")
		check_token();
		
	switch($cmd){
	
		// raw nagios external commands
		case "submitrawcmd":
			submit_nagios_command(true);
			break;
		// normal nagios external commands
		case "submitcmd":
			submit_nagios_command(false);
			break;
		// check data
		case "submitcheck":
			submit_check_data();
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

	
function submit_check_data(){
	global $cfg;
	
	echo "REQUEST:<BR>";
	print_r($request);
	echo "<BR>";
	
	// check results are passed as XML data
	$xmldata=grab_request_var("XMLDATA");
	
	// make sure we have data
	if(!have_value($xmldata))
		handle_api_error(ERROR_NO_DATA);
		
	// convert to xml
	$xml=@simplexml_load_string($xmldata);
	if(!xml)
		handle_api_error(ERROR_BAD_XML);
		
	echo "OUR XML:<BR>";
	print_r($xml);
	echo "<BR>";

	// make sure we can write to check results dir
	if(!isset($instance_array["check_results_dir"]))
		handle_api_error(ERROR_NO_CHECK_RESULTS_DIR);
	if(!file_exists($instance_array["check_results_dir"]))
		handle_api_error(ERROR_BAD_CHECK_RESULTS_DIR);
		
	$total_checks=0;
		
	// process each result
	foreach($xml->checkresult as $cr){
	
		// get check result type
		$type="host";
		foreach($cr->attributes() as $var => $val){
			if($var=="type")
				$type=strval($val);
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
		$tmpname=tempname($cfg["check_results_dir"],"check");
		$fh=fopen($tmpname,"w");
		
		fprintf($fh,"### NRDP Check ###\n");
		fprintf($fh,"# Time: %s\n",date());
		fprintf($fh,"host_name=%s\n",$hostname);
		if($type=="service")
			fprintf($fh,"service_description=%s\n",$servicename);
		fprintf($fh,"check_type=1\n"); // 0 for active, 1 for passive
		fprintf($fh,"early_timeout=1\n")
		fprintf($fh,"exited_ok=1\n");
		fprintf($fh,"return_code=%d\n",$state);
		fprintf($fh,"output=%s\n",$output);
		
		// close the file and rename it, so Nagios Core picks it up
		fclose($fh);
		rename($tmpname,$tmpname.".ok");
		
		$total_checks++;
		}
			
	output_api_header();
	
	echo "<result>\n";
	echo "  <status>0</status>\n";
	echo "  <message>OK</message>\n";
	echo "  <meta>".$total_checks." checks processed.</meta>\n"
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