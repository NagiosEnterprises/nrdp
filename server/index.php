<?php
//
// Nagios Remote Data Processor (NRDP)
// Copyright (c) 2010 Nagios Enterprises, LLC.  All rights reserved.
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