#!/usr/bin/php -q
<?
// send_nrdp.php
//
// Copyright (c) 2010-2011 Nagios Enterprises, LLC.
// Portions Copyright (c) others - see source code below.
/* License:

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
    * Neither the name of the Nagios Enterprises, LLC nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/  

doit();

// initial values
$url="";
$token="";
$host="";
$service="";
$state="";
$output="";
$type="host";
$checktype=1; // passive check
$delim="\t";  // stdin delimiter

	
function doit(){
	global $argv;
	global $url,$token,$host,$service,$state,$output,$type,$checktype,$usestdin;
	global $delim;
	
	$type="host";
	
	// get and check command line args
	check_args(parse_argv($argv));
	
	/*
	echo "URL=$url\n";
	echo "TOKEN=$token\n";
	echo "TYPE=$type\n";
	echo "CHECKTYPE=$checktype\n";
	echo "HOST=$host\n";
	echo "SERVICE=$service\n";
	echo "STATE=$state\n";
	echo "OUTPUT=".$output."\n";
	*/
	
	$hostchecks=array();
	$servicechecks=array();
	
	// process single check from command line
	if($host!=""){
		// service check
		if($service!=""){
			$newc=array(
				"hostname" => $host,
				"servicename" => $service,
				"state" => $state,
				"output" => $output,
				);
			$servicechecks[]=$newc;
			}
		// host check
		else{
			$newc=array(
				"hostname" => $host,
				"state" => $state,
				"output" => $output,
				);
			$hostchecks[]=$newc;
			}
		}
		
	// use read from stdin
	if($usestdin!=""){
		//echo "READING FROM STDIN\n";
		
		while($buf=rtrim(fgets(STDIN),"\n")){
			//echo "READ: $buf\n";
			$parts=explode("\t",$buf);
			//print_r($parts);
			$fields=count($parts);
			
			// host check
			if($fields==3){
				$hostname=$parts[0];
				$state=$parts[1];
				$output=$parts[2];
				
				$newc=array(
					"hostname" => $hostname,
					"state" => $state,
					"output" => $output,
					);
				$hostchecks[]=$newc;
				}
			// service check
			else if($fields==4){
			
				$hostname=$parts[0];
				$servicename=$parts[1];
				$state=$parts[2];
				$output=$parts[3];
				
				$newc=array(
					"hostname" => $hostname,
					"servicename" => $servicename,
					"state" => $state,
					"output" => $output,
					);
				$servicechecks[]=$newc;
				}
			}
		}
	
	// craft the XML to send
	$checkresultopts="";
	$checkresultopts=" checktype='".$checktype."'";
$xml=
"<?xml version='1.0'?> 
<checkresults>
";
	foreach($hostchecks as $hc){
	
		$hostname=$hc["hostname"];
		$state=$hc["state"];
		$output=$hc["output"];
		
		$xml.="
	<checkresult type='host' ".$checkresultopts.">
		<hostname>".htmlentities($hostname)."</hostname>
		<state>".$state."</state>
		<output>".htmlentities($output)."</output>
	</checkresult>
		";
		}
	foreach($servicechecks as $sc){
	
		$hostname=$sc["hostname"];
		$servicename=$sc["servicename"];
		$state=$sc["state"];
		$output=$sc["output"];
		
		$xml.="
	<checkresult type='service' ".$checkresultopts.">
		<hostname>".htmlentities($hostname)."</hostname>
		<servicename>".htmlentities($servicename)."</servicename>
		<state>".$state."</state>
		<output>".htmlentities($output)."</output>
	</checkresult>
		";
		}
$xml.="
</checkresults>
";

	//echo "XML=\n$xml\n";
	//exit();
	
	// build url
	$theurl=$url."/?token=".$token."&cmd=submitcheck&XMLDATA=".urlencode($xml);
	
	//echo "URL=$theurl\n";
	
	// send data to NRDP
	$opts=array(
		"method" => "post",
		"timeout" => 30,
		"return_info" => true,
		);
	$result=load_url($theurl,$opts);
	//echo "RESULT=\n";
	//print_r($result);
	
	exit(0);
	}
	
	
