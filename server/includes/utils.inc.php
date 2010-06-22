<?php
//
// Nagios DCM Utils
// Copyright (c) 2008 Nagios Enterprises, LLC.  All rights reserved.
//
// $Id: utils.inc.php 12 2010-06-19 04:19:35Z egalstad $

require_once("constants.inc.php");


add_capability("direct_nagios_command",1);
add_capability("bulk_nagios_command",1);
add_capability("soft_process_control",1);
add_capability("hard_process_control",1);
add_capability("check_result_spool_dump",1);
add_capability("passive_check_handler",1);
add_capability("passive_check_masquerading",1);
	
////////////////////////////////////////////////////////////////////////
// REQUEST FUNCTIONS
////////////////////////////////////////////////////////////////////////

// grabs POST and GET variables
function grab_request_vars($preprocess=true,$type=""){
	global $request;

	// do we need to strip slashes?
	$strip=false;
	if((function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) || (ini_get('magic_quotes_sybase') && (strtolower(ini_get('magic_quotes_sybase'))!= "off")))
		$strip=true;
		
	$request=array();

	if($type=="" || $type=="get"){
		foreach ($_GET as $var => $val){
			$request[$var]=$val;
//			echo "GET: $var = $val<BR>\n";
			}
		}
	if($type=="" || $type=="post"){
		foreach ($_POST as $var => $val){
			$request[$var]=$val;
//			echo "POST: $var = $val<BR>\n";
			}
		}
		
	// strip slashes - we escape them later in sql queries
	if($strip==true){
		foreach($request as $var => $val)
			$request[$var]=stripslashes($val);
		}
		
	}

////////////////////////////////////////////////////////////////////////
// OUTPUT FUNCTIONS
////////////////////////////////////////////////////////////////////////

function have_value($var){
	if(!isset($var))
		return false;
	if(is_null($var))
		return false;
	if(empty($var))
		return false;
	return true;
	}
	

// generate output header
function output_api_header(){
	global $request;

	// we usually output XML, except if debugging
	if(isset($request['debug'])){
		if($request['debug']=='text')
			header("Content-type: text/plain");
		else
			header("Content-type: text/html");
		}
	else{
		header("Content-type: text/xml");
		echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		}
	}	

	
////////////////////////////////////////////////////////////////////////
// MISC FUNCTIONS
////////////////////////////////////////////////////////////////////////

function xmlentities($uncleaned){

	$search=array(
		"<",
		">",
		"&",
		"\"",
		"'",
		"’"
		);
	$replace=array(
		"&lt;",
		"&gt;",
		"&amp;",
		"&quot;",
		"&apos;",
		"&apos;"
		);
	
	$cleaned=str_replace($search,$replace,$uncleaned);
	
	return $cleaned;
	}

	
////////////////////////////////////////////////////////////////////////
// ERROR HANDLING FUNCTIONS
////////////////////////////////////////////////////////////////////////

// just returns an XML error string and exits execution
function handle_api_error($msg){

	output_api_header();

	echo "<result>\n";
	echo "  <status>-1</status>\n";
	echo "  <message>".xmlentities($msg)."</message>\n";
	echo "</result>\n";

	exit();
	}



/////////////////////////////////////////////////
// AUTHENTICATION/AUTHORIZATION FUNCTIONS
/////////////////////////////////////////////////

function check_auth(){
	global $cfg;
	global $request;

	// HTTPS is required
	if(!isset($cfg["require_https"]) || $cfg["require_https"]!==false){
		if(!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS']=="")
			handle_api_error(ERROR_HTTPS_REQUIRED);
		}
	
	// require basic authentication
	if(!isset($cfg["require_basic_auth"]) || $cfg["require_basic_auth"]!==false){
		if(!isset($_SERVER['REMOTE_USER']) || $_SERVER['REMOTE_USER']=="")
			handle_api_error(ERROR_NOT_AUTHENTICATED);
		if(isset($cfg["valid_basic_auth_users"])){
			if(!in_array($_SERVER['REMOTE_USER'],$cfg["valid_basic_auth_users"]))
				handle_api_error(ERROR_BAD_USER);
			}
		}
	
	// user must supply a token
	$user_token="";
	if(isset($request["token"]) && have_value($request["token"]))
		$user_token=$request["token"];
	else
		handle_api_error(ERROR_NO_TOKEN_SUPPLIED);
		
	// no valid tokens are configured
	if(!isset($cfg["authorized_tokens"]))
		handle_api_error(ERROR_NO_TOKENS_DEFINED);
		
	// token must be valid
	if(!in_array($user_token,$cfg["authorized_tokens"]))
		handle_api_error(ERROR_BAD_TOKEN_SUPPLIED);
	}
	
	
/////////////////////////////////////////////////
// UTILITIES
/////////////////////////////////////////////////

function get_product_name(){
	global $cfg;
	return $cfg['product_name'];
	}

function get_product_version(){
	global $cfg;
	return $cfg['product_version'];
	}


/////////////////////////////////////////////////
// CAPABILITIES
/////////////////////////////////////////////////

function add_capability($cname,$cval=""){
	global $cfg;
	
	if(!isset($cfg["capabilities"]))
		$cfg["capabilities"]=array();
		
	// don't override user-specified values
	if(!isset($cfg["capabilities"][$cname]))
		$cfg["capabilities"][$cname]=$cval;
	}

function capability_enabled($cname){
	global $cfg;
	
	if(!isset($cfg["capabilities"]))
		return false;
		
	if(!isset($cfg["capabilities"][$cname]))
		return false;
		
	if($cfg["capabilities"][$cname]===1)
		return true;
		
	return false;
	}

?>