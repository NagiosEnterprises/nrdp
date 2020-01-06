<?php
/*****************************************************************************
 *
 *
 *  NRDP Common Utilities
 *
 *
 *  Copyright (c) 2008-2020 - Nagios Enterprises, LLC. All rights reserved.
 *
 *  License: GNU General Public License version 3
 *
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *****************************************************************************/

require_once("constants.inc.php");

$escape_request_vars = false;


////////////////////////////////////////////////////////////////////////
// REQUEST FUNCTIONS
////////////////////////////////////////////////////////////////////////



// encode htmlentities on a string or array recursively on an array
function map_htmlentities($val)
{
    if (is_array($val)) {
        return array_map('map_htmlentities', $val);
    } else {
        return htmlentities($val, ENT_QUOTES);
    }
}



// decode htmlentities on a string or array recursively on an array
function map_htmlentitydecode($val)
{
    if (is_array($val)) {
        return array_map('map_htmlentitydecode', $val);
    } else {
        return html_entity_decode($val, ENT_QUOTES);
    }
}



// Grabs POST and GET variables
function grab_request_vars($type = "")
{
    global $escape_request_vars;
    global $request;

    $have_magic_quotes_gpc = false;
    if (function_exists("get_magic_quotes_gpc")) {
        $have_magic_quotes_gpc = get_magic_quotes_gpc();
    }

    $have_magic_quotes_sybase = false;
    $magic_quotes_sybase = ini_get("magic_quotes_sybase");
    if (!empty($magic_quotes_sybase)) {
        if (strtolower($magic_quotes_sybase) != "off") {
            $have_magic_quotes_sybase = true;
        }
    }

    // Should we strip slashes?
    $strip = false;
    if ($have_magic_quotes_gpc || $have_magic_quotes_sybase) {
        $strip = true;
    }

    $request = array();

    if ($type == "" || $type == "get") {
        foreach ($_GET as $var => $val) {
            if ($escape_request_vars == true) {
                $request[$var] = map_htmlentities($val);
            } else {
                $request[$var] = $val;
            }
        }
    }

    if ($type == "" || $type == "post") {
        foreach ($_POST as $var => $val) {
            if ($escape_request_vars == true) {
                $request[$var] = map_htmlentities($val);
            } else {
                $request[$var] = $val;
            }
        }
    }

    // Strip slashes - we escape them later in sql queries
    if ($strip == true) {
        foreach ($request as $var => $val) {
            $request[$var] = stripslashes($val);
        }
    }
}



// grab a specific request variable
function grab_request_var($varname, $default = "")
{
    global $request;

    $value = $default;

    if (isset($request[$varname])) {
        $value = $request[$varname];
    }

    return $value;
}



// grab json data request variable
function grab_json()
{
    // maintain legacy requests
    $json_data = grab_request_var("JSONDATA");

    $json_data = grab_request_var("jsondata", $json_data);
    $json_data = grab_request_var("json", $json_data);

    return $json_data;
}



// grab xml data request variable
function grab_xml()
{
    // maintain legacy requests
    $xml_data = grab_request_var("XMLDATA");

    $xml_data = grab_request_var("xmldata", $xml_data);
    $xml_data = grab_request_var("xml", $xml_data);

    return $xml_data;
}



////////////////////////////////////////////////////////////////////////
// OUTPUT FUNCTIONS
////////////////////////////////////////////////////////////////////////



// return the type to output based on the input received
function output_type($default = TYPE_XML)
{
    $output_type = grab_request_var("output_type");
    if (!empty($output_type)) {
        return $output_type;
    }

    // test for xml first
    $xml_data = grab_xml();
    if (!empty($xml_data)) {
        return TYPE_XML;
    }

    $json_data = grab_json();
    if (!empty($json_data)) {
        return TYPE_JSON;
    }

    return $default;
}



// generate output header
function output_api_header()
{
    $debug = grab_request_var("debug", false);

    if ($debug == true) {

        if ($debug == "text") {
            header("Content-type: text/plain");
        }
        else {
            header("Content-type: text/html");
        }

        return;
    }

    $output_type = output_type();

    switch ($output_type) {

    case TYPE_JSON:
        header("Content-Type: application/json");
        break;

    case TYPE_XML:
    default:
        header("Content-type: text/xml");
        echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
        break;
    }
}



