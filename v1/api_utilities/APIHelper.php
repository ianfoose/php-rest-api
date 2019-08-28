<?php
/**
* APIHelper Class
*
* copyright Foose Industries
* version 1.0
*/

require_once('DatabaseHelper.php');
require_once('Response.php');
require_once('Request.php');
require_once('ErrorLogger.php');
require_once('EmailServices.php');
require_once('IPServices.php');
require_once('NotificationServices.php');
require_once('TokenServices.php');

abstract class APIHelper {
	/**
	* var array $configs API configs array
	*/
	public $configs = array();

	/**
	* var array $tables DB Tables Array
	*/
	public $tables = array();

	/**
	* var DataHelper $dataHelper Main DataHelper class for database interface
	*/
	public static $db;
	public static $dataHelper; // legacy support

	/**
	* Main Constructor
	*
	* param string $configs Configs file path
	* return void
	*/
	public function __construct($configPath='./config.json') {	
		$this->getConfigs($configPath);

		// set timezone
		if($this->configs['date']['timezone']) {
			date_default_timezone_set($this->configs['date']['timezone']);
		} else {
			date_default_timezone_set('UTC');
		}

		// set error reporting
		$errorReporting = 1;

		if($this->configs['environment'] == 'production' || $this->configs['development']['errors'] == false || empty($this->configs['development']['warnings'])) {
			$errorReporting = 0;
		} else {
			error_reporting(E_ALL); // Error Reporting 
		}

		ini_set('display_errors', $errorReporting); 

		// default global datahelper
		self::$db = new DatabaseHelper($this->configs);
		self::$dataHelper = self::$db; // legacy support

		// get tables dynamically from DB
		try {
			if(!empty($this->configs['database']['db']) && self::$db->connect()) {
				if($results = self::$db->query("SELECT table_name FROM information_schema.tables where table_schema='".$this->configs['database']['db']."'")) {
					
					while($tblName = $results->fetch()) {
						$tblName = $tblName['table_name'];

						// check for table exceptions
						if($this->configs['table_exceptions'] && in_array($tblName, $this->configs['table_exceptions'])) {
							continue;
						} 

						$this->tables[] = $tblName;

						if(!defined(strtoupper($tblName)))
							define(strtoupper($tblName), $tblName);
					}
				}
			}
		} catch (Exception $e) {
			// todo, silently report error
		}
	}

	/**
	* Gets configs from file
	*
	* return void
	* throws Exception
	*/
	private function getConfigs($path) {
		try {
			$configs = json_decode(file_get_contents($path), true);

			// get database configs, development or production
			if(!empty($configs) && !empty($configs['database'])) {
				if($configs['environment'] == 'development' && !empty($configs['dev-database'])) {
					foreach ($configs['database'] as $key => $value) {
						if(isset($configs['dev-database'][$key])) {
							$configs['database'][$key] = $configs['dev-database'][$key];
						} else {
							$configs['database'][$key] = $value;
						}
					} 
				} 
			} 
	
			$this->configs = $configs;
		} catch(Exception $e) {
			throw new Exception('Config values not set, maybe the file does not exist?');
		}
	}

	/**
	* Check if data exists
	*
	* param string $data String data
	* return boolean
	* throws Exception
	*/
	public function checkIfDataExists($data) {
		$emptyKeys = array();

		if(is_array($data)) {
			foreach ($data as $key => $value) {
				if(empty($value)) 
					$emptyKeys[] = $key;
			}

			if(count($emptyKeys) > 0) {
				throw new Exception(implode(',',$emptyKeys).' cannot be empty', 404);
			} 
		} else { // error
			if(empty($data)) {
				throw new Exception('Data is empty', 404);
			}
		}
		return true;
	}

