<?php
/*****************************************************************************
 *
 *
 *  NRDP - Nagios Remote Data Processor
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


//////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////
// Page Settings
// (Change these variables to customize your NRDP landing page)
///////////////////

// Should we display a token by default?
$display_token = true;
$page_token = "token";


// There are several tabs you can land on
//$default_tab = "command";
//$default_tab = "checkresult";
//$default_tab = "xml";
$default_tab = "json";

// Do you want the alerts at the top of the page? Or on the bottom?
//$display_alerts = "bottom";
$display_alerts = "top";


// What is the alert message timeout? (in seconds)
$alert_timeout = 3;


// This is the example data that will be populated in the data on the page
$fake_command               = "DISABLE_HOST_NOTIFICATIONS";
$fake_host_name             = "somehost";
$fake_service_description   = "someservice";
$fake_output_good           = "Everything looks okay! | perfdata=1;";
$fake_output_bad            = "WARNING: Danger Will Robinson! | perfdata=1;";


//////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////


require_once(dirname(__FILE__) . "/config.inc.php");
require_once(dirname(__FILE__) . "/includes/utils.inc.php");


// Load plugins
load_plugins();


// Setup and authenticate
grab_request_vars();
check_auth();


route_request();


function route_request()
{
    global $cfg;
    $cmd = strtolower(grab_request_var("cmd"));

    // If no command was specified check if we should show the options
    if (empty($cmd) && empty($cfg['hide_display_page'])) {
        display_form();
    }

    if ($cmd == "hello") {
        say_hello();
    }

    // Check for authenticated token
    check_token();

    $args = array(
        "cmd" => $cmd
    );
    do_callbacks(CALLBACK_PROCESS_REQUEST, $args);

    // A callback should have exited already
    echo "No command specified or request handler";
    exit();
}


function say_hello()
{
    $response = array(
        "response" => array(
            "status" => 0,
            "message" => "OK",
            "product" => get_product_name(),
            "version" => get_product_version(),
            ),
        );

    output_response($response);
    exit();
}


function display_form()
{
    global $display_token;
    global $page_token;
    global $default_tab;
    global $display_alerts;
    global $alert_timeout;
    global $fake_command;
    global $fake_host_name;


    $tabs = array("command", "checkresult", "xml", "json");
    if (!in_array($default_tab, $tabs)) {
        $default_tab = "";
    }


    $xml = get_default_xml();
    $json = get_default_json();


    // reset token if we need to
    if ($display_token == false) {
        $page_token = "";
    }


?>
<!doctype html>
<html>
<head>
    <title>Nagios Remote Data Processor</title>
    <script type="text/javascript" src="includes/jquery-3.2.1.min.js"></script>
    <link href="includes/bootstrap.min.css" rel="stylesheet" />
    <script type="text/javascript" src="includes/bootstrap.bundle.min.js"></script>
    <style>
        body {
            margin: 2em 0;
        }
        .btn {
            margin-top: 2em;
            cursor: pointer;
        }
        .tab-content {
            margin: 1em 0;
        }
        .token-group {
            margin-top: 1em;
        }
    </style>
    <script type="text/javascript">

        // number of seconds to keep the alerts
        var alert_timeout = <?php echo intval($alert_timeout); ?>;

        function build_alert(cssclass, msg) {

            var $alert = $("<div class=\"alert alert-" + cssclass + " form-control-sm\">" + msg + "</div>");
            $(".messages").html($alert);
            return $alert;
        }

        function check_message_status(status, msg) {

            var $alertbox;

            if (status != 0) {
                $alertbox = build_alert("danger", msg);
            } else {
                $alertbox = build_alert("info", msg);
            }

            setTimeout(function() { $alertbox.remove(); }, (alert_timeout * 1000));
        }

        function success_xml(xml) {

            //console.log("success_xml(xml) data:");
            //console.log(xml);

            var status = $(xml).find("status").text();
            var msg = $(xml).find("message").text();

            check_message_status(status, msg);
        }

        function success_json(json) {

            //console.log("success_json(json) data:");
            //console.log(json);

            var status = json.result.status;
            var msg = json.result.message;

            check_message_status(status, msg);
        }

        $(function() {

            // get the page hash so we can set the appropriate tabs
            var page_hash = $(location).attr("hash").substr(1);
            if (page_hash == "") {

                // use the php defined default if no hash is used
                page_hash = "<?php echo $default_tab; ?>";
            }

            if (page_hash == "command") {

                // this is the default, so we don't need to do anything
            }
            else if (page_hash == "checkresult") {

                $(".nav-tabs a[href='#checkresult'").tab("show");
            }
            else if (page_hash == "xml") {

                $(".nav-tabs a[href='#checkresult'").tab("show");

                // we don't need to explicitly show the xml tab either
                // since it is the default
                //$(".nav-tabs a[href='#xml'").tab("show");
            }
            else if (page_hash == "json") {

                $(".nav-tabs a[href='#checkresult'").tab("show");
                $(".nav-tabs a[href='#json'").tab("show");
            }

            $(".submit-command").click(function() {
                $.ajax({
                    type: "GET",
                    data: {
                        cmd: "submitcmd",
                        token: $("#token").val(),
                        command: $("#extcommand").val()
                    },
                    success: function(xml) { success_xml(xml); }
                });
            });

            $(".submit-checkresult-xml").click(function() {
                $.ajax({
                    type: "POST",
                    dataType: "xml",
                    data: {
                        cmd: "submitcheck",
                        token: $("#token").val(),
                        xml: $("#xml-data").val()
                    },
                    success: function(xml) { success_xml(xml); }
                });
            });

            $(".submit-checkresult-json").click(function() {

                $.ajax({
                    type: "POST",
                    dataType: "json",
                    data: {
                        cmd: "submitcheck",
                        token: $("#token").val(),
                        json: $("#json-data").val()
                    },
                    success: function(json) { success_json(json); }
                });
            });
        });
    </script>
</head>
<body>
    <div class="container">

        <div class="row">
            <div class="col-12">
                <h2>NRDP &middot; Nagios Remote Data Processor</h2>
            </div>
        </div>

        <?php 
            if ($display_alerts == "top") {
                echo get_alerts_div();
            }
        ?>

        <hr />

        <div class="row">
            <div class="col-12">

                <ul class="nav nav-tabs" id="action-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="command-tab" data-toggle="tab" href="#command" role="tab" aria-controls="command" aria-selected="true">Submit Nagios Command</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="checkresult-tab" data-toggle="tab" href="#checkresult" role="tab" aria-controls="checkresult" aria-selected="false">Submit Check Result</a>
                    </li>
                </ul>

                <!-- token is used everywhere -->
                <div class="form-group token-group">
                    <label for="token">Token</label>
                    <input type="text" name="token" id="token" value="<?php echo $page_token; ?>" placeholder="<?php echo $page_token; ?>" class="form-control form-control-sm">
                    <small class="form-text">
                        Use the token you've configured in your <code>config.inc.php</code> file.
                    </small>
                </div>

                <!-- command / check result tabs -->
                <div class="tab-content" id="action-contents">

                    <!-- command tab -->
                    <div class="tab-pane fade show active" id="command" role="tabpanel" aria-labelledby="command-tab">

                        <div class="form-group">
                            <label for="extcommand">Command</label>
                            <input type="text" name="command" id="extcommand" value="<?php echo $fake_command . ';' . $fake_host_name; ?>" class="form-control form-control-sm">
                            <small id="command-command-help" class="form-text">
                                Specify your command string here. Helpful information <a href="https://assets.nagios.com/downloads/nagioscore/docs/nagioscore/4/en/extcommands.html">can be found here.</a>
                            </small>
                        </div>

                        <a href="#" class="btn btn-primary submit-command">Submit Command</a>

                    </div><!-- /command-tab -->

                    <!-- checkresult tab -->
                    <div class="tab-pane fade" id="checkresult" role="tabpanel" aria-labelledby="checkresult-tab">

                        <ul class="nav nav-tabs" id="crtype-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="xml-tab" data-toggle="tab" href="#xml" role="tab" aria-controls="xml" aria-selected="true">XML Check Result</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="json-tab" data-toggle="tab" href="#json" role="tab" aria-controls="json" aria-selected="false">JSON Check Result</a>
                            </li>
                        </ul>

                        <!-- xml / json check result tabs -->
                        <div class="tab-content" id="crtpe-contents">

                            <!-- xml tab -->
                            <div class="tab-pane fade show active" id="xml" role="tabpanel" aria-labelledby="xml-tab">
                                <form method="post">

                                <div class="form-group">
                                    <textarea rows="19" name="xml" id="xml-data" class="form-control form-control-sm"><?php echo $xml; ?></textarea>
                                    <small class="form-text">
                                        Check result data in XML format.
                                    </small>
                                </div>

                                <a href="#" class="btn btn-primary submit-checkresult-xml">Submit XML Check Result</a>

                            </div><!-- /xmltab -->

                            <!-- json tab -->
                            <div class="tab-pane fade" id="json" role="tabpanel" aria-labelledby="json-tab">

                                <div class="form-group">
                                    <textarea rows="23" name="json" id="json-data" class="form-control form-control-sm"><?php echo $json; ?></textarea>
                                    <small class="form-text">
                                        Check result data in JSON format.
                                    </small>
                                </div>

                                <a href="#" class="btn btn-primary submit-checkresult-json">Submit JSON Check Result</a>

                            </div><!-- /jsontab -->

                        </div><!-- /xml&json check result tabs -->
                    </div><!-- /checkresult tab -->

                </div><!-- /action-contents -->
            </div><!-- /col-12 -->
        </div><!-- /row -->

        <?php 
            if ($display_alerts == "bottom") {
                echo get_alerts_div();
            }
        ?>

    </div><!-- /container -->
</body>
</html>
<?php
    exit();
}


// Load all the plugins from the plugin folder
function load_plugins()
{
    $plugins_dir = dirname(__FILE__) . "/plugins/";
    $sub_dirs = scandir($plugins_dir);

    foreach ($sub_dirs as $sub_dir) {

        if ($sub_dir == "." || $sub_dir == "..") {
            continue;
        }

        $plugin_dir = "{$plugins_dir}{$sub_dir}";

        if (is_dir($plugin_dir)) {

            $plugin_file = "{$plugin_dir}/{$sub_dir}.inc.php";

            if (file_exists($plugin_file)) {
                include_once($plugin_file);
            }
        }
    }
}



// grab default xml data output
// with the defaults specified
// at the beginning of the script
function get_default_xml()
{
    global $fake_host_name;
    global $fake_service_description;
    global $fake_output_good;
    global $fake_output_bad;

    return <<<XML
<?xml version='1.0'?>
<checkresults>
    <checkresult type='host'>
        <hostname>{$fake_host_name}</hostname>
        <state>0</state>
        <output>{$fake_output_good}</output>
    </checkresult>
    <checkresult type='service'>
        <hostname>{$fake_host_name}</hostname>
        <servicename>{$fake_service_description}</servicename>
        <state>1</state>
        <output>{$fake_output_bad}</output>
    </checkresult>
</checkresults>
XML;
}



// grab default json data output
// with the defaults specified
// at the beginning of the script
function get_default_json()
{
    global $fake_host_name;
    global $fake_service_description;
    global $fake_output_good;
    global $fake_output_bad;

    /*

    // this is the example format to follow for converting
    // php arrays to json data

    $json = array(
        "checkresults" => array(

            array(
                "checkresult"   => array(
                    "type"      => "host",
                ),
                "hostname"      => $fake_host_name,
                "state"         => 0,
                "output"        => $fake_output_good,
                )
            ),
            array(
                "checkresult"   => array(
                    "type"      => "service",
                ),
                "hostname"      => $fake_host_name,
                "servicename"   => $fake_service_description,
                "state"         => 1,
                "output"        => $fake_output_bad,
                )
            ),
        ),
    );

    return json_encode($json, JSON_PRETTY_PRINT);

    */
    
    return <<<JSON
{
    "checkresults": [
        {
            "checkresult": {
                "type": "host"
            },
            "hostname": "{$fake_host_name}",
            "state": "0",
            "output": "{$fake_output_good}"
        },
        {
            "checkresult": {
                "type": "service"
            },
            "hostname": "{$fake_host_name}",
            "servicename": "{$fake_service_description}",
            "state": "1",
            "output": "{$fake_output_bad}"
        }
    ]
}
JSON;
}



// the alerts div can be at the top or the bottom
// of the form - this way it can easily be called
// in the html above
function get_alerts_div()
{
    return <<<ALERTSDIV

        <hr />
        <!-- this is where the alerts show up -->
        <div class="row">
            <div class="col-12 messages">
            </div>
        </div>

ALERTSDIV;
}
