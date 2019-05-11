<?php
/**
* DatabaseHelper
*
* @copyright Ian Foose - Foose Industries
* @version 1.0
*/

require_once('Response.php');
require_once('ErrorLogger.php');

session_start();

class DatabaseHelper {
	/**
	* @var string $dbURL Database URL
	*/
	private $dbURL;

	/**
	* @var int $dbPort Database Port
	*/
	private $dbPort;

	/**
	* @var string $dbUser Database User 
	*/
	private $dbUser;

	/**
	* @var string $dbPassword Database Password 
	*/
	private $dbPassword;

	/**
	* @var string $dbName Database name (optional)
	*/
	private $dbName;

	/**
	* @var int $insertID last inserted ID
	*/
	public $insertID;

	/**
	* @var bool $transaction Database transaction flag
	*/
	private $transaction = false;
	
	/**
	* @var Database Object $db Current database connection
	*/
	protected static $db;

	/**
	* Intializer
	*
	* @param string $url URL
	* @param string $user Username
	* @param string $password Password
	* @param string $dbName Database Name
	* 
	* @return void
	*/
	public function __construct($url, $port=3306, $user, $password, $dbName) {
		$this->dbURL = $url;
		$this->dbPort = $port;
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
	* @return Database Connection
	* @throws Exception
	*/
	public function connect() {
		if(self::$db != null) {
			return self::$db;
		} else {
			try {
				self::$db = new PDO("mysql:host=".$this->dbURL.":".$this->dbPort.";dbname=".$this->dbName.";",$this->dbUser,$this->dbPassword);
				self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				self::$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
				self::$db->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );

				return self::$db;
			} catch(Exception $e) {
				throw new Exception('Unable to connect to DB',500);
			} 
		}
	}

	/**
	* Queries the database
	*
	* @param string $query Query String
	* @param bool $output Outputs the error
	* @return MySQLi result
	* @throws Exception
	*/
	public function query($query, $params=null, $useResult=false) {
		if(!empty($query)) {
			try {
				if($this->connect()) {
					if($useResult)
						self::$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

			
					$statement = $this->connect()->prepare($query);
           			$statement->execute($params);

           			$this->insertID = self::$db->lastInsertId();

					return $statement;
				}
			} catch(Exception $e) { // query failed
				// log error
				try {
					$errorLogger = new ErrorLogger();
					
					$errorLogger->logError(500,$e->getMessage());
				} catch(Exception $errorE) {
					throw $errorE;
				}

				if($this->transaction) { // auto rollback
	               	self::rollback();
	            }

				throw new Exception('Query Error: '.$e->getMessage(), 500);
			} 
		}
	}

