<?php
error_reporting(E_ALL); // Error Reporting 
ini_set('display_errors', 1); // CHECK BEFORE RELEASING

include_once('api_utilities/APIHelper.php');

date_default_timezone_set('UTC'); // important

class API extends APIHelper {
	
	public function __construct() {
		$this->scheme = '/api/v1';
		parent::__construct(); 

		self::$tokens = new TokenServices();

		self::$emailServices = new EmailServices();

		Router::set('/',function() {
			header("Access-Control-Allow-Origin: *");
		});

		Router::all('/',function() {
			return 'API';
		});
	}
}

$api = new API();
$api->run();
?>