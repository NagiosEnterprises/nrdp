<?php
//
// NRDP Utils
//
// Copyright (c) 2008-2017 - Nagios Enterprises, LLC. All rights reserved.
// License: Nagios Open Software License <http://www.nagios.com/legal/licenses>
//


require_once("constants.inc.php");


if (!defined("callbacks")) {
    $callbacks = array();
}


////////////////////////////////////////////////////////////////////////
// REQUEST FUNCTIONS
////////////////////////////////////////////////////////////////////////


$escape_request_vars=true;
$request_vars_decoded=false;


if (!function_exists('map_htmlentities')) {
    function map_htmlentities($arrval) {
        if (is_array($arrval)) {
            return array_map('map_htmlentities', $arrval);
        } else {
            return htmlentities($arrval, ENT_QUOTES);
        }
    }
}


if (!function_exists('map_htmlentitydecode')) {
    function map_htmlentitydecode($arrval) {
        if (is_array($arrval)) {
            return array_map('map_htmlentitydecode', $arrval);
        } else {
            return html_entity_decode($arrval, ENT_QUOTES);
        }
    }
}


// Grabs POST and GET variables
if (!function_exists('grab_request_vars')) {
    function grab_request_vars($preprocess=true, $type="") {
        global $escape_request_vars;
        global $request;

        // Do we need to strip slashes?
        $strip = false;
        if ((function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) || (ini_get('magic_quotes_sybase') && (strtolower(ini_get('magic_quotes_sybase')) != "off"))) {
            $strip = true;
        }

        $request = array();

        if ($type == "" || $type == "get") {
            foreach ($_GET as $var => $val) {
                if ($escape_request_vars == true) {
                    if (is_array($val)) {
                        $request[$var] = array_map('map_htmlentities', $val);
                    } else {
                        $request[$var] = htmlentities($val, ENT_QUOTES);
                    }
                } else {
                    $request[$var] = $val;
                }
            }
        }

        if ($type == "" || $type == "post") {
            foreach ($_POST as $var => $val) {
                if ($escape_request_vars == true) {
                    if (is_array($val)) {
                        $request[$var] = array_map('map_htmlentities', $val);
                    } else {
                        $request[$var] = htmlentities($val, ENT_QUOTES);
                    }
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
}


if (!function_exists('grab_request_var')) {
    function grab_request_var($varname, $default="") {
        global $request;
        global $escape_request_vars;
        global $request_vars_decoded;

        $v = $default;
        if (isset($request[$varname])) {
            if ($escape_request_vars == true && $request_vars_decoded == false) {
                if (is_array($request[$varname])) {
                    $v = array_map('map_htmlentitydecode', $request[$varname]);
                } else {
                    $v = html_entity_decode($request[$varname], ENT_QUOTES);
                }
            } else {
                $v = $request[$varname];
            }
        }

        return $v;
    }
}


if (!function_exists('decode_request_vars')) {
    function decode_request_vars() {
        global $request;
        global $request_vars_decoded;

        $newarr = array();
        foreach ($request as $var => $val) {
            $newarr[$var] = grab_request_var($var);
        }

        $request_vars_decoded = true;
        $request = $newarr;
    }
}


////////////////////////////////////////////////////////////////////////
// OUTPUT FUNCTIONS
////////////////////////////////////////////////////////////////////////


// Generate output header
function output_api_header() {
    global $request;

    // We usually output XML, except if debugging
    if (isset($request['debug'])) {
        if ($request['debug'] == 'text') {
            header("Content-type: text/plain");
        } else {
            header("Content-type: text/html");
        }
    } else {
		if (isset($request['JSONDATA']) || isset($request['json'])) {
			header("Content-Type: application/json");
		} else {
			header("Content-type: text/xml");
			echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		}
    }
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


// Gets value from array using default
if (!function_exists('grab_array_var')) {
    function grab_array_var($arr, $varname, $default="") {
        global $request;

        $v = $default;
        if (is_array($arr)) {
            if (array_key_exists($varname, $arr)) {
                $v = $arr[$varname];
            }
        }

        return $v;
    }
}


function xmlentities($uncleaned) {
    $search = array("<", ">", "&", "\"", "'", "’");
    $replace = array("&lt;", "&gt;", "&amp;", "&quot;", "&apos;", "&apos;");
    $cleaned = str_replace($search, $replace, $uncleaned);
    return $cleaned;
}


////////////////////////////////////////////////////////////////////////
// ERROR HANDLING FUNCTIONS
////////////////////////////////////////////////////////////////////////


// Just returns an XML or JSON error string and exits execution
function handle_api_error($msg) {
	global $request;
    _debug("handle_api_error(msg=$msg)");
    output_api_header();
    if (isset($request['JSONDATA'])) {
		if (isset($request['pretty'])) {
			echo "{\n";
			echo "  \"result\" : {\n";
			echo "    \"status\" : \"0\",\n";
			echo "    \"message\" : \"".$msg."\"\n";
			echo "  }\n";
			echo "}\n";
		} else {
			echo "{ \"result\" : { \"status\" : \"-1\", \"message\" : \"".$msg."\" } }\n";
		}
	} else {
		echo "<result>\n";
		echo "  <status>-1</status>\n";
		echo "  <message>" . xmlentities($msg) . "</message>\n";
		echo "</result>\n";
	}
    exit();
}


/////////////////////////////////////////////////
// AUTHENTICATION/AUTHORIZATION FUNCTIONS
/////////////////////////////////////////////////


function check_auth() {
    global $cfg;

    _debug("check_auth()");

    // HTTPS is required
    if (!isset($cfg["require_https"]) || $cfg["require_https"] !== false) {
        _debug(" * require_https either not set or non-false (is required)");
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == "") {
            _debug(" * no https usage, denying auth");
            handle_api_error(ERROR_HTTPS_REQUIRED);
        }
    }

    // Require basic authentication
    if (!isset($cfg["require_basic_auth"]) || $cfg["require_basic_auth"]!==false){
        _debug(" * require_basic_auth either not set or non-false (is required)");
        if (!isset($_SERVER['REMOTE_USER']) || $_SERVER['REMOTE_USER'] == "") {
            _debug(" * no remote_user set, denying auth");
            handle_api_error(ERROR_NOT_AUTHENTICATED);
        }
        if (isset($cfg["valid_basic_auth_users"])) {
            if (!in_array($_SERVER['REMOTE_USER'], $cfg["valid_basic_auth_users"])) {
                _debug(" * remote_user not in \$cfg['valid_basic_auth_users'], denying auth");
                handle_api_error(ERROR_BAD_USER);
            }
        }
    }
}


function check_token() {
    global $cfg;

    _debug("check_token()");

    // User must supply a token
    $user_token = grab_request_var("token");
    if (!have_value($user_token)) {
        _debug(" * no token supplied");
        handle_api_error(ERROR_NO_TOKEN_SUPPLIED);
    }

    _debug(" checking token: $user_token");

    // No valid tokens are configured
    if (!isset($cfg["authorized_tokens"])) {
        _debug(" * no authorized_tokens defined in \$cfg, denying token");
        handle_api_error(ERROR_NO_TOKENS_DEFINED);
    }

    // Token must be valid
    if (!in_array($user_token, $cfg["authorized_tokens"])) {
        _debug(" * token ($user_token) not in \$cfg['authorized_tokens'], denying token");
        handle_api_error(ERROR_BAD_TOKEN_SUPPLIED);
    }

    _debug(" * token passed");
}
    
    
/////////////////////////////////////////////////
// UTILITIES
/////////////////////////////////////////////////


if (!function_exists('get_product_name')) {
    function get_product_name() {
        global $cfg;
        return $cfg['product_name'];
    }
}


if (!function_exists('get_product_version')) {
    function get_product_version() {
        global $cfg;
        return $cfg['product_version'];
    }
}


////////////////////////////////////////////////////////////////////////
// CALLBACK FUNCTIONS
////////////////////////////////////////////////////////////////////////


if (!function_exists('do_callbacks')) {
    function do_callbacks($cbtype, &$args) {
        global $callbacks;
        $total_callbacks = 0;

        if (array_key_exists($cbtype, $callbacks)) {
            foreach ($callbacks[$cbtype] as $cb) {
                $cb($cbtype, $args);
                $total_callbacks++;
            }
        }

        return $total_callbacks;
    }
}


if (!function_exists('count_nrdp_callbacks')) {
    function count_nrdp_callbacks($cbtype) {
        global $callbacks;
        $total_callbacks = 0;

        if (array_key_exists($cbtype, $callbacks)) {
            $total_callbacks = count($callbacks[$cbtype]);
        }

        return $total_callbacks;
    }
}


if (!function_exists('register_callback')) {
    function register_callback($cbtype, $func, $prepend=null) {
        global $callbacks;
        
        if ($prepend) {
            array_unshift($callbacks[$cbtype], $func);
        } else {
            $callbacks[$cbtype][] = $func;
        }
    }
}

if (!function_exists('_debug')) {
    function _debug($data) {

        global $cfg;

        if (!is_string($data))
            return;

        $debug = grab_array_var($cfg, "debug", false);
        if (!$debug)
            return;

        $file = grab_array_var($cfg, "debug_log", "/usr/local/nrdp/server/debug.log");
        $date = '[' . date('r') . '] ';
        $datepad = str_pad(' ', strlen($date));

        $lines = explode("\n", $data);

        foreach ($lines as $i => $line) {
            if ($i == 0) {
                file_put_contents($file, "{$date}{$line}\n", FILE_APPEND);
            }
            else {
                file_put_contents($file, "{$datepad}{$line}\n", FILE_APPEND);
            }
        }
    }
}
