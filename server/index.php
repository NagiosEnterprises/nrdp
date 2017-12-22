<?php
//
// Nagios Remote Data Processor (NRDP)
// Copyright (c) 2010-2017 - Nagios Enterprises, LLC.
//
// License: Nagios Open Software License <http://www.nagios.com/legal/licenses>
//

//////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////
// Page Settings
///////////////////
// Change these variables to customize your NRDP landing page
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
//$display_alerts = "top";
$display_alerts = "bottom";

// What is the alert message timeout? (in seconds)
$alert_timeout = 3;

// This is the example data that will be populated in the data on the page
$fake_command = "DISABLE_HOST_NOTIFICATIONS";
$fake_hostname = "somehost";
$fake_servicename = "someservice";
$fake_output_good = "Everything looks okay! | perfdata=1;";
$fake_output_bad = "WARNING: Danger Will Robinson! | perfdata=1;";

//////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////

require_once(dirname(__FILE__).'/config.inc.php');
require_once(dirname(__FILE__).'/includes/utils.inc.php');

// Load plugins
load_plugins();

// Setup and authenticate
grab_request_vars();
check_auth();


route_request();


function route_request()
{
	$cmd = strtolower(grab_request_var("cmd"));

	// Token if required for most everything
	if ($cmd != "" && $cmd != "hello") {
		check_token();
	}

	switch ($cmd)
	{
		// Say hello
		case "hello":
			say_hello();
			break;

		// Display a form for debugging/testing
		case "":
			display_form();
			break;

		// Let the plugins handle output
		default:
			$args = array(
				"cmd" => $cmd
			);
			do_callbacks(CALLBACK_PROCESS_REQUEST, $args);
			break;
	}

	echo "NO REQUEST HANDLER";

	exit();
}


function say_hello()
{
	output_api_header();

	echo "<response>\n";
	echo "  <status>0</status>\n";
	echo "  <message>OK</message>\n";
	echo "  <product>".get_product_name()."</product>\n";
	echo "  <version>".get_product_version()."</version>\n";
	echo "</response>\n";

	exit();
}


