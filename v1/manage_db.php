#!/usr/bin/php

<?php
require_once('api_utilities/APIHelper.php');

/**
* DBManager Class
*
* @version 2.0
* @return void
*/
class DBManager extends APIHelper {
	/**
	* @var array $dbConfigs Database configs for the API
	*/
	private $dbConfigs;

	/**
	* Main Constructor
	*
	* @return void
	*/
	function __construct() {
		parent::__construct();

		if(!array_key_exists('database', $this->configs)) {
			die('No database configs were found, please check your config.json!');
		} else {
			$this->dbConfigs = $this->configs['database']; 
			
			// check for 'root_db' credentials
			if(array_key_exists('root_user', $this->dbConfigs) && array_key_exists('root_password', $this->dbConfigs)) {
				// using 'root_db' credentials
				$this->dbConfigs['user'] = $this->dbConfigs['root_user'];
				$this->dbConfigs['password'] = $this->dbConfigs['root_password'];
			} 
		}
	}

	/**
	* Parse SQL files
	*
	* @param bool $update Flag to update database.
	* @param bool $all Flag to run all scripts in modules folder.
	* @return void
	*/
	public function parseSQLFiles($update=false, $all=true) {
		$prefix = 'Installing ';
		$fileName = 'database.sql';

		if($update) {
			$fileName = 'updates.sql';
			$prefix = 'Updating ';
		}

		if(!$update) {
			$databaseSQLFileDir = dirname(__FILE__).'/sql/';
			$this->executeSQLFile($databaseSQLFileDir.'database.sql', false);
			$this->executeSQLFile($databaseSQLFileDir.'default_db.sql');
		} else {
			$this->executeSQLFile(dirname(__FILE__).'/sql/updates.sql');
		}

		if($all) {
			foreach (glob(dirname(__FILE__).'/modules/*') as $dirname) {
				$sqlFile = "$dirname/$fileName";

				echo "$prefix $sqlFile...\n\n";
				$this->executeSQLFile($sqlFile);
			}
		}
	}

	/**
	* Constructs a MySQL login string with host, port, user and password options.
	*
	* @return string
	*/ 
	private function constructLoginString() {
		$dbHost = $this->dbConfigs['host'];
		$dbPort = $this->dbConfigs['port'];
		$user = $this->dbConfigs['user'];
		$password = $this->dbConfigs['password'];
		$db = $this->dbConfigs['db'];
		return "-h $dbHost -P $dbPort -u $user  -p$password $db ";
	}

	/**
	* Backup database method
	*
	* @return void
	*/
	public function backupDB() {
		try {
			$loginStr = $this->constructLoginString();
			$db = $this->dbConfigs['db'];
			$currentDate = date('now');
			$this->executeSQL("mysqldump $loginStr $db > $currentDate-$db.sql");
			echo "Database export complete.\n\n";
		} catch(Exception $e) {
			echo "An error occured while backing up the DB with exception: ".$e->getMessage()."\n\n";
		}
	}

	/**
	* Execute SQL from a file
	*
	* @param string $filePath File path for SQL file to run.
	* @return void
	*/
	private function executeSQLFile($filePath) {
		try {
			$loginStr = $this->constructLoginString();
			file_exists($filePath);
			echo "Running $filePath ...\n\n";
			$this->executeSQL("mysql $loginStr -e 'source $filePath'");
			echo "Installed...\n\n";
		} catch(Exception $e) {
			echo "An error occured while processing $filePath with exception: ".$e->getMessage()."\n\n";
		}
	}

	/**
	* Executs an SQL statement.
	*
	* @param string $command MySQL command
	* @return shell status
	*/
	private function executeSQL($command) {
		try {		
			return shell_exec($command);
		} catch(Exception $e) {
			throw $e;
		}
	}
}

/* ==================== INTERACTIVE PORTION ==================== */

$command = '';
$runAll = true;
$backup = false;

if(isset($argv) && isset($argv[1])) {
	$command = strtolower($argv[1]);

	if(isset($argv[2])) {
		if(strtolower($argv[2]) == 'base') {
			$runAll = false;
		} else {
			die("Invalid option, ".$argv[2]."!!, valid option(s) are 'base' to install base data only, empty value for all.");
		}
	}

	if(isset($argv[3])) {
		if(strtolower($argv[3]) == 'skip-backup') {
			$backup = true;
		} else {
			die("Invalid option, ".$argv[3]."!!, valid option(s) are 'skip-backup' to skip backing up the existing database");
		}
	}
}

$dbManager = new DBManager();

chdir('sql/');

if($command == 'update') {
	// update database only
	if($backup) {
		$dbManager->backupDB();
	}

	$dbManager->parseSQLFiles(true, $runAll);
} else if($command == 'install') {
	// install database only
	$dbManager->parseSQLFiles(false, $runAll);
} else {
	die('Invalid command!, valid commands are install and update');
}

?>
