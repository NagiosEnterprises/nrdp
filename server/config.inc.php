<?php
// NRDP Config File
// Copyright (c) 2010 Nagios Enterprises, LLC.
// License: Nagios Open Software License <http://www.nagios.com/legal/licenses>
//  
// $Id: config.inc.php 12 2010-06-19 04:19:35Z egalstad $


// an array of one or more tokens that are valid for this NRDP install
// a client request must contain a valid token in order for the NRDP to response or honor the request
// NOTE: tokens are just alphanumeric strings - make them hard to guess!
$cfg['authorized_tokens'] = array(
	//"mysecrettoken",  // <-- not a good token
	//"90dfs7jwn3",   // <-- a better token (don't use this exact one, make your own)
	);
	
// do we require that HTTPS be used to access NRDP?
// set this value to 'false' to disable HTTPS requirement
$cfg["require_https"]=false;

// do we require that basic authentication be used to access NRDP?
// set this value to 'false' to disable basic auth requirement 
$cfg["require_basic_auth"]=false;

// what basic authentication users are allowed to access NRDP?
// comment this variable out to allow all authenticated users access to the NRDP
$cfg["valid_basic_auth_users"]=array(
	"nrdpuser"
	);
	
// the name of the system group that has write permissions to the external command file
// this group is also used to set file permissions when writing bulk commands or passive check results
// NOTE: both the Apache and Nagios users must be a member of this group
$cfg["nagios_command_group"]="nagcmd";

// full path to Nagios external command file
$cfg["command_file"]="/usr/local/nagios/var/rw/nagios.cmd";

// full path to check results spool directory
$cfg["check_results_dir"]="/usr/local/nagios/var/spool/checkresults";

// full path to directory where temp scratch files can be written
// NOTE: the Apache user need to be able create files here, and the Nagios user needs to read/delete those same files, so the /tmp system directory won't work (it has a sticky bit on it)
$cfg["tmp_dir"]="/usr/local/nagios/var/tmp";

	
///////// DONT MODIFY ANYTHING BELOW THIS LINE /////////

$cfg['product_name']='nrdp';
$cfg['product_version']='1.2'


?>