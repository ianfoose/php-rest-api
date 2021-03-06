<?php
/**
* IPServices Class
*
* @copyright Ian Foose Foose Industries
* @version 1.0
*/

require_once(dirname(dirname(dirname(__FILE__))).'/api_utilities/APIHelper.php');

class IPServices extends APIHelper {
	/**
	* Main Constructor
	*
	* @return void
	*/
	public function __construct($expose=true) {
		parent::__construct();

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
		Router::get('/traffic', function($req, $res) {
			$res->send($this->getTraffic($this->getRequestValue($_GET, 'deleted', 0), $this->getQueryDirection(), $this->getQueryOffset(), $this->getQueryLimit()));
		}, 'traffic');

		Router::get('/traffic/count/number', function($req, $res) {
			$res->send($this->getTrafficTotal($this->getRequestValue($_GET, 'deleted', 0)));
		}, 'traffic');

		Router::get('/traffic/:id', function($req, $res) {
			$res->send($this->getTrafficByID($req->params['id']));
		}, 'traffic');

		Router::get('/traffic/search/:query', function($req, $res) {
			$res->send($this->searchVisitors($req->params['query'], $this->getQueryDirection(), $this->getQueryOffset(), $this->getQueryLimit()));
		}, 'traffic');

		// PUBLIC ENDPOINT
		Router::put('/visit', function($req, $res) {
			$res->send($this->logTraffic());
		}, 'traffic_visit');
	}

	/**
	* Get IP address of a user
	*
	* @return string
	*/
	public function getIP() {
		if(array_key_exists('REMOTE_ADDR', $_SERVER)) {
			return $_SERVER['REMOTE_ADDR'];
		}

		if(array_key_exists('HTTP_CLIENT_IP', $_SERVER)) {
			return $_SERVER['HTTP_CLIENT_IP'];
		}

		if(array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
	}

	/**
	* Get web client
	*
	* @return string
	*/
	public function getClient() { 
		return $_SERVER['HTTP_USER_AGENT']; 
	}

	/**
	* Logs a visitor
	*
	* @return string
	* @throws Exception
	*/
	public function logTraffic() {
		try {
			$ip = $this->getIP();
			$client = $this->getClient();
			
			self::$db->query("INSERT INTO ".TRAFFIC." SET ip=:ip,client=:client", array(':ip'=>$ip, ':client'=>$client));
			return 'Traffic Logged';	
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
	public function getTrafficByID($id) {
		try {
			if($result = self::$db->find('*', array('id'=>$id), TRAFFIC)) { 
				return self::getTrafficData($result);
			}
		} catch(Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets visitors
	*
	* @param string $deleted Deleted flag
    * @param string $order Pagination order
	* @param int $offset Pagination offset
	* @param int $limit Pagination Limit
	* @return array
	* @throws Exception
	*/
	public function getTraffic($deleted='', $direction='ASC', $offset=0, $limit=40) {
		try {
			$params = array(':deleted'=>$deleted, ':offset'=>$offset, ':limit'=>$limit);

			$result = self::$db->query("SELECT * FROM ".TRAFFIC." WHERE deleted=:deleted ORDER BY id $direction LIMIT :offset,:limit", $params);
			$visits = array();

			while($visit = $result->fetch()) {
				$visits[] = self::getTrafficData($visit);
			}

			return $visits;
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Searches site visitors
	*
	* @param string $query Search query
	* @param string $order Pagination order
	* @param int $offset Pagination offset
	* @param int $limit Pagination limit
	* @return array
	* @throws Exception
	*/
	public function searchTraffic($query, $order='ASC', $offset=0, $limit=40) {
		try {
			$results = self::$db->query("SELECT * FROM ".TRAFFIC." WHERE ip LIKE CONCAT('%',:ip,'%') OR client LIKE CONCAT('%',:c,'%') ORDER BY id $order LIMIT :offset,:limit",
			    array(':ip'=>$query, ':c'=>$query, ':offset'=>$offset, ':limit'=>$limit));
			
			$logs = array();

			while($log = $results->fetch()) {
				$logs[] = $this->getTrafficData($log);
			}

			return $logs;
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
	public function getTrafficData($item) {
		if(!empty($item['date'])) {
			$item['string_date'] = $this->formatDate($item['date']);
		}

		return $item;
	}

	/**
	* Gets total number of visitors
	*
	* @return int
	* @throws Exception
	*/
	public function getTrafficTotal() {
		try {
			$result = self::$db->query("SELECT id FROM ".TRAFFIC);
			return $result->rowCount();
		} catch(Exception $e) {
			throw $e;
		}
	}
}
?>