function check_args($args){
	global $argv;
	global $url,$token,$host,$service,$state,$output,$type,$checktype,$usestdin;
	
	$error=false;
	
	//print_r($args);
	
	// get values
	$url=grab_array_var($args,"url");
	$token=grab_array_var($args,"token");
	$checktype=grab_array_var($args,"checktype",1);
	$host=grab_array_var($args,"host");
	$service=grab_array_var($args,"service");
	$state=grab_array_var($args,"state");
	$output=html_entity_decode(grab_array_var($args,"output"),ENT_QUOTES);
	$usestdin=grab_array_var($args,"usestdin");
	//$output=grab_array_var($args,"output");
	
	//echo "OUTPUT1=".$output."\n";
	
	if($service!="")
		$type="service";
	
	// make sure we have required vars
	if($url=="" || $token=="" || (($usestdin=="")&& ($host=="" || $state=="" || $output=="")))
		$error=true;
	
	if($error){
		echo "send_nrdp - NRDP Host and Service Check Client\n";
		echo "Copyright (c) 2010-2011 Nagios Enterprises, LLC\n";
		echo "Portions Copyright (c) others - see source code\n";
		echo "License: BSD\n";
		echo "\n";
		echo "Usage: ".$argv[0]." --url=<url> --token=<token> --host=<hostname> [--service=<servicename>] --state=<state> --output=<output>\n";
		echo "\n";
		echo "   <url>	   = The URL used to access the remote NRDP agent.\n";
		echo "   <token>       = The secret token used to access the remote NRDP agent.\n";
		echo "   <hostname>    = The name of the host associated with the passive host/service check result.\n";
		echo "   <servicename> = For service checks, the name of the service associated with the passive check result.\n";
		echo "   <state>       = An integer indicating the current state of the host or service.\n";
		echo "   <output>      = Text output to be sent as the passive check result.  Newlines should be encoded with encoded newlines (\\n).\n";
		echo "\n";
		echo "Send a passive host or service check result to a remote Nagios instance using the NRDP agent.\n";
		exit(1);
		}

	}
	
	
// gets value from array using default
function grab_array_var($arr,$varname,$default=""){
	global $request;
	
	$v=$default;
	if(is_array($arr)){
		if(array_key_exists($varname,$arr))
			$v=$arr[$varname];
		}
	return $v;
	}
	
	
function parse_argv($argv){

    array_shift($argv);
    $out=array();
	
    foreach($argv as $arg){
	
        if(substr($arg,0,2)=='--'){
			$eq=strpos($arg,'=');
            if($eq===false){
                $key=substr($arg,2);
                $out[$key]=isset($out[$key])?$out[$key]:true;
				} 
			else{
                $key=substr($arg,2,$eq-2);
                $out[$key]=substr($arg,$eq+1);
				}
			} 
			
		else if(substr($arg,0,1)=='-'){
            if(substr($arg,2,1)=='='){
                $key=substr($arg,1,1);
                $out[$key]=substr($arg,3);
				}
			else{
                $chars=str_split(substr($arg,1));
                foreach($chars as $char){
                    $key=$char;
                    $out[$key]=isset($out[$key])?$out[$key]:true;
					}
				}
			} 
		else{
            $out[] = $arg;
			}
		}
		
    return $out;
	}
	
	

/**
 * See http://www.bin-co.com/php/scripts/load/
 * Version : 1.00.A
 * License: BSD
 */
