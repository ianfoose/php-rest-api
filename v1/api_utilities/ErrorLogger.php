<?php
/**
*	Error Logging Class
*
* @version 1.0
*/

require_once('DatabaseHelper.php');
require_once('Response.php');
require_once('./TableNames.php');

class ErrorLogger {
	private static $dataHelper;

	public function __construct() { 
		/* DEFAULT */	
	}

	/**
	* Connects to a database
	*
	* @return bool
	*/
	private static function connect() {
		if(!empty(ERROR_URL) && !empty(ERROR_USER) && !empty(ERROR_PASSWORD) && !empty(ERROR_DB) && !empty(ERRORS)) {
			if(self::$dataHelper == null) {
				self::$dataHelper = new DatabaseHelper(ERROR_URL,ERROR_USER,ERROR_PASSWORD,ERROR_DB);
			} 
			return true;
		}
		return false;
	}

	/**
	* Log an error
	*
	* @param string $errorCode Error Code
	* @param string $errorDescription Error Description
	* @return string | Response
	*/
	public static function logError($code,$description="",$message="") {
		if(self::connect()) {
			if(self::$dataHelper->query("INSERT INTO ".ERRORS." SET code=:code,description=:description,message=:message",array(':code'=>$code,':description'=>$description,':message'=>$message))) {
				return 'Error Logged';
			} 
			throw new Excepetion(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
		}
	}

	/**
	* Searches errors
	*
	* @param string $q Query string
	* @return array | Response
	*/
	public static function searchErrors($q) {
		if(self::connect()) {
			if($result = self::$dataHelper->query("SELECT * FROM ".ERRORS." WHERE code LIKE CONCAT('%',:q,'%') OR message LIKE CONCAT('%',:a,'%') OR description LIKE CONCAT('%',:b,'%')",array(
				':q'=>$q,
				':a'=>$q,
				':b'=>$q))) {

				$errors = array();

				while($error = $result->fetch()) {
					$errors[] = self::getErrorData($error);
				}

				return $errors;
			}
			throw new Excepetion(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
		}
	}

	/**
	* Gets the total number of errors
	*
	* @param boolean $deleted Deleted
	* @return int
	*/
	public static function getNumberOfErrors($deleted=null) {
		if(self::connect()) {
			$queryString = "SELECT id FROM ".ERRORS;

			$params = array();

			if($deleted != null) {
				$queryString .= ' and deleted=:d';
				$params[':d'] = $deleted;
			}

			if($result = self::$dataHelper->query($queryString,$params)) {
				return $result->rowCount();
			}
			throw new Excepetion(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
		}
	}

	/**
	* Gets errors
	*
	* @param int $startID Start ID
	* @param boolean $old Old
	* @param int $limit Limit
	* @param array $filters Filters array
	* @param boolean $deleted Deleted
	* @return array | string
	*/
	public static function getErrors() {
		if(self::connect()) {
			$o = DatabaseHelper::getOffset(getSinceID(),getMaxID(),ERRORS,'id',getLimit());

			if($result = self::$dataHelper->query("SELECT * FROM ".ERRORS." WHERE".$o[0],$o[1],true)) {
				$errors = array();

				while($error = $result->fetch()) {
					$errors[] = self::getErrorData($error);
				}

				return $errors;
			}
			throw new Excepetion(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
		}
	}

	/**
	* Deletes an error
	*
	* @param int $errorID Error ID
	* @param int $userID User ID
	* @return string
	*/
	public static function deleteError($errorID,$userID) {
		if(self::connect()) {
			if(self::$dataHelper->find('id','id',$errorID,ERRORS,'Error')) {
				if(self::$dataHelper->query("UPDATE ".ERRORS." SET deleted='1' WHERE id=:id", array(':id'=>$errorID))) {
					return 'Error Deleted';
				}
			}
			throw new Excepetion(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
		}
	}

	/**
	* Gets an error
	*
	* @param int $errorID Error ID
	* @param boolean $deleted Deleted
	* @return Error Object | string
	*/
	public static function getError($errorID) {
		if(self::connect()) {
			if($result = self::$dataHelper->find('*','id',$errorID,ERRORS,'Error')) {
				return self::getErrorData($result);
			}
			throw new Excepetion(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
		}
	}

	/**
	* Get error data
	*
	* @param Error Object $error 
	* @param boolean $deleted Deleted
	* @return Error Object
	*/
	private static function getErrorData($error) {
		if(!empty($error['date']))
			$error['string_date'] = formatDate($error['date']);
		
		return $error;
	}
}
?>