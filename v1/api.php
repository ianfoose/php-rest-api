<?php
require_once('api_utilities/APIHandler.php');

class API extends APIHandler {

	public function __construct() {
		parent::__construct(); 

		Router::all('/',function($req, $res) {
			$res->output('My API');
		});

		$this->addAuthHandler('default', function($req, $res) {
			// defaults to `false` for security to disallow any connection
			return false;
		});

		Router::get('/test', function($req, $res) {
			$res->send($this->saveAuditLog(32,'user',2, 'edited'));
		});

		router::get('/test2', function($req, $res) {
			try {
				$res->send($this->getAuditLogs(null,null,null,USERS));
			} catch(Exception $e) {
				$res->send($e);
			}
		});
	}
}

new NotificationServices(null,true);

// start the api
$api = new API();

$api->run();
?>
