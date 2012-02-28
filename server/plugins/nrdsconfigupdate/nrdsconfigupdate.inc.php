<?php
//
// Nagios Core NRDS CONFIG UPDATER NRDP Plugin
// Written by: Scott Wilkerson (nagios@nagios.org)
// Copyright (c) 2012 Nagios Enterprises, LLC.
// License: Nagios Open Software License <http://www.nagios.com/legal/licenses>
//

require_once(dirname(__FILE__).'/../../config.inc.php');
require_once(dirname(__FILE__).'/../../includes/utils.inc.php');

global $cfg;
$cfg["config_dir"]="/usr/local/nrdp/configs";
$cfg["plugin_dir"]="/usr/local/nagios/libexec";

register_callback(CALLBACK_PROCESS_REQUEST,'nrdsconfigupdate_process_request');

function nrdsconfigupdate_process_request($cbtype,$args){

    $cmd=grab_array_var($args,"cmd");
    
    //echo "CMD=$cmd<BR>";
    
    switch($cmd){
        // check data
        case "updatenrds":
            nrds_config_update_check();
            break;
        case "getconfig":
            nrds_get_config();
            break;
        case "getplugin":
            nrds_get_plugin();
            break;
        case "nrdsgetclient":
            nrds_get_client_install();
            break;
           
        // something else we don't handle...
        default:
            break;
        }
    }

function nrds_get_client_install(){
    global $cfg;
    global $request;
    $configname=grab_request_var("configname");
   
    $config_file=$cfg["config_dir"]."/".$configname.".cfg";
    $nrds_config_tmp_dir=$cfg["tmp_dir"]."/nrdsconfig";
    $client_install_file="$nrds_config_tmp_dir/$configname.tar.gz";
    $script = "mkdir -p $nrds_config_tmp_dir;cp -fr /usr/local/nrdp/clients $nrds_config_tmp_dir;chmod +x $nrds_config_tmp_dir/clients/installnrds;cp -f $config_file $nrds_config_tmp_dir/clients/nrds/nrds.cfg;cd $nrds_config_tmp_dir; tar czfp $nrds_config_tmp_dir/$configname.tar.gz clients;rm -rf $nrds_config_tmp_dir/clients;";
    $rslt = system($script,$retval);
    if (file_exists($client_install_file)) {
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"$configname.tar.gz\"");
        header("Content-Length: ".filesize($client_install_file));
        passthru("cat $client_install_file",$err);
        unlink($client_install_file);
        rmdir($nrds_config_tmp_dir);
        exit();
    } else {
        header("HTTP/1.0 404 Not Found");
        exit;
    }
}


//this function grabs the config for delivery
function nrds_get_config(){
    global $cfg;
    global $request;
    $configname=grab_request_var("configname");
    $config=$cfg["config_dir"]."/".$configname.".cfg";
    if (file_exists($config)) {
        //readfile($config);
        passthru("cat $config",$err);
        exit;
    } else {
        header("HTTP/1.0 404 Not Found");
        exit;
    }    
}
function nrds_get_plugin(){
    global $cfg;
    global $request;
    $plugin_var=grab_request_var("plugin");
    $plugin=$cfg["plugin_dir"]."/".$plugin_var;
    if (file_exists($plugin)) {
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"$plugin_var\"");
        header("Content-Length: ".filesize($plugin));
        passthru("cat $plugin",$err);
        exit();
    } else {
        header("HTTP/1.0 404 Not Found");
        exit;
    }

}
function nrds_config_update_check(){
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

        
    // process each result
    foreach($xml->config as $cr){
        // common elements
        $configname=strval($cr->name);
        $version=doubleval($cr->version);
    }
    // setup defaults
    $status=0;
    $message="OK";
    $output="";
    // check config logic
    if (!is_dir($cfg["config_dir"])) mkdir($cfg["config_dir"],755,true);
    if (!file_exists($cfg["config_dir"]."/".$configname.".cfg")){
        $status=2;
        $message="We do not have that configuration";
    } else {
        $handle = @fopen($cfg["config_dir"]."/".$configname.".cfg", "r");
        if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {
                if (substr($buffer,0,14) == "CONFIG_VERSION") {
                    $version_line = explode("=",$buffer);
                    $api_version = $version_line[1];
                    break;
                }
            }
            fclose($handle);
        }
    }
    
    if ($version< $api_version) {
        $status=1;
        $message="Version ".$api_version." available";
    }
    
    
    output_api_header();
    
    echo "<result>\n";
    echo "  <status>".$status."</status>\n";
    echo "  <message>".$message."</message>\n";
    echo "    <meta>\n";
    echo "       <output>".$output."</output>\n";
    echo "    </meta>\n";
    echo "</result>\n";
    
    exit();
    }


    

?>