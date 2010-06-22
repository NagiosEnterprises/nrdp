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
	global $request;
	
	$cmd="";
	if(isset($request["cmd"]))
		$cmd=strtolower($request["cmd"]);
		
	switch($cmd){
	
		case "getinstances":
			get_instances();
			break;
		// raw nagios external commands
		case "submitrawcmd":
			submit_nagios_command(true);
			break;
		// normal nagios external commands
		case "submitcmd":
			submit_nagios_command(false);
			break;
		// bulk nagios commands
		case "submitbulkcmd":
			submit_bulk_nagios_command();
			break;
		// main config
		case "getmainconfig":
			get_main_config();
			break;
		// status information
		case "getprogramstatus":
			get_instance_programstatus();
			break;
		default:
			echo say_hello();
			break;
		}

	exit();
	}


function read_nagios_config_file($instance){
	global $cfg;
	
	if(!isset($cfg["nagios_instances"][$instance]))
		return false;
		
	$instance_array=$cfg["nagios_instances"][$instance];
	if(!array_key_exists("main_config_file",$instance_array))
		return false;
		
	$main_config_file=$instance_array["main_config_file"];

	if(!file_exists($main_config_file))
		return false;
	if(!is_readable($main_config_file))
		return false;

	// open file
	if(($handle=@fopen($main_config_file,"r"))===false)
		return false;
		
	$contents=array();
	$buffer="";
	$multilines=true;
	if(array_key_exists("multiline_support",$instance_array) && $instance_array["multiline_support"]===false)
		$multilines=false;
	$line=0;
	while(!feof($handle)){
		$line++;
	
		$newbuf=fgets($handle,8192);
		$buffer.=$newbuf;
		
		//echo "BUF=".$buffer."<BR>\n";
		
		// trim in-line comments
		$pos=strpos($buffer,";");
		if($pos!==false){
			$buffer=substr($buffer,0,$pos-1);
			}
		
		$buffer=trim($buffer);
		
		// skip comments and blank lines
		if($buffer=="" || $buffer[0]=="#" || $buffer[0]=="\n"){
			$buffer="";
			continue;
			}
			
		// check for line continuation
		if($multilines==true){
			$len=strlen($buffer);
			//echo "LEN=".$len.", BUF=".$buffer."<BR>\n";
			if(($len==2 && $buffer[$len-2]=='\\') || ($len>2 && $buffer[$len-2]=='\\' && $buffer[$len-3]!='\\')){
				$buffer=substr($buffer,0,$len-2);
				//echo "CONTINUING...<BR>\n";
				continue;
				}
			}
			
		$pos=strpos($buffer,"=");
		$var=trim(substr($buffer,0,$pos));
		$val=trim(substr($buffer,$pos+1));
		
		if($var==""){
			$buffer="";
			continue;
			}

		if(!isset($contents[$var]))
			$contents[$var]=array();
		$contents[$var][]=$val;
		
		$buffer="";
		
		//echo $buffer;
		}
		
	fclose($handle);
	
	//echo "MAIN CONFIG=".$main_config_file."<BR>\n";
	//print_r($contents);
	
	return $contents;
	}
	
function get_main_config(){
	global $cfg;
	global $request;
	
	$raw=false;
	//if(isset())
	
	// make sure this capability is enabled
	if(!capability_enabled("read_main_config"))
		handle_api_error(ERROR_CAPABILITY_NOT_ENABLED);

	// make sure we have a valid instance
	if(!isset($request["instance"]))
		handle_api_error(ERROR_NOT_INSTANCE);
	$instance=$request["instance"];
	if(!array_key_exists($instance,$cfg["nagios_instances"]))
		handle_api_error(ERROR_BAD_INSTANCE);

	$instance_array=$cfg["nagios_instances"][$instance];
		
	$result=read_nagios_config_file($instance);
	if($result===false)
		handle_api_error(ERROR_READ_MAIN_CONFIG);
		
	output_api_header();
	
	echo "<configfile name='".xmlentities($instance_array["main_config_file"])."'>\n";
	foreach($result as $var => $valarr){
		echo "  <variable name='".xmlentities($var)."'>\n";
		foreach($valarr as $val)
			echo "    <value>".xmlentities($val)."</value>\n";
		echo "  </variable>\n";
		}
	echo "</configfile>\n";
	}
		

