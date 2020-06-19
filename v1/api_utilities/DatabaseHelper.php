<?php
/**
* DatabaseHelper
*
* @copyright Ian Foose - Foose Industries
* @version 1.0
*/

require_once('APIHelper.php');
require_once('Response.php');

class DatabaseHelper {
	/**
	* @var array $dbConfigs Database Configs
	*/
	private $dbConfigs = array();
	
	/**
	*
	* @var array $configs API Global Configs
	*/
	private $configs = array();

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
	* Main Constructor
	*
	* @param array $configs Database connection properties
	* @return void
	*/
	public function __construct($configs) {
		$this->configs = $configs;
		$this->dbConfigs = $configs['database'];
	}

	/**
	* Returns a database connection
	*
	* @return Database Connection | Bool
	*/
	public function getDB() {
		if(self::$db != null) {
			return self::$db;
		} 
		return false;
	}

	/**
	* Sets up a database connection
	*
	* @return Database Connection
	* @throws Exception
	*/
	public function connect() {
		if(self::$db != null) {
			return self::$db;
		} else {
			try {
				$dbConfigs = $this->configs['database'];

				// check database config values
				if(empty($this->dbConfigs['host']) && !empty($this->dbConfigs['user']) && !empty($this->dbConfigs['password']) && !empty($this->dbConfigs['db'])) {
		        	$msg = 'Database Connection Error';

		        	if($this->configs['environment'] == 'dev') {
		            	$msg = 'Invalid Database Config Values';
		        	}

		        	throw new Exception($msg, 500);
				}

                $dbURL = $dbConfigs['host'];

                if(!empty($dbConfigs['port']) && $dbConfigs['port'] != 3306) {
                    $dbURL .= ':'.$dbConfigs['port'];
              	}

				self::$db = new PDO("mysql:host=".$dbURL.";dbname=".$dbConfigs['db'].";charset=".$dbConfigs['charset'].";", $dbConfigs['user'], $dbConfigs['password']);
				self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				self::$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
				self::$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

				return self::$db;
			} catch(Exception $e) {
				$msg = '';
			    
				if($this->configs['environment'] == 'dev') {
			   	    $msg .= ', Error: '.$e->getMessage();
			   	}
			    
				throw new Exception($msg, 500);
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
					if($useResult) {
						self::$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
					}
			
					$statement = $this->connect()->prepare($query);
           			$statement->execute($params);

           			$this->insertID = self::$db->lastInsertId();

					return $statement;
				}
			} catch(Exception $e) { // query failed
				$errorMessage = 'Query Error';
				
				if($this->configs['environment'] == 'dev') {
					$errorMessage = 'Query Error: '.$e->getMessage().' ';	
				}
				
				error_log('Query Error: '.$e->getMessage().' ');
					
				if($this->transaction) { // auto rollback
	               	self::rollback();
	            }

				throw new Exception($errorMessage, 500);
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
	public function find($keys, $vals, $tbl, $message='Item', $output=true) {
		if(!is_array($vals))
			throw new Exception('Values must be in an array', 500);

		$queryString = 'SELECT '.$keys.' FROM '.$tbl.' WHERE ';
		$params = array();

		$j = 0;
		foreach ($vals as $key => $value) {
			$queryString .= $key.'=:'.$j;
			$params[':'.$j] = $vals[$key];

			if($j < count($vals)-1) {
				$queryString .= ' AND ';
			}

			$j++;
		}

		// run query
		try {
			if($result = self::query($queryString,$params)) {
				if($result->rowCount() == 1) {
					return $result->fetch();
				} else {
					if($output) {
						throw new Exception($message, 404);
					}
					return false;
				}
			}
		} catch (Exception $e) {
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
	* return string
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
	* @param int $sinceID Since ID
	* @param int $maxID Max ID
	* @param string $id ID column name
	* @return string
	*/
	public function getOffsetRange($tbl, $sinceID=0, $maxID=0, $id='id') {
		$params = array();
		$q = " $id <=(SELECT MAX($tbl.$id) FROM $tbl)";
			
		if($sinceID != 0 && $maxID == 0) {
			$q .= " AND $id < :sinceID";
			$params[':sinceID'] = $sinceID;
		} else if($maxID != 0 && $sinceID == 0) { 
			$q .= " AND $id > :maxID";
			$params[':maxID'] = $maxID;
		} else {
			if($maxID != 0 && $sinceID != 0) {
				if($maxID != $sinceID) {
					$params[':sinceID'] = $sinceID;
					$params[':maxID'] = $maxID;

					if($sinceID > $maxID) { // up
						$q .= " AND $id > :maxID AND $id < :sinceID";
					} else { // down
						$q .= " AND $id < :maxID AND $id > :sinceID";
					}
				}
			} 
		}

		return array('query'=>$q,'params'=>$params);
	}

    /**
    * Creates a query string that implements column filters
    *
    * @param array $filters Filters Array
    * @param string $condition Comparison condition, defaults to AND but can be OR
    * @return string
    */
    public function buildFilterQueryString($filters=array(), $validFilters=array(), $condition='AND') {
        $filters = array_filter($filters, 'strlen');
        if(!empty($filters)) {
            list($end) = array_keys(array_slice($filters, -1, 1, true));

            foreach($filters as $key => $value) {
		if(!empty($validFilters) && in_array($key, $filters)) {
		    $validFiltersString = implode(',', $validFilters);
        	    throw new Exception("Filter key, $key, is not in valid filters list, $validFiltersString", 404);
		}
                    
		if(!empty($value)) {
                    $queryString .= '`'.$key.'`=:'.$key;

                    // check if end of array
                    if($key !== $end) {
                        $queryString .= " $condition ";
                    }
                }
		throw new Exception("Filter value for key, $key, cannot be empty", 404);
            }
        }
    }
	
    /**
    * Creates an array of filter parameters for a query.
    *
    * @param array $filters Filters Array
    * @return array
    */
    public function buildFilterQueryParameters($filters=array(), $validFilters=array()) {
        $params = array();

        $filters = array_filter($filters, 'strlen');
        if(!empty($filters)) {
            foreach($filters as $key => $value) {
		if(!empty($validFilters) && in_array($key, $filters)) {
		    $validFiltersString = implode(',', $validFilters);
        	    throw new Exception("Filter key, $key, is not in valid filters list, $validFiltersString", 404);
		}
		    
                if(!empty($value)) {
                    $params[':'.$key] = $value;
                }
		throw new Exception("Filter value for key, $key, cannot be empty", 404);
            }
        }
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
}
?>