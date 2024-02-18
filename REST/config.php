<?php

header('Content-Type:application/json');

function CheckConfigFile( array $config )
{
	if( ! array_key_exists( "X32_IP", $config ) )
	{
		echo json_encode( [ "config" => [], "Error_in_config" => "X32_IP param is missing" ] ) . "\n";
		exit(0);
	}

	if( ! array_key_exists( "MIXBUS_LIST", $config ) )
	{
		echo json_encode( [ "config" => [], "Error_in_config" => "MIXBUS_LIST param is missing" ] ) . "\n";
		exit(0);
	}
}

function SplitMixbusList( &$config )
{
	$config["MIXBUS_LIST"] = explode( ",", $config["MIXBUS_LIST"] );
	foreach( $config["MIXBUS_LIST"] as &$item )
	{
		settype( $item, "integer" );
	}
}


$config = parse_ini_file( __DIR__ . "/../config.ini");
CheckConfigFile( $config );

SplitMixbusList( $config );

$retval = [ "config" => $config ];

echo json_encode( $retval ) . "\n";