function get_instance_programstatus(){
	global $cfg;
	global $request;
	
	// make sure we have a valid instance
	if(!isset($request["instance"]))
		handle_api_error(ERROR_NOT_INSTANCE);
	$instance=$request["instance"];
	if(!array_key_exists($instance,$cfg["nagios_instances"]))
		handle_api_error(ERROR_BAD_INSTANCE);

	$result=read_nagios_config_file($instance);
	
	$status_file="/usr/local/nagios/var/status.dat";
	if(isset($result["status_file"])){
		$status_file_arr=$result["status_file"];
		$status_file=$status_file_arr[0];
		}
	
	//echo $status_file;
	//print_r($result);
	
	if(!file_exists($status_file))
		handle_api_error(ERROR_BAD_STATUS_FILE);
	if(!is_readable($status_file))
		handle_api_error(ERROR_READ_STATUS_FILE);

	// open file
	if(($handle=@fopen($status_file,"r"))===false)
		handle_api_error(ERROR_READ_STATUS_FILE);

	output_api_header();
	echo "<programstatusinfo file='".xmlentities($status_file)."' instance='".xmlentities($instance)."'>\n";

	$buffer="";
	$line=0;
	$in_info=false;
	$in_programstatus=false;
	while(!feof($handle)){
		$line++;
		
		$buffer="";

		$newbuf=fgets($handle,8192);
		$buffer.=$newbuf;
		
		//echo "BUF=".$buffer."<BR>\n";
		
		// trim in-line comments
		$pos=strpos($buffer,";");
		if($pos!==false){
			$buffer=substr($buffer,0,$pos-1);
			}
		
		//echo "RAWBUF='".$buffer."' ".strlen($buffer)."<BR>\n";
		$buffer=trim($buffer);
		//echo "TRIMBUF='".$buffer."' ".strlen($buffer)."<BR>\n";
		
		// skip comments and blank lines
		if($buffer=="" || $buffer[0]=="#" || $buffer[0]=="\n"){
			$buffer="";
			continue;
			}

		$pos=strpos($buffer,"=");
		$var=trim(substr($buffer,0,$pos));
		$val=trim(substr($buffer,$pos+1));
		
		//echo "BUF=".$buffer."<BR>\n";

		if(strpos($buffer,"info {")===0){
			echo "  <info>\n";
			$in_info=true;
			continue;
			}
		if(strpos($buffer,"}")===0 && $in_info==true){
			echo "  </info>\n";
			$in_info=false;
			continue;
			}

		if(strpos($buffer,"programstatus {")===0){
			echo "  <programstatus>\n";
			$in_programstatus=true;
			continue;
			}
		if(strpos($buffer,"}")===0 && $in_programstatus==true){
			echo "  </programstatus>\n";
			$in_programstatus=false;
			break;
			}
			
		if($in_programstatus==true || $in_info==true){
			//echo "BUF='".$buffer."' ".strlen($buffer)."<BR>\n";
			
			echo "    <".$var.">".xmlentities($val)."</".$var.">\n";
			}
		
		}
		
	echo "</programstatusinfo>\n";

	fclose($handle);
	}

	
