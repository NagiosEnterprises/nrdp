<?php
//
// Nagios Core Passive Check NRDP Plugin
// Copyright (c) 2010 Nagios Enterprises, LLC.
// License: Nagios Open Software License <http://www.nagios.com/legal/licenses>
//
// $Id: utils.inc.php 12 2010-06-19 04:19:35Z egalstad $

require_once(dirname(__FILE__).'/../../config.inc.php');
require_once(dirname(__FILE__).'/../../includes/utils.inc.php');


register_callback(CALLBACK_PROCESS_REQUEST,'nagioscorepassivecheck_process_request');

function nagioscorepassivecheck_process_request($cbtype,$args){

	$cmd=grab_array_var($args,"cmd");
	
	//echo "CMD=$cmd<BR>";
	
	switch($cmd){
		// check data
		case "submitcheck":
			nagioscorepassivecheck_submit_check_data();
			break;
		// something else we don't handle...
		default:
			break;
		}
	}

function nagioscorepassivecheck_submit_check_data(){
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
		$tmpname=tempnam($cfg["check_results_dir"],"c");
		$fh=fopen($tmpname,"w");
		
		fprintf($fh,"### NRDP Check ###\n");
		fprintf($fh,"start_time=%d.0\n",time());
		fprintf($fh,"# Time: %s\n",date('r'));
		fprintf($fh,"host_name=%s\n",$hostname);
		if($type=="service")
			fprintf($fh,"service_description=%s\n",$servicename);
		fprintf($fh,"check_type=1\n"); // 0 for active, 1 for passive
		fprintf($fh,"early_timeout=1\n");
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
	
	exit();
	}


	

?>