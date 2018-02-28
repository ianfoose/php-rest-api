<?php
/**
* DatabaseHelper
*
* @copyright Ian Foose - Foose Industries
* @version 1.0
*/

require_once('utilities.php');

session_start();

class DatabaseHelper {
	private $dbURL;
	private $dbUser;
	private $dbPassword;
	private $dbName;

	public $insertID;
	public $errorMessage = "Database Error";
	public $errorCode = 500;

	private $transaction = false;
	protected static $db;

	/**
	* Intializer
	*
	* @param $url String URL
	* @param $user String User
	* @param $password String Password
	* @param $dbName String Database Name
	* 
	* @return void
	*/
	public function __construct($url, $user, $password, $dbName) {
		$this->dbURL = $url;
		$this->dbUser = $user;
		$this->dbPassword = $password;
		$this->dbName = $dbName;
	}

	/**
	* Returns a database connection
	*
	* @return Database Connection
	*/
	public function getDB() {
		if(self::$db != null) {
			return self::$db;
		} 
		return false;
	}

	/**
	* Sets up a mysqli database connection
	*
	* @return Error | DB Connection
	*/
	public function connect() {
		$this->resetError();
		if(self::$db != null) {
			return self::$db;
		} else {
			try {
				self::$db = new PDO("mysql:host=".$this->dbURL.";dbname=".$this->dbName.";",$this->dbUser,$this->dbPassword);
				self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				self::$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
				self::$db->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );

				return self::$db;
			} catch(Exception $e) {
				$this->errorMessage = 'Unable to connect to db';
				try {
					ErrorLogger::logError(500,$this->errorMessage,$e->getMessage()); 
				} catch(Exception $e) {
					return false;
				}
				return false;
			} 
		}
	}

	/**
	* Resets error variables
	*
	* @return void
	*/
	private function resetError() {
		$this->errorCode = 500;
		$this->errorMessage = 'Database Error';
	}

	/**
	* Finds a data row
	*
	* @param $keys string Keys to be retrieved
	* @param $cols string Columns to be checked against
	* @param $vals string | array Values to check columns against
	* @param $tbl string Table name to query
	* @param $message string Name of item being found, default 'item'
	* @return Bool | Response
	*/
	public function find($keys, $cols, $vals, $tbl,$message='item') {
		$this->resetError();
		$cols = explode(',',$cols);
		
		if(!is_array($vals))
			$vals = explode(',',$vals);

		if(count($cols) == count($vals)) {
			$queryString = "SELECT ".$keys." FROM ".$tbl." WHERE ";
			$params = array();

			$j = 0;
			foreach ($cols as $key => $value) {
				$queryString .= $value."=:".$value;
				$params[':'.$value] = $vals[$key];

				if($j < count($cols)-1) {
					$queryString .= " AND ";
				}

				$j++;
			}

			if($result = self::query($queryString,$params)) {
				if($result->rowCount() == 1) {
					return $result->fetch();
				}
			}
			$this->errorMessage = $message.' not found';
			$this->errorCode = 404;
			return false;
		} 
		return false;
	}

	/**
	* Queries the database
	*
	* @param string $query Query String
	* @param bool $output Outputs the error
	* @return MySQLi result | Error Object
	*/
	public function query($query, $params=null, $useResult=false) {
		$this->resetError();
		if(!empty($query)) {
			if(self::connect()) {
				if($useResult)
					self::$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

				try {
					$statement = self::connect()->prepare($query);
           			$statement->execute($params);

           			$this->insertID = self::$db->lastInsertId();

					return $statement;
				} catch(Exception $e) {
					try {
						ErrorLogger::logError(500,$e->getMessage());
					} catch(Exception $e) {
						return false;
					}

					if($this->transaction) // auto rollback
	                	self::rollback();

	                $this->errorMessage = $e->getMessage();
					return false;
				}
			} 
		}
	}

	/**
	* Begin a transaction
	*
	* @return void
	*/
	public function beginTransaction() {
		if($this->transaction) {
			return true;
		} else {
			try {	
				self::connect()->beginTransaction();
				$this->transaction = true;
				return true;
			} catch(Exception $e) {
				$this->errorMessage = $e->getMessage();
				return false;
			}
		}
	}

	/**
	* Rollback a transaction
	*
	* @return void
	*/
	public function rollback() {
		try {
			self::connect()->rollback();
			$this->transaction = false;
			return true;
		} catch(Exception $e) {
			$this->errorMessage = $e->getMessage();
			return false;
		}
	}

	/**
	* Commit a transaction
	*
	* @return void
	*/
	public function commit() {
		try {
			self::connect()->commit();
			$this->transaction = false;
			return true;
		} catch(Exception $e) {
			$this->errorMessage = $e->getMessage();
			return false;
		}
	}

	/**
	* Builds a date filter query string
	*
	*
	*
	* @param
	* @return string
	*/
	public static function returnDateQuery($col, $startDate, $endDate) {
		if(!empty($startDate)) {
			$startDateStamp = strtotime($startDate);
			$startDate = date("Y-m-d H:i:s", $startDate);

			if(empty($endDate)) { // current date
				$endDate = 'curdate()';
			} else {
				$endDateStamp = strtotime($endDate);
				$endDate = date("Y-m-d H:i:s", $endDateStamp);
			}

			$q = $col.' >= :startDate AND '.$col.' <= :endDate';

			return array($q,array(':endDate'=>$endDate,':startDate'=>$startDate));
		}
		return null;
	}

	/**
	* Gets offset string for query
	*
	* @param int $sinceID Since ID
	* @param int $maxID Max ID
	* @param string $tbl Table name
	* @param string $id ID column name
	* @param int $limit Limit
	* @return string
	*/
	public static function getOffset($sinceID, $maxID, $tbl, $id='id', $limit=null) {
		$params = array();

		$q = " $id <=(SELECT MAX($tbl.$id) FROM $tbl)";
		
		if($sinceID != 0 && $maxID == 0) {
			$q = " $id < :sinceID";
			$params[':sinceID'] = $sinceID;
		} else if($maxID != 0 && $sinceID == 0) { 
			$q = " $id > :maxID";
			$params[':maxID'] = $maxID;
		} else {
			if($maxID != 0 && $sinceID != 0) {
				if($maxID != $sinceID) {
					$params[':sinceID'] = $sinceID;
					$params[':maxID'] = $maxID;

					if($sinceID > $maxID) { // up
						$q = " $id > :maxID AND $id < :sinceID";
					} else { // down
						$q = " $id < :maxID AND $id > :sinceID";
					}
				}
			} 
		}

		$q .= " ORDER BY id DESC";

		if($limit != null) {
			$q.= " LIMIT :limit";
			$params[':limit'] = (int)$limit;
		}

		return array($q,$params);
	}

	/**
	* Builds a query string on deleted parameter filter 
	*
	* @param string $deleted Deleted filter
	* @return string
	*/
	public static function getDeletedQuery($deleted) {
		$baseQueryString = ' deleted=';
		if($deleted == 'true') { // deleted
			return $baseQueryString .= "'1'";
		} else if($deleted == 'false') { // not deleted
			return $baseQueryString .= "'0'";
		} 
		return ''; // mixed
	}

	/**
	* Parse Partial response string
	*
	* @param string $unparsed Input String
	* @return array
	*/
	public function parsePartialResponse($unparsed) {
	    $requestedFields = explode(',', trim($unparsed, '()'));
	    $models = [];
	    
	    foreach ($requestedFields as $key => $fieldName) {
	      // Related model: emails.email,email.id etc.
	      $dotPos = strpos($fieldName, '.');
	      if ($dotPos !== false) {
	        $modelName = substr($fieldName, 0, $dotPos);
	        $fieldName = substr($fieldName, $dotPos + 1);
	        // Add to list of fields for related model
	        if (isset($models[$modelName])) {
	          $models[$modelName][] = $fieldName;
	        } else {
	          $models[$modelName] = [$fieldName];
	        }
	      } else {  // Same model, fields only
	        if (!isset($models['0'])) {
	          $models['0'] = [];
	        }
	        $models['0'][] = $fieldName;
	      }
	    }
	    return $models;
	}

	/**
	* Validates a partial response fields
	*
	* @param array $validFields Valid partial response fields
	* @param array $fields Requested fields
	* @return boolean | array 
	*/
	public function validateResponse($validFields, $fields) {
		$this->resetError();
		if(!empty($validFields) && !empty($fields)) {
			$invalidFields = array();

			foreach ($fields as $key => $field) { 
				if (!in_array($field, $validFields)) {
					$invalidFields[] = $field;
				}
			}

			if(!empty($invalidFields) && count($invalidFields > 0)) {
				$errorDescription = implode(',', $invalidFields);

				$outputSuffix = ' are invalid fields';
				if(count($invalidFields) == 1) {
					$outputSuffix = ' is an invalid field';
				}
				
				$this->errorMessage = $errorDescription.$outputSuffix; 
				return false;
			} else {
				if(!in_array('id', $fields)) {
					$fields[] = 'id';
				}
				return implode(',', $fields);
			}
		}
		$this->errorMessage = 'null data';
		return false;
	}

	/**
	* Validates partial response fields for a certain object and returns approriate fields
	*
	* @param array $fields Requested fields
	* @param array $validFields Valid partial response fields
	* @param string $name The name of the object to check
	* @return boolean | array 
	*/
	public function validator($fields,$validFields,$name) {
		if(!empty($fields)) {
			if($fields = $this->parseString($fields)) {
				if(!array_key_exists($name, $fields)) {
					$checkFields = $fields[0];
				} else {
					$checkFields = $fields[$name]; 
				}

				if($p = self::validateResponse($validFields, $checkFields)) {
					return $p;
				}
			}
			$this->errorCode = 500;
			return false;
		}
		return '*';
	}

	/**
	* Builds a query filter
	*
	* @param array $filters Query Filters
	* @param boolean $query Strict search or general search
	* @return array
	*/
	public static function buildFilter($filters,$query=true) {
		$q = '';
		$p = array();
		$i = 0;

		foreach ($filters as $key => $value) {
			if($query) {
				$q .= " $key LIKE CONCAT('%', :value, '%')";
			} else {
				$q .= " $key=:value";
			}

			$p[":value"] = $value;

			if($i != count($filters) - 1) {
				if($query) {
					$q .= " OR";
				} else {
					$q .= " AND";
				}
				$i++;
			}
		}

		return array($q,$p);
	}

	/**
	* Check if data exists
	*
	* @param string $data String data
	* @return boolean
	*/
	public function checkIfDataExists($data) {
		$this->resetError();
		$emptyKeys = array();

		if(is_array($data)) {
			foreach ($data as $key => $value) {
				if(empty($value)) 
					$emptyKeys[] = $key;
			}

			if(count($emptyKeys) > 0) {
				$this->errorCode = 404;
				$this->errorMessage = implode(',',$emptyKeys).' cannot be empty';
				return false;
			} 
		} else { // error
			if(empty($data)) {
				$this->errorCode = 404;
				$this->errorMessage = 'data is empty';
				return false;
			}
		}
		return true;
	}
}
?>
