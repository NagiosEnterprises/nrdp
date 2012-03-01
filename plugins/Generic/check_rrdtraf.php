#!/usr/bin/php
<?php

/*
    SOLO TRABAJA EN MODO LOCAL, IGNORA CUALQUIER $host$ QUE SE LE PASE COMO PARAMETRO
    
    TODO: 
	Usar $some_error para algo, aun no se detectan estados UNKNOWN por errores( falta archivo, no puede leer, etc)
	ejecutar desde la linea de comandos para ver mensajes de ese tipo
*/

define( VERSION, "check_rrdtraf v0.1 By Pablo Armando 2009");

// Nagios status
define( OK,       0);
define( WARNING,  1);
define( CRITICAL, 2);
define( UNKNOWN,  3);

$status = array(
		"OK",
		"WARNING",
		"CRITICAL",
		"UNKNOWN"
		);

    $some_error = false;
    $ret = OK;
    $verbose = false;
    $check_input = false;
    $check_output = false;
    $rrd_file = '';
    $warning_min = 0;
    $warning_max = PHP_INT_MAX;
    $critical_min = 0;
    $critical_max = PHP_INT_MAX;

    // Llamamos a la funcion principal de nuestro programa
    main();
    
    
function main()
{
    global $verbose, $rrd_file, $warning_min, $warning_max, $critical_min, 
    $critical_max, $check_input, $check_output, $status, $ret, $some_error;
    
    $on_warning = false;
    $on_critical = false;

    $opciones = getopt("hIOvf:w:W:c:C:" /*, array("help", "rrd_file")*/ );

    if( !is_null($opciones['h']) )
    {
	ayuda();
	exit(UNKNOWN);
    }

    if( !is_null($opciones['v']) )
    {
	printf("%s\n", VERSION);
	exit(UNKNOWN);
    }

    if( !is_null($opciones['I']) )
	$check_input = true;

    if( !is_null($opciones['O']) )
	$check_output = true;

    if( $check_input && $check_output || !$check_input && !$check_output)
    {
	printf("Check Input AND check Output are true or both false. Choose only one per call\n");
	$ret = UNKNOWN;
	exit($ret);
    }

    if( !is_null($opciones['f']) )
	$rrd_file = $opciones['f'];

    if( !is_null($opciones['w']) )
	$warning_min = $opciones['w'];
    else
	$warning_min = 0;

    if( !is_null($opciones['W']) )
	$warning_max = $opciones['W'];
    else
	$warning_max = PHP_INT_MAX;

    if( !is_null($opciones['c']) )
	$critical_min = $opciones['c'];
    else
	$critical_min = 0;

    if( !is_null($opciones['C']) )
	$critical_max = $opciones['C'];
    else
	$critical_max = PHP_INT_MAX;

    $traffic = get_traffic($rrd_file);
    
    if( $check_input )
	$on_warning = check_warning($traffic[0], $warning_min, $warning_max, $kbps);
    else
	$on_warning = check_warning($traffic[1], $warning_min, $warning_max, $kbps);

    if( $check_input )
	$on_critical = check_critical($traffic[0], $critical_min, $critical_max);
    else
	$on_critical = check_critical($traffic[1], $critical_min, $critical_max);

    // $ret es por defecto OK    
    if( $on_warning )
    {
	// WARNING
	$ret = WARNING;
    }
    
    if( $on_critical )
    {
	// CRITICAL
	$ret = CRITICAL;
    }
    
    if( $some_error )
    {
	$ret = UNKNOWN;
    }
    
    printf("%s - %s kbps %s\n", $status[$ret], round($kbps, 2), $check_input===true?"IN traffic":"OUT traffic");
    // dump_parametros();

    exit($ret);
}

function check_warning($traffic, $warn_min, $warn_max, &$kbps)
{
    $kbps = $traffic * 8 / 1000;
    
    return between($kbps, $warn_min, $warn_max);
}

function check_critical($traffic, $crit_min, $crit_max)
{
    $kbps = $traffic * 8 / 1000;
    
    return between($kbps, $crit_min, $crit_max);
}


function between($x, $min, $max)
{
    if( $x >= $min && $x <= $max )
	return true;
    else
	return false;
}

function get_traffic($rrd_file)
{
    $cmd = '/usr/bin/rrdtool fetch ' . $rrd_file . ' AVERAGE -r 300 -s -300 -e -300' ;
    $lines = explode("\n", shell_exec($cmd) );
    list($time, $in, $out) = explode( " ", $lines[2] );
    // printf(" Time: %s\n In: %s\n Out: %s\n", $time, $in, $out);
    return array($in, $out);
}

function dump_parametros()
{
    global $verbose, $rrd_file, $warning_min, $warning_max, $critical_min, $critical_max;
    printf("Verbose: %s\nRRD File: %s\n", $verbose, $rrd_file);
    printf("Warn Min: %s\nWarn Max: %s\n", $warning_min, $warning_max);
    printf("Crit Min: %s\nCrit Max: %s\n", $critical_min, $critical_max);
}

function ayuda()
{
    printf("This plugin will check the incoming/outgoing transfer rates of a router,\n");
    printf("switch, etc recorded in an Cacti rrd database\n");
    printf("By Pablo Armando (c) 2009\n");
    printf("\n");
    printf("Usage:\n\tcheck_rrdtraf [-h] -f rrd_file -I|-O -w min_warning -W max_warning -c min_critial -C max_critical \n\n");
    printf("Example:\n\tcheck_rrdtraf -f /var/lib/cacti/rra/mx_1_traffic_in_239.rrd -w 1000 -W 2000 -c 4000 -C 4500 -I \n\n");
    printf("This checks if the input traffic (-I) is between 1000 and 2000kbps.\n");
    printf("If this is true a warning message will be fired If the input traffic\n");
    printf("is between 4000 and 4500kbps then a critic message will be fired\n");
    printf("\n");
    printf("-h   Show this help\n");
    printf("-f   full path to rrd database\n");
    printf("-I   check input traffic from incoming data source\n");
    printf("-O   check output trafic from outgoing data source\n");
    printf("-w   start of warning range - Required\n");
    printf("-W   end of warning range   - Required\n");
    printf("-c   start of critic range  - Required\n");
    printf("-C   end of critic range    - Required\n");
    printf("\nThe values for -w, -W, -c, and -C are expresed in kbps\n");
}

?>