/* renamed to load_url */
function load_url($url,$options=array('method'=>'get','return_info'=>false)) {

	// added 04-28-08 EG added a default timeout of 15 seconds
	if(!isset($options['timeout']))
		$options['timeout']=15;

    $url_parts = parse_url($url);

    $info = array(//Currently only supported by curl.
        'http_code'    => 200
    );
    $response = '';
    
    $send_header = array(
        'Accept' => 'text/*',
        'User-Agent' => 'BinGet/1.00.A (http://www.bin-co.com/php/scripts/load/)'
    );

    ///////////////////////////// Curl /////////////////////////////////////
    //If curl is available, use curl to get the data.
    if(function_exists("curl_init") 
                and (!(isset($options['use']) and $options['use'] == 'fsocketopen'))) { //Don't user curl if it is specifically stated to user fsocketopen in the options
        if(isset($options['method']) and $options['method'] == 'post') {
            $page = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];
        } else {
            $page = $url;
        }

        $ch = curl_init($url_parts['host']);

	// added 04-28-08 EG set a timeout
	if(isset($options['timeout']))
		curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout']);
			
        curl_setopt($ch, CURLOPT_URL, $page);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //Just return the data - not print the whole thing.
        curl_setopt($ch, CURLOPT_HEADER, true); //We need the headers
        curl_setopt($ch, CURLOPT_NOBODY, false); //The content - if true, will not download the contents
        if(isset($options['method']) and $options['method'] == 'post' and $url_parts['query']) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $url_parts['query']);
        }
        //Set the headers our spiders sends
        curl_setopt($ch, CURLOPT_USERAGENT, $send_header['User-Agent']); //The Name of the UserAgent we will be using ;)
        $custom_headers = array("Accept: " . $send_header['Accept'] );
        if(isset($options['modified_since']))
            array_push($custom_headers,"If-Modified-Since: ".gmdate('D, d M Y H:i:s \G\M\T',strtotime($options['modified_since'])));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $custom_headers);

        curl_setopt($ch, CURLOPT_COOKIEJAR, "cookie.txt"); //If ever needed...
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        if(isset($url_parts['user']) and isset($url_parts['pass'])) {
            $custom_headers = array("Authorization: Basic ".base64_encode($url_parts['user'].':'.$url_parts['pass']));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $custom_headers);
        }

        $response = curl_exec($ch);
        $info = curl_getinfo($ch); //Some information on the fetch
        curl_close($ch);

    //////////////////////////////////////////// FSockOpen //////////////////////////////
    } else { //If there is no curl, use fsocketopen
        if(isset($url_parts['query'])) {
            if(isset($options['method']) and $options['method'] == 'post')
                $page = $url_parts['path'];
            else
                $page = $url_parts['path'] . '?' . $url_parts['query'];
        } else {
            $page = $url_parts['path'];
        }

        $fp = fsockopen($url_parts['host'], 80, $errno, $errstr, 30);
        if ($fp) {
	
		// added 04-28-08 EG set a timeout
		if(isset($options['timeout']))
			stream_set_timeout($fp,$options['timeout']);
			
            $out = '';
            if(isset($options['method']) and $options['method'] == 'post' and isset($url_parts['query'])) {
                $out .= "POST $page HTTP/1.1\r\n";
            } else {
                $out .= "GET $page HTTP/1.0\r\n"; //HTTP/1.0 is much easier to handle than HTTP/1.1
            }
            $out .= "Host: $url_parts[host]\r\n";
            $out .= "Accept: $send_header[Accept]\r\n";
            $out .= "User-Agent: {$send_header['User-Agent']}\r\n";
            if(isset($options['modified_since']))
                $out .= "If-Modified-Since: ".gmdate('D, d M Y H:i:s \G\M\T',strtotime($options['modified_since'])) ."\r\n";

            $out .= "Connection: Close\r\n";
            
            //HTTP Basic Authorization support
            if(isset($url_parts['user']) and isset($url_parts['pass'])) {
                $out .= "Authorization: Basic ".base64_encode($url_parts['user'].':'.$url_parts['pass']) . "\r\n";
            }

            //If the request is post - pass the data in a special way.
            if(isset($options['method']) and $options['method'] == 'post' and $url_parts['query']) {
                $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
                $out .= 'Content-Length: ' . strlen($url_parts['query']) . "\r\n";
                $out .= "\r\n" . $url_parts['query'];
            }
            $out .= "\r\n";

            fwrite($fp, $out);
            while (!feof($fp)) {
                $response .= fgets($fp, 128);
            }
            fclose($fp);
        }
    }

    //Get the headers in an associative array
    $headers = array();

    if($info['http_code'] == 404) {
        $body = "";
        $headers['Status'] = 404;
    } else {
        //Seperate header and content
	//echo "RESPONSE: ".$response."<BR><BR>\n";
	//exit();
        $separator_position = strpos($response,"\r\n\r\n");
        $header_text = substr($response,0,$separator_position);
        $body = substr($response,$separator_position+4);
	
	// added 04-28-2008 EG if we get a 301 (moved), another set of headers is received,
	if(substr($body,0,5)=="HTTP/"){
		$separator_position = strpos($body,"\r\n\r\n");
		$header_text = substr($body,0,$separator_position);
		$body = substr($body,$separator_position+4);
		}
	
        //echo "SEP: ".$separator_position."<BR><BR>\n";
	//echo "HEADER: ".$header_text."<BR><BR>\n";
	//echo "BODY: ".$body."<BR><BR>\n";
        
        foreach(explode("\n",$header_text) as $line) {
            $parts = explode(": ",$line);
            if(count($parts) == 2) $headers[$parts[0]] = chop($parts[1]);
        }
    }

    if($options['return_info'])
	return array('headers' => $headers, 'body' => $body, 'info' => $info);
    return $body;
}



?>