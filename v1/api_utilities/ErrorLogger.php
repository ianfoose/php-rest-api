<?php
/**
* Error Logging Class
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

		if(!defined('ERRORS')) {
			$errorMsg = 'Errors table is not defined.';

			if($this->configs['environment'] == 'production') {
				$errorMsg = 'Database Error';
			}

            if($this->configs['log_errors'] == true) {
			    throw new Exception($errorMsg, 500);
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
			 	$res->send($this->getErrors($this->getQueryDirection(), $this->getQueryOffset(), $this->getQueryLimit()));
			} catch (Exception $e) {
				$res->send($e);
			}
		}, 'errors');

		Router::get('/error/:id', function($req, $res) {
			try {
				$res->send($this->getError($req->params['id']));
			} catch (Exception $e) {
				$res->send($e);
			}
		}, 'errors');

		Router::get('/errors/count/number', function($req, $res) {
			try {
				$res->send($this->getNumberOfErrors($_GET['deleted']));
			} catch (Exception $e) {
				$res->send($e);
			}
		}, 'errors');

		Router::get('/errors/search/:query', function($req, $res) {
			try {
				$res->send($this->searchErrors($req->params['q'], $this->getQueryDirection(), $this->getQueryOffset(), $this->getQueryLimit()));
			} catch (Exception $e) {
				$res->send($e);
			}
		}, 'errors');

		Router::delete('/error/:id', function($req, $res) {
		 	try {
		 		$res->send($this->deleteError($req->params['id'], $req->body['soft']));
		 	} catch (Exception $e) {
		 		$res->send($e);
		 	}
		}, 'errors');
	}

	/**
	* Log an error
	*
	* @param string $errorCode Error Code
	* @param string $errorDescription Error Description
	* @param string $message Error Message
	* @return string
	* @throws Exception
	*/
	public function logError($code, $description='', $message='') {
		if($this->configs['log_errors'] == true) {
			try {
				if(self::$db->query("INSERT INTO ".ERRORS." SET code=:code,description=:description,message=:message",
				    array(':code'=>$code,':description'=>$description,':message'=>$message), true)) {
					return 'Error Logged';
				} 
			} catch (Exception $e) {
				throw $e;
			}
		}
	}

	/**
	* Searches errors
	*
	* @param string $q Query string
	* @param string $order Pagination order
	* @param int $offset Pagination offset
	* @param int $limit Pagination limit
	* @return array
	* @throws Exception
	*/
	public function searchErrors($q, $direction='ASC', $offset=0, $limit=40) {
		try {
			$queryString = "SELECT * FROM ".ERRORS." WHERE code LIKE CONCAT('%',:q,'%') OR message LIKE CONCAT('%',:a,'%') OR description LIKE CONCAT('%',:b,'%')";
			$params = array(':q'=>$q, ':a'=>$q, ':b'=>$q, ':offset'=>$offset, ':limit'=>$limit);

			$result = self::$db->query($queryString." ORDER BY id $direction LIMIT :offset,:limit", $params);
			$errors = array();

			while($error = $result->fetch()) {
				$errors[] = self::getErrorData($error);
			}

			return $errors;
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets the total number of errors
	*
	* @param string $deleted Deleted flag
	* @return int
	* @throws Exception
	*/
	public function getNumberOfErrors() {
		try {
			$result = self::$db->query("SELECT id FROM ".ERRORS);
			return $result->rowCount();
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets errors
	*
	* @param string $deleted Deleted flag
	* @param string $order Pagination order
    * @param int $offset Pagination offset
	* @param int $limit Pagination limit
	* @return array
	* @throws Exception
	*/
	public function getErrors($direction='ASC', $offset=0, $limit=40) {
		try {
			$params = array(':offset'=>$offset, ':limit'=>$limit);
			$result = self::$db->query("SELECT * FROM ".ERRORS." ORDER BY id $direction LIMIT :offset,:limit",$params);
			$errors = array();

			while($error = $result->fetch()) {
				$errors[] = self::getErrorData($error);
			}

			return $errors;
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**/**
	* Deletes an error
	*
	* @param int $errorID Error ID
	* @param bool $soft Soft delete flag
	* @return string
	* @throws Exception
	*/
	public function deleteError($errorID, $soft=true) {
		try {
			if(self::$db->find('id', array('id'=>$errorID), ERRORS, 'Error')) {
		        if($soft) {
                    self::$db->query("UPDATE ".ERRORS." SET deleted='1' WHERE id=:id", array(':id'=>$errorID));
                    return 'Error Deleted';
                } else {
                     self::$db->query("DELETE FROM ".ERRORS." WHERE id=:id", array(':id'=>$errorID));
                     return 'Error Permanently Deleted';
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
			$result = self::$db->find('*', array('id'=>$errorID), ERRORS, 'Error', true);
			return self::getErrorData($result);
			
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
		if(array_key_exists('date', $error)) { 
			$error['string_date'] = $this->formatDate($error['date']);
		}
		
		return $error;
	}
}
?>