// accepts an array and will output it either xml
// or json based on output_type()
// will only pretty print if php > 5.4.0
function output_response($arr)
{
    output_api_header();

    $output_type = output_type();
    switch ($output_type) {

    case TYPE_JSON:

        $pretty_print = 0;
        if (grab_request_var("pretty")) {
            if (defined("JSON_PRETTY_PRINT")) {
                $pretty_print = JSON_PRETTY_PRINT;
            }
        }

        echo json_encode($arr, $pretty_print);
        break;


    case TYPE_XML:
    default:

        echo generate_xml_from_arr($arr, 0);
        break;
    }
}



// accepts an array and will output xml with
// appropriate indentation
function generate_xml_from_arr($arr, $indentation)
{
    if (is_array($arr)) {

        $tab_size = 4;
        $indent = str_repeat(" ", ($indentation * $tab_size));

        $ret = "";

        foreach ($arr as $key => $el) {

            $possible_newline = "";
            $start_indent = $indent;
            $close_indent = "";

            if (is_array($el)) {
                $possible_newline = "\n";
                $close_indent = $indent;
            }

            $ret .= $start_indent . "<{$key}>" . $possible_newline;
            $ret .= generate_xml_from_arr($el, $indentation + 1);
            $ret .= $close_indent . "</{$key}>" . "\n";
        }

        return $ret;
    }

    return xmlentities($arr);
}



// allows you to easily intermix logic where you would generally
// have to clutter up your codespace with a few ifs to gather
// specific data points from different object types
function get_xml_or_json($type, $var, $xml, $json, $xml_func = false, $default = "")
{
    if ($type == TYPE_XML) {

        if ($xml_func == true) {
            if ($var->$xml() !== null) {
                return $var->$xml();
            }
        }

        else {
            if (isset($var->$xml)) {
                return $var->$xml;
            }
        }
    }

    if ($type == TYPE_JSON) {

        if (isset($var[$json])) {
            return $var[$json];
        }
    }

    return $default;
}


////////////////////////////////////////////////////////////////////////
// MISC FUNCTIONS
////////////////////////////////////////////////////////////////////////


if (!function_exists('have_value')) {
    function have_value($var) {
        if (!isset($var))
            return false;
        else if (is_null($var))
            return false;
        else if (empty($var))
            return false;
        else if ($var == "")
            return false;
        return true;
    }
}


// gets value from array using default
function grab_array_var($arr, $varname, $default = "")
{
    if (isset($arr[$varname])) {
        return $arr[$varname];
    }
 
    return $default;
}



// gets value from config or default
function grab_cfg_var($varname, $default = "", $return_as_array = false)
{
    global $cfg;

    $value = $default;

    if (isset($cfg[$varname])) {
        $value = $cfg[$varname];
    }

    if ($return_as_array == true) {
        $value = required_array($value);
    }

    return $value;
}



// if a variable is required to be an array
// convert it to one if it isn't
function required_array($var)
{
    if (empty($var)) {
        return array();
    }

    if (is_array($var)) {
        return $var;
    }

    return array($var);
}



// just like htmlentities - except for xml
function xmlentities($str)
{
    $search = array(  "<",    ">",    "&",     "\"",     "'",      "’");
    $replace = array( "&lt;", "&gt;", "&amp;", "&quot;", "&apos;", "&apos;");

    return str_replace($search, $replace, $str);
}



////////////////////////////////////////////////////////////////////////
// ERROR HANDLING FUNCTIONS
////////////////////////////////////////////////////////////////////////



// handles an xml or json api error
function handle_api_error($msg, $info = "")
{
    _debug("handle_api_error(msg=$msg)");

    $error = array(
        "result" => array(
            "status"    => -1,
            "message"   => $msg,
            ),
        );

    if (!empty($info)) {
        $error["result"]["information"] = $info;
    }

    output_response($error);

    exit(0);
}



////////////////////////////////////////////////////////////////////////
// AUTHENTICATION/AUTHORIZATION FUNCTIONS
////////////////////////////////////////////////////////////////////////



// check authentication if required
function check_auth()
{
    _debug("check_auth()");

    $require_https = grab_cfg_var("require_https", false);

    // Verify that we are using HTTPS if we are required
    if ($require_https == true) {
        _debug(" * require_https is set to true, checking to make sure request was via HTTPS");
        if (empty($_SERVER['HTTPS'])) {
            _debug(" * not using https, denying auth");
            handle_api_error(ERROR_HTTPS_REQUIRED);
        }
    }

    $require_basic_auth = grab_cfg_var("require_basic_auth", false);

    // Verify against basic auth if it is set
    if ($require_basic_auth == true) {

        _debug(" * require_basic_auth is set to true, checking against basic auth users");

        $remote_user = grab_array_var($_SERVER, "REMOTE_USER");

        if (empty($remote_user)) {

            _debug(" * no remote_user set, denying auth");
            handle_api_error(ERROR_NOT_AUTHENTICATED);
        }

        $valid_basic_auth_users = grab_cfg_var("valid_basic_auth_users", array(), true);

        if (!in_array($remote_user, $valid_basic_auth_users)) {

            _debug(" * remote_user not in \$cfg['valid_basic_auth_users'], denying auth");
            handle_api_error(ERROR_BAD_USER);
        }
    }
}



