<?php

header('Content-Type:application/json');

$GLOBALS["Debug"] = false;

$retval = [ "Answers" => [] ];

$config = parse_ini_file( __DIR__ . "/../config.ini");
CheckConfigFile( $config );

$input_data = json_decode(file_get_contents('php://input'), true);

if( array_key_exists( "requests", $input_data ) )
{
	$connection = new X32Connection( $config["X32_IP"] );
	
	$requests = [];
	foreach( $input_data["requests"] as $requestData )
	{
		$request = new X32Request( $requestData );
		$requests[] = $request;
		if( $request->id > 0 )
		{
			$retval["Answers"][$request->id] = $connection->MakeRequest( $request );
		}
	}
}

echo json_encode( $retval ) . "\n";

function CheckConfigFile( array $config )
{
	if( ! array_key_exists( "X32_IP", $config ) )
	{
		echo json_encode( [ "Answers" => [], "Error_in_config" => "X32_IP param is missing" ] ) . "\n";
		exit(0);
	}
}

class X32Answer
{
	public bool $valid = false;
	public int $id;
	public $data;

	function __construct(int $id)
	{
		$this->id = $id;
	}
}

class X32Request
{
	public bool $valid = false;
	public int $id;
	public string $func;
	public array $data;

	function __construct( array $json ) {

		foreach( $json as $key => $value )
		{		
			$this->{$key} = $value;
		}

		if( ! array_key_exists( "id", $json ) ) return;
		if( ! array_key_exists( "func", $json ) ) return;
		if( ! array_key_exists( "data", $json ) ) return;
		
		if( ! array_key_exists( $json["func"], X32Connection::$functionlist )) return;

		foreach( X32Connection::$functionlist[$json["func"]] as $input )
		{
			if( ! array_key_exists( $input, $json["data"] ) ) return;
		}

		$this->valid = true;
	}
}

class X32Connection
{
	private $fp = null;

	static array $functionlist = [ 
		"GetLevelSendToMixbus" => [ "channel", "mixbus" ],
		"SetLevelSendToMixBus" => [ "channel", "mixbus", "level" ],
	
		"GetMuteSendToMixBus" => [ "channel", "mixbus" ],
		"SetMuteSendToMixBus" => [ "channel", "mixbus", "on_or_off" ],
	
		"GetChannelName" => [ "channel" ],
		"GetChannelColor" => [ "channel"],

		"GetMixBusOnOrOff" => [ "mixbus"],
		"SetMixBusOnOrOff" => [ "mixbus", "on_or_off"],

		"GetMixBusFaderLevel" => [ "mixbus"],
		"SetMixBusFaderLevel" => [ "mixbus", "level"],
	];	

	function __construct( string $ip )
	{
		$this->fp = stream_socket_client("udp://$ip:10023", $errno, $errstr, 30);
		if( !$this->fp ){
			$this->fp = null;
			echo "$errstr ($errno)\n";
		}
	}

	function __destruct()
	{
		fclose( $this->fp );
	}

	function MakeRequest( X32Request $request )
	{
		$answer = new X32Answer( $request->id );
		if( ! $request->valid ) return $answer;

		$answer->valid = true;
		$answer->data = call_user_func_array( array( $this, $request->func), $request->data );
		return $answer;
	}

	static function DebugPrint( string $data )
	{
		if( isset( $GLOBALS["Debug"] ) && $GLOBALS["Debug"] == false ) return;

		$arr = str_split( $data, 1 );
		foreach( $arr as &$it )
		{
			if( ord( $it ) < 31 )
			$it = "0x" . bin2hex( $it );
		}
		print_r( $arr );
	}

	static function PadString( string $data ) : string 
	{
		$data .= "\0";
		$len = strlen( $data );
		while( $len % 4 != 0 )
		{
			$len++;
			$data .= "\0";
		}
		return $data;
	}

	static function Getfloat( string $data ) : float 
	{
		$pos_comma = strpos( $data, "," );
		if( $pos_comma == 0 ) return 0;

		if( $data[ $pos_comma + 1] != "f" ) return 0;

		$number = unpack( "GNumber", substr( $data, $pos_comma + 4, 4 ));

		return $number["Number"];
	}

	static function GetString( string $data ) : string
	{	
		$pos_comma = strpos( $data, "," );
		if( $pos_comma == 0 ) return "";
		if( $data[ $pos_comma + 1] != "s" ) return "";

		$end = strpos( $data, "\0", $pos_comma + 4 );

		return substr( $data, $pos_comma + 4, $end - $pos_comma - 4 );
	}

	static function GetInt( string $data ) : int 
	{
		$pos_comma = strpos( $data, "," );
		if( $pos_comma == 0 ) return -1;
		if( $data[ $pos_comma + 1] != "i" ) return -1;
		
		$number = unpack( "NNumber", substr( $data, $pos_comma + 4, 4 ));

		return $number["Number"];
	}

	static function GetEnum( string $data, array $possible_values ) : int
	{
		$pos_comma = strpos( $data, "," );
		if( $pos_comma == 0 ) return -1;
		if( $data[ $pos_comma + 1] != "s" && $data[ $pos_comma + 1] != "i" ) return -1;
		
		if( $data[ $pos_comma + 1] == "s" )
		{
			$value = self::GetString( $data );
			return array_search( $value, $possible_values, true );
		}
		else
		{
			return self::GetInt( $data );
		}
	}

