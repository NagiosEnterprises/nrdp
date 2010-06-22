<?php
// Nagios DCM Config File
// Copyright (c) 2008 Nagios Enterprises, LLC.  All rights reserved.
//  
// $Id: config.inc.php 12 2010-06-19 04:19:35Z egalstad $


// an array of one or more tokens that are valid for this DCM install
// a client request must contain a valid token in order for the DCM to response or honor the request
$cfg['authorized_tokens'] = array(
	"test",
	"welcome"
	);
	
// do we require that HTTPS be used to access DCM?
// set this value to 'false' to disable HTTPS requirement (not recommended)
$cfg["require_https"]=false;

// do we require that basic authentication be used to access DCM?
// set this value to 'false' to disable basic auth requirement (not recommended)
$cfg["require_basic_auth"]=false;

// what basic authentication users are allowed to access DCM?
// comment this variable out to allow all authenticated users access to the DCM
$cfg["valid_basic_auth_users"]=array(
	"dcmuser"
	);
	
// the name of the system group that has write permissions to the external command file
// this group is also used to set file permissions when writing bulk commands or passive check results
// NOTE: both the Apache and Nagios users must be a member of this group
$cfg["nagios_command_group"]="nagcmd";

// define your Nagios instances here
// all Nagios instances on this machine you want to control should be included here
$cfg['nagios_instances']=array(

	// first Nagios instance
	"dev1-nag3" => array(
	
		// nagios version 
		"nagios_version" => 3,
		//"naigos_version" => 2
		
		// are multiple line continuations allows in config files?  Nagios 3 = true, Nagios 2 = false
		"multiline_support" => true,
	
		// full path to main config file
		"main_config_file" => "/usr/local/nagios/etc/nagios.cfg",

		// full path to Nagios external command file
		"command_file" => "/usr/local/nagios/var/rw/nagios.cmd",

		// full path to check results spool directory
		"check_results_dir" => "/usr/local/nagios/var/spool/checkresults",

		// full path to status file
		"status_file" => "/usr/local/nagios/var/status.dat",

		// full path to directory where temp scratch files can be written
		// NOTE: the Apache user need to be able create files here, and the Nagios user needs to read/delete those same files, so the /tmp system directory won't work (it has a sticky bit on it)
		"tmp_dir" => "/usr/local/nagios/var/tmp",

		// url to Nagios web interface
		"nagios_url" => "http://dev1/nagios/",
		),
		
	// second Nagios instance if you have one
	"dev1-nag2" => array(
		"nagios_version" => 2,
		"nagios_bin" => "/usr/local/nagios-2x/bin/nagios",
		"command_file" => "/usr/local/nagios-2x/var/rw/nagios.cmd",
		"status_file" => "/usr/local/nagios-2x/var/status.dat",
		"nagios_url" => "http://dev1/nagios-2x/",
		)
		
	// and so on...
	);
	

// capabilities this DCM install will support/honor
// you can disable particluar capabilities by setting them to zero (0)
$cfg["capabilities"]=array(
	"raw_nagios_command" => 1,	// can clients submit raw external commands to Nagios?  this allows timestamps to be faxed
	"normal_nagios_command" => 1,	// can clients submit normal external commands to Nagios? timestamps are auto-calculated
	"bulk_nagios_command" => 1,
	
	"read_main_config" => 1,
	
	"soft_process_control" => 1,
	"hard_process_control" => 0,
	
	"check_result_spool_dump" => 1,
	"passive_check_handler" => 1,
	"passive_check_masquerading" => 0,
	);
	

///////// DONT MODIFY ANYTHING BELOW THIS LINE /////////

$cfg['product_name']='nagiosdcm';
$cfg['product_version']='1.0'


?>