function display_form()
{
	global $cfg;
	global $display_token, $page_token;
	global $default_tab;
	global $display_alerts, $alert_timeout;
	global $fake_command, $fake_hostname, $fake_servicename, $fake_output_good, $fake_output_bad;

	$tabs = array("command", "checkresult", "xml", "json");
	if (!in_array($default_tab, $tabs))
		$default_tab = "";

	$xmldata = "<?xml version='1.0'?>\n" .
		"<checkresults>\n" .
		"	<checkresult type='host'>\n" .
		"		<hostname>{$fake_hostname}</hostname>\n" .
		"		<state>0</state>\n" .
		"		<output>{$fake_output_good}</output>\n" .
		"	</checkresult>\n" .
		"	<checkresult type='service'>\n" .
		"		<hostname>{$fake_hostname}</hostname>\n" .
		"		<servicename>{$fake_servicename}</servicename>\n" .
		"		<state>1</state>\n" .
		"		<output>{$fake_output_bad}</output>\n" .
		"	</checkresult>\n" .
		"</checkresults>";

	/*
	// this is the example format to follow for converting
	// php arrays to json data
	$jsondata = array(
		"checkresults" => array(

			array("host" => array(
				"hostname" 		=> $fake_hostname,
				"state" 		=> 0,
				"output" 		=> $fake_output_good,
				)
			),
			array("service" => array(
				"hostname" 		=> $fake_hostname,
				"servicename" 	=> $fake_servicename,
				"state" 		=> 1,
				"output" 		=> $fake_output_bad,
				)
			),
		),
	);
	$jsondata = json_encode($jsondata, JSON_PRETTY_PRINT);
	*/
	
	$jsondata = "{\n" .
		"    \"checkresults\": [\n" .
		"        {\n" .
		"            \"host\": {\n" .
		"                \"hostname\": \"somehost\",\n" .
		"                \"state\": 0,\n" .
		"                \"output\": \"Everything looks okay! | perfdata=1;\"\n" .
		"            }\n" .
		"        },\n" .
		"        {\n" .
		"            \"service\": {\n" .
		"                \"hostname\": \"somehost\",\n" .
		"                \"servicename\": \"someservice\",\n" .
		"                \"state\": 1,\n" .
		"                \"output\": \"WARNING: Danger Will Robinson! | perfdata=1;\"\n" .
		"            }\n" .
		"        }\n" .
		"    ]\n" .
		"}";

	// reset token if we need to
	if ($display_token == false)
		$page_token = "";

	// change where we show the div if set
	$alerts_div = "\n" .
		"		<hr />\n\n" .
		"		<!-- this is where the alerts show up -->\n" .
		"		<div class=\"row\">\n" .
		"			<div class=\"col-12 messages\">\n" .
		"			</div>\n" .
		"		</div>";
	$alerts_on_bottom = true;
	if ($display_alerts == "top")
		$alerts_on_bottom = false;

?>
<!doctype html>
<html>
<head>
	<title>Nagios Remote Data Processor</title>
	<script src="includes/jquery-3.2.1.min.js"></script>
	<link href="includes/bootstrap.min.css" rel="stylesheet" />
	<script src="includes/bootstrap.bundle.min.js"></script>
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
	<script>

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

			console.log("success_xml(xml) data:");
			console.log(xml);

			var status = $(xml).find("status").text();
			var msg = $(xml).find("message").text();

			check_message_status(status, msg);
		}

		function success_json(json) {

			console.log("success_json(json) data:");
			console.log(json);

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
						XMLDATA: $("#xmldata").val()
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
						JSONDATA: $("#jsondata").val()
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

		<?php if ($alerts_on_bottom == false) {  echo $alerts_div; } ?>

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
							<input type="text" name="command" id="extcommand" value="<?php echo $fake_command . ';' . $fake_hostname; ?>" class="form-control form-control-sm">
							<small id="command-command-help" class="form-text">
								Specify your command string here. Helpful information <a href="https://assets.nagios.com/downloads/nagioscore/docs/nagioscore/4/en/extcommands.html">can be found here.</a>
							</small>
						</div>

						<button type="button" class="btn btn-primary submit-command">Submit Command</button>

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
									<textarea rows="19" name="XMLDATA" id="xmldata" class="form-control form-control-sm"><?php echo $xmldata; ?></textarea>
									<small class="form-text">
										Check result data in XML format.
									</small>
								</div>

								<button type="button" class="btn btn-primary submit-checkresult-xml">Submit XML Check Result</button>

							</div><!-- /xmltab -->

							<!-- json tab -->
							<div class="tab-pane fade" id="json" role="tabpanel" aria-labelledby="json-tab">

								<div class="form-group">
									<textarea rows="19" name="JSONDATA" id="jsondata" class="form-control form-control-sm"><?php echo $jsondata; ?></textarea>
									<small class="form-text">
										Check result data in JSON format.
									</small>
								</div>

								<button type="button" class="btn btn-primary submit-checkresult-json">Submit JSON Check Result</button>

							</div><!-- /jsontab -->

						</div><!-- /xml&json check result tabs -->
					</div><!-- /checkresult tab -->

				</div><!-- /action-contents -->
			</div><!-- /col-12 -->
		</div><!-- /row -->

		<?php if ($alerts_on_bottom == true) { echo $alerts_div; } ?>

	</div><!-- /container -->
</body>
</html>
<?php
	exit();
}


// Load all the plugins from the plugin folder
function load_plugins()
{
	$p = dirname(__FILE__)."/plugins/";
	$subdirs = scandir($p);
	foreach ($subdirs as $sd) {
		if ($sd == "." || $sd == "..") {
			continue;
		}
		$d = $p.$sd;
		if (is_dir($d)) {
			$pf = $d."/$sd.inc.php";
			if (file_exists($pf)) {
				include_once($pf);
			}
		}
	}
}