	static function ConvertFloatToDezibel( float $value ) : float
	{
        if ($value >= 0.5) return $value * 40.0 - 30.0;
        else if ($value >= 0.25) return $value * 80.0 - 50.0;
        else if ($value >= 0.0625) return $value * 160.0 - 70.0;
        else if ($value >= 0.0) return $value * 480.0 - 90.0;
        return 0;
	}

	static function ConvertDezibelToFloat( float $dB ) : float
	{
		if ($dB < -60 ) return ($dB + 90) / 480;
        else if ($dB < -30) return ($dB + 70) / 160;
        else if ($dB < -10) return ($dB + 50) / 80;
        else if ($dB <= 10 ) return ($dB + 30) / 40;
        return 0;
	}

	static function XPrintType( string $type, $data )
	{
		$retval = "";
		if( $type == "f" )
		{
			$retval = self::PadString( ",f" );
			$retval .= pack( "G", $data );
		}
		else if( $type == "s" )
		{
			$retval = self::PadString( ",s" );
			$retval .= self::PadString( $data );
		}
		else if( $type == "i" )
		{
			$retval = self::PadString( ",i" );
			$retval .= pack( "N", $data );
		}

		return $retval;
		
	}

	function GetLevelSendToMixbus( int $channel, int $mixbus ) : float 
	{
		$tosend = sprintf( "/ch/%02d/mix/%02d/level", $channel, $mixbus );
		$tosend = self::PadString( $tosend );

		// Debug
		self::DebugPrint( $tosend );

		fwrite( $this->fp, $tosend );

		$data = fread( $this->fp, 1000 );

		self::DebugPrint( $data );

		$value = self::Getfloat( $data );

		//return self::ConvertFloatToDezibel($value);
		return $value;
	}

	function SetLevelSendToMixBus( int $channel, int $mixbus, float $level ) : bool
	{
		$tosend = self::PadString( sprintf( "/ch/%02d/mix/%02d/level", $channel, $mixbus ));
		//$tosend .= self::XPrintType( "f", self::ConvertDezibelToFloat($level) );
		$tosend .= self::XPrintType( "f", $level );

		fwrite( $this->fp, $tosend );
		self::DebugPrint( $tosend );
		return true;
	}

	function GetMuteSendToMixBus( int $channel, int $mixbus ) : int
	{
		$tosend = self::PadString( sprintf( "/ch/%02d/mix/%02d/on", $channel, $mixbus ));
		fwrite( $this->fp, $tosend );

		$data = fread( $this->fp, 1000 );
		self::DebugPrint( $data );
		$value = self::GetEnum( $data, array( "OFF", "ON" ));
		return $value;
	}

	function SetMuteSendToMixBus( int $channel, int $mixbus, bool $on_or_off )
	{
		$tosend = self::PadString( sprintf( "/ch/%02d/mix/%02d/on", $channel, $mixbus ));
		$tosend .= self::XPrintType( "i", (int)$on_or_off );
		self::DebugPrint( $tosend );
		fwrite( $this->fp, $tosend );
		return true;
	}

	function GetChannelName(  int $channel )
	{
		$tosend = self::PadString( sprintf( "/ch/%02d/config/name", $channel ) );
		fwrite( $this->fp, $tosend );

		$data = fread( $this->fp, 1000 );
		self::DebugPrint($data);

		$value = self::GetString($data);
		return $value;
	}

	function GetChannelColor( int $channel )
	{
		$tosend = self::PadString( sprintf( "/ch/%02d/config/color", $channel ) );
		fwrite( $this->fp, $tosend );

		$data = fread( $this->fp, 1000 );
		self::DebugPrint($data);

		$value = self::GetEnum($data, array( "OFF", "RD", "GN", "YE", "BL", "MG", "CY", "WH", "OFFi",
		"RDi", "GNi", "YEi", "BLi", "MGi", "CYi", "WHi" ) );
		return $value;
	}

	function GetMixBusOnOrOff( int $mixbus )
	{
		$tosend = self::PadString( sprintf( "/bus/%02d/mix/on", $mixbus ) );
		fwrite( $this->fp, $tosend );

		$data = fread( $this->fp, 1000 );
		self::DebugPrint($data);

		$value = self::GetEnum($data, array( "OFF", "ON" ) );
		return $value;
	}

	function SetMixBusOnOrOff( int $mixbus, bool $on_or_off )
	{
		$tosend = self::PadString( sprintf( "/bus/%02d/mix/on", $mixbus ));
		$tosend .= self::XPrintType( "i", (int)$on_or_off );
		self::DebugPrint( $tosend );
		fwrite( $this->fp, $tosend );
		return true;
	}

	function GetMixBusFaderLevel( int $mixbus )
	{
		$tosend = self::PadString( sprintf( "/bus/%02d/mix/fader", $mixbus ) );
		fwrite( $this->fp, $tosend );

		$data = fread( $this->fp, 1000 );
		self::DebugPrint($data);

		$value = self::Getfloat($data );
		//return self::ConvertFloatToDezibel($value);
		return $value;
	}

	function SetMixBusFaderLevel( int $mixbus, float $level )
	{
		$tosend = self::PadString( sprintf( "/bus/%02d/mix/fader", $mixbus ));
		//$tosend .= self::XPrintType( "f", self::ConvertDezibelToFloat( $level ) );
		$tosend .= self::XPrintType( "f", $level );

		fwrite( $this->fp, $tosend );
		self::DebugPrint( $tosend );
		return true;
	}
}

