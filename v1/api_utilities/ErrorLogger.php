<?php
/**
*	Error Logging Class
*
* @version 1.0
*/

require_once('APIHelper.php');

class ErrorLogger extends APIHelper {
	/**
	* Main Constructor
	*
	* @return void
	*/
	public function __construct($expose=true) { 
		parent::__construct();

		if(!empty(@$this->configs['error_logging'])) {
			$errorsCfg = $this->configs['error_logging'];

			if(!empty(@$errorsCfg['url']) && !empty(@$errorsCfg['username']) && !empty(@$errorsCfg['password'])) {

				self::$dataHelper = new DatabaseHelper($errorsCfg['url'],@$errorsCfg['port'],$errorsCfg['username'],$errorsCfg['password'],@$errorsCfg['database']);
			} else {
				throw new Exception('Check Error Logging Configs', 500);
			}
		} else { // default to regular credentials
			if(!empty(@$configs['database'])) {
				$dbConfigs = $this->configs['database'];

				if(!empty(@$dbConfigs['url']) && !empty(@$dbConfigs['username']) && !empty(@$dbConfigs['password'])) {
					self::$dataHelper = new DatabaseHelper($dbConfigs['url'],@$dbConfigs['port'],$dbConfigs['username'],$dbConfigs['password'],@$dbConfigs['database']);
				}
			} else {
				throw new Exception('Check Error Logging Configs', 500);
			}
		}

		// expose API methods
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
		Router::get('/errors', function($req, $res) {
			try {
			 	$res->output($this->getErrors(@$_GET['since_id'], @$_GET['max_id'], @$_GET['limit'], @$_GET['deleted']));
			} catch (Exception $e) {
				return new Response($e);
			}
		});

		Router::get('/error/:id', function($req, $res) {
			try {
				$res->output($this->getError(@$req['params']['id']));
			} catch (Exception $e) {
				return new Response($e);
			}
		});

		Router::get('/errors/count/number', function($req, $res) {
			try {
				$res->output($this->getNumberOfErrors(@$_GET['deleted']));
			} catch (Exception $e) {
				return new Response($e);
			}
		});

		Router::get('/errors/search/:query', function($req, $res) {
			try {
				$res->output($this->searchErrors($req['params'][':q'], @$_GET['offset']));
			} catch (Exception $e) {
				return new Response($e);
			}
		});

		Router::delete('/error/:id', function($req, $res) {
			try {
				$res->output($this->deleteError($req['params'][':id']));
			} catch (Exception $e) {
				return new Response($e);
			}
		});
	}

	/**
	* Log an error
	*
	* @param string $errorCode Error Code
	* @param string $errorDescription Error Description
	* @return string
	* @throws Exception
	*/
	public function logError($code,$description='',$message='') {
		if(@$this->configs['log_error'] == true) {
			try {
				if(self::$dataHelper->query("INSERT INTO ".$this->tables['errors']." SET code=:code,description=:description,message=:message",array(':code'=>$code,':description'=>$description,':message'=>$message), true)) {
					return 'Error Logged';
				} 
			} catch (Excepetion $e) {
				throw $e;
			}
		}
	}

	/**
	* Searches errors
	*
	* @param string $q Query string
	* @return array
	* @throws Exception
	*/
	public function searchErrors($q, $offset) {
		try {
			$queryString = "SELECT * FROM ".$this->tables['errors']." WHERE code LIKE CONCAT('%',:q,'%') OR message LIKE CONCAT('%',:a,'%') OR description LIKE CONCAT('%',:b,'%')";
			$params = array(':q'=>$q, ':a'=>$q, ':b'=>$q);

			if(!empty(@$offset)) {
				$queryString .= " OFFSET=:offset";
				$params[':offset'] = $offset;
			}

			if($result = self::$dataHelper->query($queryString, $params)) {
				$errors = array();

				while($error = $result->fetch()) {
					$errors[] = self::getErrorData($error);
				}

				return $errors;
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets the total number of errors
	*
	* @param string $deleted Deleted
	* @return int
	* @throws Exception
	*/
	public function getNumberOfErrors($deleted=null) {
		try {
			$queryString = "SELECT id FROM ".$this->tables['errors'];

			$params = array();

			if($deleted != null) {
				$queryString .= ' and deleted=:d';
				$params[':d'] = $deleted;
			}

			if($result = self::$dataHelper->query($queryString,$params)) {
				return $result->rowCount();
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets errors
	*
	* @param int $startID Start ID
	* @param int $maxID Max ID
	* @param int $limit Limit
	* @param array $filters Filters array
	* @param string $deleted Deleted
	* @return array
	* @throws Exception
	*/
	public function getErrors($sinceID=0, $maxID=0, $limit, $deleted) {
		try {
			$o = self::$dataHelper->getOffset($this->tables['errors'], 'id', $sinceID, $maxID, @$deleted, @$limit);

			if($result = self::$dataHelper->query("SELECT * FROM ".$this->tables['errors']." WHERE".$o[0],$o[1],true)) {
				$errors = array();

				while($error = $result->fetch()) {
					$errors[] = self::getErrorData($error);
				}

				return $errors;
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Deletes an error
	*
	* @param int $errorID Error ID
	* @param int $userID User ID
	* @return string
	* @throws Exception
	*/
	public function deleteError($errorID,$userID) {
		try {
			if(self::$dataHelper->find('id', array('id'=>@$errorID), $this->tables['errors'], 'Error')) {
				if(self::$dataHelper->query("UPDATE ".$this->tables['errors']." SET deleted='1' WHERE id=:id", array(':id'=>$errorID))) {
					return 'Error Deleted';
				}
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets an error
	*
	* @param int $errorID Error ID
	* @param string $deleted Deleted
	* @return object
	* @throws Exception
	*/
	public function getError($errorID) {
		try {
			if($result = self::$dataHelper->find('*', array('id'=>@$errorID), $this->tables['errors'],'Error')) {
				return self::getErrorData($result);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Get error data
	*
	* @param Error Object $error 
	* @return Error Object
	*/
	private function getErrorData($error) {
		if(!empty($error['date']))
			$error['string_date'] = formatDate($error['date']);
		
		return $error;
	}
}
?>