	/**
	* Logs an audit event for a piece of data, example is editing a user, store the event and who edited
	*
	* param int $itemID Item being edited
	* param int $editingID Editor ID
	* param string $event Event being logged, example (changed, added, deleted)
	*
	* return String
	* throws Exception
	*/
	public function saveAuditLog($objectID, $objectType, $editingID, $event='changed') {
		try {
			if(self::$db->query("INSERT INTO ".AUDIT_LOGS." SET object_id=:id, type=:t, event=:e, editor_id=:eID",array(':id'=>$objectID,':t'=>$objectType, ':eID'=>$editingID,':e'=>$event))) {
				return 'Audit Saved';
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets an audit log by id
	*
	* param int $id Audit Id
	* return Data Object
	* throws Exception
	*/
	public function getAuditLog($id, $mapping=null) {
		try {
			if($auditLog = self::$db->find('*',array('id'=>$id),AUDIT_LOGS)) {
				return $this->getAuditLogData($auditLog, $mapping);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets all audit logs
	*
	* param int $sinceID
	* param int $maxID
	* param int $limit Fetch Limit
	* return array
	* throws Exception
	*/
	public function getAuditLogs($sinceID=0, $maxID=0, $limit=35, $mapping=null) {
		try {
			if($results = self::$db->query("SELECT * FROM ".AUDIT_LOGS)) {
				$logs = array();

				while($log = $results->fetch()) {
					$logs[] = $this->getAuditLogData($log, $mapping);
				}

				return $logs;
			}
		} catch(Exception $e) {
			throw $e;
		}
	}

	private function getAuditLogData($log=null, $mapping=null) {
		if(isset($log['date']))
			$log['string_date'] = $this->formatDate($log['date']);

		if(!empty($mapping)) {
			if($object = self::$db->find('*',array('id'=>$log['object_id']), $mapping)) {
				$log['object'] = $object;
			}
		}

		return $log;
	}

	/**
	* Formats date
	*
	* param string $date String version of a date
	* param string $format String date format can be left empty for default conversion
	* return string
	*/
	public function formatDate($date, $format=null) { 
		if($format == null) {
			$date = strtotime($date.date_default_timezone_get()); // sets timezone
			$date = time() - $date; // to get the time since that moment
		    $date = ($date<1)? 1 : $date;
		    $tokens = array (
		        31536000 => 'year',
		        2592000 => 'month',
		        604800 => 'week',
		        86400 => 'day',
		        3600 => 'hour',
		        60 => 'minute',
		        1 => 'now'
		    );

		    foreach ($tokens as $unit => $text) {
		        if ($date < $unit) continue;
		        $numberOfUnits = floor($date / $unit);

		        if($text == 'now') {
		        	return $text;
		        }

		        return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':'').' ago';
		    }
		} else {
			return date($format, strtotime($date));
		}
		return $date;
	}

	/**
	* Formats a database time 
	*
	* param string $time Time String
	* return string
	*/
	public function formatTime($time) {
		return date('g:i A,', strtotime($time));
	} 

	/**
	* Formats a number to decimal places with an optional suffix
	*
	* param int $number The number being formated
	* param boolean $suffix Determines wheather to output a suffix
	* return int | string 
	*/
	public function formatNumber($number, $suffix = true) {
		$ending = '';

		if($number > 1000 && $number < 100000) { // thousand
			$number = round(($number/1000), 1);
			$ending = 'K';
		} else if ($number > 1000000 && $number < 10000000) { // million
			$number = round(($number/100000), 1);
			$ending = 'M';
		} else if($number > 1000000) { // billion
			$number = round(($number/10000000), 1);
			$ending = 'B';
		}

		if($suffix == true) { $number = $number.' '.$ending; }

		return $number;
	}

	/**
	* Formats file size
	*
	* param int $bytes The number of bytes at the lowest level
	* param boolean $suffix Determines wheater to print the suffix
	* return string
	*/
	public function formatSize($bytes, $suffix=true) {
	    if ($bytes == 0) {
	        return '0.00 Bytes';
	    }

	    $stringSize = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
	    $size = floor(log($bytes, 1024));

	    $ending = '';
	    if($suffix == true) { $ending = $stringSize[$size]; }

	    return round($bytes/pow(1024, $size), 2).' '.$ending;
	}

	/**
	* Turns unicode into an emoji
	* 
	* param string $input String to be processed 
	* return string
	*/
	public function processEmoji($input) {
		return preg_replace("/\\\\u([0-9A-F]{2,5})/i", "&#x$1;", $input);
	}

	/**
	* Zips a file
	*
	* param string $name File name 
	* param array $files Files to zip

	* return void
	*/
	public function zipFile($name, $files) {
		$zip = new ZipArchive();
		$zip->open('zipfile.zip', ZipArchive::CREATE);
		 
		foreach ($files as $key => $value) {
			//$zip->addFile('test.php', 'subdir/test.php');
		}
		 
		$zip->close();

		$filename = $name.'.zip';

		// send $filename to browser
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mimeType = finfo_file($finfo, (__DIR__).'/'.$filename);
		$size = filesize((__DIR__).'/'.$filename);
		$name = basename($filename);
		 
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
		    // cache settings for IE6 on HTTPS
		    header('Cache-Control: max-age=120');
		    header('Pragma: public');
		} else {
		    header('Cache-Control: private, max-age=120, must-revalidate');
		    header("Pragma: no-cache");
		}
		
		header("Content-Type: $mimeType");
		header('Content-Disposition: attachment; filename="' . $name . '";');
		header("Accept-Ranges: bytes");
		header('Content-Length: ' . filesize((__DIR__).'/'.$filename));
	}
}
?>