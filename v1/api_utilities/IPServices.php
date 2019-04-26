<?php
/**
* IPServices Class
*
* @copyright Ian Foose Foose Industries
* @version 1.0
*/

require_once('APIHelper.php');

class IPServices extends APIHelper {
	/**
	* Main Constructor
	*
	* @return void
	*/
	public function __construct($expose=true) {
		if($expose) {
			$this->exposeAPI();
		}
	}

	/**
	* Exposes functions to the API
	*
	* @return void
	*/
	public function exposeAPI() {
		Router::get('/visits', function($req, $res) {
			try {
				$res->output($this->getVisitors(@$_GET['since_id'], @$_GET['max_id'], @$_GET['limit'], @$_GET['deleted']));
			} catch (Exception $e) {
				return new Response($e);
			}
		});

		Router::get('/visits/count/number', function($req, $res) {
			try {
				$res->output($this->getTotalNumberOfVisits(@$_GET['deleted']));
			} catch (Exception $e) {
				return new Response($e);
			}
		});

		Router::get('/visit/:id', function($req, $res) {
			try {
				$res->output($this->getVisitor(@$req['params']['id']));
			} catch (Exception $e) {
				return new Response($e);
			}
		});

		Router::get('/visits/search/:query', function($req, $res) {
			try {
				$res->output($this->search);
			} catch (Exception $e) {
				return new Response($e);
			}
		});

		Router::put('/visit', function($req, $res) {
			try {
				$res->output($this->logVistitor());
			} catch (Exception $e) {
				return new Response($e);
			}
		});
	}

	/**
	* Get IP address of a user
	*
	* @return string
	*/
	public static function getIP() {
		$ipRemote = @$_SERVER['REMOTE_ADDR'];
		$httpClientIP = @$_SERVER['HTTP_CLIENT_IP'];
		$httpXforwardedFor = @$_SERVER['HTTP_X_FORWARDED_FOR'];
		if(!empty(@$httpClientIP)) {
			return $httpClientIP;
		} else if(!empty(@$httpXForwardedFor)) {
			return $httpXForwardedFor;
		} 
		return $ipRemote;
	}

	/**
	* Get web client
	*
	* @return string
	*/
	public static function getClient() { 
		return $_SERVER['HTTP_USER_AGENT']; 
	}

	/**
	* Logs a visitor
	*
	* @return string
	* @throws Exception
	*/
	public function logVistitor() {
		try {
			$ip = IPServices::getIP();
			$client = IPServices::getClient();
			
			if(self::$dataHelper->query("INSERT INTO ".$this->tables['traffic']." SET ip=:ip,client=:client", array(':ip'=>$ip,':client'=>$client))) {
				return 'Logged';
			}	
		} catch(Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets a visitor by ID
	*
	* @param int $id Visitor ID
	* @return object
	* @throws Exception
	*/
	public function getVisitor($id) {
		try {
			if($result = self::$dataHelper->find('*', array('id'=>$id), $this->tables['traffic'])) { 
				return self::getVisitorData($result);
			}
		} catch(Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets visistors
	*
	* @param int $sinceID Since ID
	* @param int $maxID Max ID
	* @param int $limit Limit
	* @return array
	* @throws Exception
	*/
	public function getVisitors($sinceID=0, $maxID=0, $limit=35) {
		try {
			$o = self::$dataHelper->getOffset($this->tables['traffic'], $id='id', $sinceID, $maxID, null, $limit);

			if($result = self::$dataHelper->query("SELECT * FROM ".$this->tables['traffic'].$o[0],$o[1])) {
				$visits = array();

				while($visit = $result->fetch()) {
					$visits[] = self::getVisitorData($visit);
				}

				return $visits;
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets visitor data
	*
	* @param object $item Visitor Object 
	* @return object
	*/
	public function getVisitorData($item) {
		if(!empty($item['date']))
			$item['string_date'] = formateDate($item['date']);

		return $item;
	}

	/**
	* Gets total number of visitors
	*
	* @return int
	* @throws Exception
	*/
	public function getTotalNumberOfVisits() {
		try {
			if($result = self::$dataHelper->query("SELECT id FROM ".$this->tables['traffic'])) {
				return $result->rowCount();
			}
		} catch(Exception $e) {
			throw $e;
		}
	}
}
?>
