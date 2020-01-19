<?php
require_once('api_utilities/APIHandler.php');

class API extends APIHandler {

	public function __construct() {
		parent::__construct();

		Router::all('/', function($req, $res) {
			$res->send($this->configs['name']);
		}, false, false, true);

		$this->addAuthHandler(array('default', 'errors', 'email_templates', 'email_subscriptions', 'local_notifications'), function($req, $res) {
			// defaults to `false` for security to disallow any connection
			return false;
		});

		$this->addRateLimitHandler(array('default'), function($req, $res) {
			$rateLimiter = new RateLimiter();
			return $rateLimiter->rateLimitByIP();
		});
	}
}

// start the api
$api = new API();

$api->run();
?>
