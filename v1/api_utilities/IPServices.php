<?php
/**
* IPServices Class
*
* @copyright Ian Foose Foose Industries
* @version 1.0
*/

include_once('./TableNames.php');

class IPServices {
	/**
	* Get IP address of a user
	*
	* @return string
	*/
	public static function getIP() {
		$ipRemote = @$_SERVER['REMOTE_ADDR'];
		$httpClientIP = @$_SERVER['HTTP_CLIENT_IP'];
		$httpXforwardedFor = @$_SERVER['HTTP_X_FORWARDED_FOR'];
		if(!empty($httpClientIP)) {
			return $httpClientIP;
		} else if(!empty($httpXForwardedFor)) {
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
	* @return boolean
	*/
	public function logVistitor() {
		$ip = IPServices::getIP();
		$client = IPServices::getClient();
		if(self::$dataHelper->query("INSERT INTO ".VISITORS." SET ip='$ip',client='$client'")) {
			return 'Logged';
		}	
		throw new Exception(self::$dataHelper->errorMessage,self::$dataHelper->errorCode);
	}

	/**
	* Gets a visitor by ID
	*
	* @param int $id Visitor ID
	* @return object | Exception
	*/
	public function getVisitor($id) {
		if($result = self::$dataHelper->find('*','id',$id,VISITORS)) { 
			return self::getVisitorData($result);
		}
		throw new Exception(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
	}

	/**
	* Gets visistors
	*
	* @param int $sinceID Since ID
	* @param int $maxID Max ID
	* @param int $limit Limit
	* @return array | Exception
	*/
	public function getVisitors($sinceID=0, $maxID=0, $limit=35) {
		$o = self::$dataHelper->getOffset($sinceID,$maxID,VISITORS,'id',$limit);

		if($result = self::$dataHelper->query("SELECT * FROM ".VISITORS.$o[0],$o[1])) {
			$visits = array();

			while($visit = $result->fetch()) {
				$visits[] = self::getVisitorData($visit);
			}

			return $visits;
		}
		throw new Exception(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
	}

	/**
	* Gets visitor data
	*
	* @param $item object Visitor Object 
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
	* @return int | exception
	*/
	public function getTotalNumberOfVisits() {
		if($result = self::$dataHelper->query("SELECT id FROM ".VISITORS)) {
			return $result->rowCount();
		}
		throw new Exception(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
	}
}
?>