	/**
	* Finds a data row
	*
	* @param string $keys Keys to be retrieved
	* @param array $vals Values to check columns against
	* @param string $tbl Table name to query
	* @param string $message string Name of item being found, default 'item'
	* @return Bool
	*/
	public function find($keys, $vals, $tbl, $message='item') {
		if(!is_array($vals))
			throw new Exception('Values must be in an array', 500);

		$queryString = "SELECT ".$keys." FROM ".$tbl." WHERE ";
		$params = array();

		$j = 0;
		foreach ($vals as $key => $value) {
			$queryString .= $key."=:".$j;
			$params[':'.$j] = $vals[$key];

			if($j < count($vals)-1) {
				$queryString .= " AND ";
			}

			$j++;
		}

		// run query
		try {
			if($result = self::query($queryString,$params)) {
				if($result->rowCount() == 1) {
					return $result->fetch();
				} else {
					return false;
				}
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Logs an audit event for a piece of data, example is editing a user, store the event and who edited
	*
	* @param int $itemID Item being edited
	* @param int $editingID Editor ID
	* @param string $event Event being logged, example (changed, added, deleted)
	*
	* @return String
	* @throws Exception
	*/
	public function saveAuditLog($itemID, $editingID, $event='changed') {
		try {
			if(self::query("INSERT INTO ".$this->tables['audit_logs']." SET item_id=:id, event=:e, editor_id:eID",array(':id'=>$itemID,':eID'=>$editingID,':event'=>$event))) {
				return 'Audit Saved';
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets an audit log by id
	*
	* @param int $id Audit Id
	* @return Data Object
	* @throws Exception
	*/
	public function getAuditLog($id) {
		try {
			if($auditLog = self::find('*',array('id'=>$id),$this->tables['audit_logs'])) {
				return $auditLog;
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets all audit logs
	*
	* @param int $sinceID
	* @param int $maxID
	* @param int $limit Fetch Limit
	* @return array
	* @throws Exception
	*/
	public function getAuditLogs($sinceID=0, $maxID=0, $limit=35) {
		try {
			if($logs = self::query("SELECT * FROM ".$this->tables['audit_logs'])) {
				$logs = array();

				while($log = $logs->fetch()) {
					$log['string_date'] = formateDate($log['date']);

					if(!empty(@$mapping)) {
						// if($object = self::find('*','id',$log['object_id'], $this->tables[$mapping])) {
						// 	$log['object'] = $object;
						// }
					}

					$logs[] = $log;
				}

				return $logs;
			}
		} catch(Exception $e) {
			throw $e;
		}
	}

	/**
	* Begin a transaction
	*
	* @return void
	* @throws Exception
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
				throw new Exception('Begin Transaction Error: '.$e->getMessage(), 500);
			}
		}
	}

	/**
	* Rollback a transaction
	*
	* @return void
	* @throws Exception
	*/
	public function rollback() {
		try {
			self::connect()->rollback();
			$this->transaction = false;
			return true;
		} catch(Exception $e) {
			throw new Exception('Rollback Error: '.$e->getMessage(), 500);
		}
	}

	/**
	* Commit a transaction
	*
	* @return void
	* @throws Exception
	*/
	public function commit() {
		try {
			self::connect()->commit();
			$this->transaction = false;
			return true;
		} catch(Exception $e) {
			throw new Exception('Commit Error: '.$e->getMessage(), 500);
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
	* @param string $tbl Table name
	* @param string $id ID column name
	* @param int $sinceID Since ID
	* @param int $maxID Max ID
	* @param string $deleted Deleted filter
	* @param int $limit Limit
	* @param string $order MySQL Order
	* @return string
	*/
	public static function getOffset($tbl, $id='id', $sinceID=0, $maxID=0, $deleted, $limit, $order='') {
		$params = array();

		// set db select limit
		if(empty(@$limit) || !isset($limit)) {
			// if(!empty(@$this->configs['database']['limit'])) {
			// 	$limit = @$this->configs['database']['limit'];
			// } else {
			// 	$limit = 30;
			// }
		}

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

		// deleted
		if(!empty(@$deleted)) {
			if(is_array($deleted)) {
				if(!empty(@$deleted['key']) && !empty(@$deleted['value'])) {
					$q .= " ".$deleted['key']."=:d";
					$params[':d'] = $deleted;
				} 
			} else {
				$q .= " deleted=:d";
				$params[':d'] = $deleted;
			}
		}

		// order param
		if(!empty(@$order)) {
			if($order == 'DESC' || $order == 'ASC') {
				$q .= " ORDER BY id ".$order;
			}
		}

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
	public static function deletedQuery($deleted) {
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
	* @throws Exception
	*/
	public function validateResponse($validFields, $fields) {
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
				
				if($output) {
					throw new Exception(404, $errorDescription.$outputSuffix);
				}
				return false;
			} else {
				if(!in_array('id', $fields)) {
					$fields[] = 'id';
				}
				return implode(',', $fields);
			}
		} else {
			throw new Exception('null data', 404);
		}
	}

	/**
	* Validates partial response fields for a certain object and returns approriate fields
	*
	* @param array $fields Requested fields
	* @param array $validFields Valid partial response fields
	* @param string $name The name of the object to check
	* @return boolean | array 
	* @throws Exception
	*/
	public function validator($fields, $validFields, $name) {
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
			} else {
				throw new Exception('Invalid Partial Response fields', 500);
			}
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
	public static function buildFilter($filters, $query=true) {
		$q = '';
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
}
?>
