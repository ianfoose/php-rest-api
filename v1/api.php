<?php
require_once('api_utilities/APIHandler.php');

class API extends APIHandler {

	public function __construct() {
		parent::__construct(); 
			
		Router::all('/',function($req, $res) {
			$res->output('My API');
		});

		$this->addAuthHandler('default', function($req, $res) {
			return true;
		});
	}
}

// start the api
$api = new API();

$api->run();
?>