// check supplied token against config file
function check_token()
{
    _debug("check_token()");

    $user_token = grab_request_var("token");

    // User must supply a token
    if (empty($user_token)) {

        _debug(" * no token supplied");
        handle_api_error(ERROR_NO_TOKEN_SUPPLIED);
    }

    _debug(" checking token: $user_token");

    $authorized_tokens = grab_cfg_var("authorized_tokens", array(), true);

    // No valid tokens are configured
    if (empty($authorized_tokens)) {

        _debug(" * no authorized_tokens defined in \$cfg, denying token");
        handle_api_error(ERROR_NO_TOKENS_DEFINED);
    }

    // Token must be valid
    if (!in_array($user_token, $authorized_tokens)) {

        _debug(" * token ($user_token) not in \$cfg['authorized_tokens'], denying token");
        handle_api_error(ERROR_BAD_TOKEN_SUPPLIED);
    }

    _debug(" * token passed");
}
    


/////////////////////////////////////////////////
// PRODUCT INFORMATION
/////////////////////////////////////////////////



// return the product name
function get_product_name()
{
    return PRODUCT_NAME;
}



// return the product version
function get_product_version()
{
    return PRODUCT_VERSION;
}




////////////////////////////////////////////////////////////////////////
// CALLBACK FUNCTIONS
////////////////////////////////////////////////////////////////////////



// execute cbtype callbacks with specified args
function do_callbacks($cbtype, &$args)
{
    global $callbacks;
    $total_callbacks = 0;

    if (isset($callbacks[$cbtype])) {
        foreach ($callbacks[$cbtype] as $cb) {
            $cb($cbtype, $args);
            $total_callbacks++;
        }
    }

    return $total_callbacks;
}



// register a callback to be used by nrdp
function register_callback($cbtype, $func, $prepend = null)
{
    global $callbacks;
    
    if (!empty($prepend)) {
        array_unshift($callbacks[$cbtype], $func);
    }
    else {
        $callbacks[$cbtype][] = $func;
    }
}




////////////////////////////////////////////////////////////////////////
// DEBUGGING FUNCTIONS
////////////////////////////////////////////////////////////////////////



// print a debugging message to the log file if debugging is enabled
function _debug($data)
{
    $debug = grab_cfg_var("debug", false);
    if ($debug == false) {
        return;
    }

    if (!is_string($data)) {
        return;
    }

    $debug_log = grab_cfg_var("debug_log", "/usr/local/nrdp/server/debug.log");
    $date = '[' . date('r') . '] ';
    $datepad = str_pad(' ', strlen($date));

    $lines = explode("\n", $data);

    foreach ($lines as $i => $line) {

        if ($i == 0) {
            file_put_contents($debug_log, "{$date}{$line}\n", FILE_APPEND);
        }

        // for multi-line output we pad
        // the left hand side for easy visibility
        else {
            file_put_contents($debug_log, "{$datepad}{$line}\n", FILE_APPEND);
        }
    }
}



////////////////////////////////////////////////////////////////////////
// HUMAN READABLE FUNCTIONS
////////////////////////////////////////////////////////////////////////



function human_readable_state_type($state_type)
{
    switch ($state_type) {

    case 0:
        return "SOFT";
        break;

    case 1:
        return "HARD";
        break;
    }
}



function human_readable_state($type, $state)
{
    if ($type == "host") {
        return human_readable_host_state($state);
    }

    return human_readable_service_state($state);
}



function human_readable_host_state($state)
{
    switch ($state) {

    case 0:
        return "UP";
        break;

    case 1:
        return "DOWN";
        break;

    default:
        return "UNREACHABLE";
        break;
    }
}



function human_readable_service_state($state)
{
    switch ($state) {

    case 0:
        return "OK";
        break;

    case 1:
        return "WARNING";
        break;

    case 2:
        return "CRITICAL";
        break;

    default:
        return "UNKNOWN";
        break;
    }
}