function submit_bulk_nagios_command(){
	global $cfg;
	global $request;
	
	$raw=false;
	//if(isset())
	
	// make sure this capability is enabled
	if(!capability_enabled("bulk_nagios_command"))
		handle_api_error(ERROR_CAPABILITY_NOT_ENABLED);

	// make sure we have a command
	if(!isset($request["command"]) || $request["command"]=="")
		handle_api_error(ERROR_NO_COMMAND);

	// make sure we have a valid instance
	if(!isset($request["instance"]))
		handle_api_error(ERROR_NOT_INSTANCE);
	$instance=$request["instance"];
	if(!array_key_exists($instance,$cfg["nagios_instances"]))
		handle_api_error(ERROR_BAD_INSTANCE);
		
	$instance_array=$cfg["nagios_instances"][$instance];
		
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
		
	// open tmp file
	if(isset($instance_array["tmp_dir"]))
		$tmpdir=$instance_array["tmp_dir"];
	else
		$tmpdir="/tmp";
	//echo "TMPDIR=".$tmpdir."<BR>\n";
	$tmpfname=tempnam($tmpdir,"DCM");
	
	//echo "TMPFILE=".$tmpfname."<BR>\n";

	// open tmp file for writing bulk commands
	if(($tmphandle=@fopen($tmpfname,"w+"))===false)
		handle_api_error(ERROR_TEMP_FILE_OPEN);
		
	// get current time
	$ts=time();
		
	// write the external command(s) to scratch file
	$error=false;
	if(!is_array($request["command"])){
		if($raw==false)
			fwrite($tmphandle,"[".$ts."] ");
		$result=fwrite($tmphandle,$request["command"]."\n");
		//echo "WROTE: ".$request["command"]."<BR>\n";
		}
	else{
		foreach($request["command"] as $cmd){
			if($raw==false)
				fwrite($tmphandle,"[".$ts."] ");
			$result=fwrite($tmphandle,$cmd."\n");
			//echo "WROTE: ".$cmd."<BR>\n";
			if($result===false)
				break;
			}
		}
		
	// close temporary file
	fclose($tmphandle);
	// allow group rw permissions
	chmod($tmpfname,0660);
	// change group ownership so Nagios can read/delete temp file
	if(isset($cfg["nagios_command_group"]))
		chgrp($tmpfname,$cfg["nagios_command_group"]);

	// write an external command to process the bulk file
	if($result!==false)
		fwrite($handle,"[".$ts."] PROCESS_FILE;".$tmpfname.";1\n");

	// close the external command file
	fclose($handle);

	if($result===false)
		handle_api_error(ERROR_BAD_WRITE);
	
	output_api_header();
	
	echo "<result>\n";
	echo "  <status>0</status>\n";
	echo "  <message>OK</message>\n";
	echo "</result>\n";
	}


function submit_nagios_command($raw=false){
	global $cfg;
	global $request;
	
	// make sure this capability is enabled
	if($raw==true){
		if(!capability_enabled("raw_nagios_command"))
			handle_api_error(ERROR_CAPABILITY_NOT_ENABLED);
		}
	else{
		if(!capability_enabled("normal_nagios_command"))
			handle_api_error(ERROR_CAPABILITY_NOT_ENABLED);
		}

	// make sure we have a command
	if(!isset($request["command"]) || $request["command"]=="")
		handle_api_error(ERROR_NO_COMMAND);

	// make sure we have a valid instance
	if(!isset($request["instance"]))
		handle_api_error(ERROR_NOT_INSTANCE);
	$instance=$request["instance"];
	if(!array_key_exists($instance,$cfg["nagios_instances"]))
		handle_api_error(ERROR_BAD_INSTANCE);
		
	$instance_array=$cfg["nagios_instances"][$instance];
		
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
	if(!is_array($request["command"])){
		if($raw==false)
			fwrite($handle,"[".$ts."] ");
		$result=fwrite($handle,$request["command"]."\n");
		//echo "WROTE: ".$request["command"]."<BR>\n";
		}
	else{
		foreach($request["command"] as $cmd){
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
	echo "  <capabilities>\n";
	
	foreach($cfg["capabilities"] as $cname => $cval){
		echo "    <capability name='".$cname."'>".$cval."</capability>\n";
		}
	
	echo "  </capabilities>\n";
	echo "  <instances>\n";
	
	foreach($cfg['nagios_instances'] as $instance_name => $instance_vars){
		echo "    <instance>".$instance_name."</instance>\n";
		}
		
	echo "  </instances>\n";
	echo "</response>\n";
	}


function get_instances(){
	global $cfg;
	
	output_api_header();

	echo "<instances>\n";
	
	foreach($cfg['nagios_instances'] as $instance_name => $instance_vars){
		echo "  <instance name='".$instance_name."'>\n";
		echo "    <version>".$instance_vars['nagios_version']."</version>\n";
		echo "  </instance>\n";
		}
		
	echo "</instances>\n";
	}
	

	

?>