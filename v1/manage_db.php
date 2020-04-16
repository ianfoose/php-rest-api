#!/usr/bin/php

<?php
require_once('api_utilities/APIHelper.php');

/**
* DBManager Class
*
* @version 1.0
* @return void
*/
class DBManager extends APIHelper {
	/**
	* Main Constructor
	*
	* @return void
	*/
	function __construct() {
		parent::__construct();

		if(!$this->configs['database']) {
			die('No database configs were found, please check your config.json!');
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
			
			$this->executeSQL($databaseSQLFileDir.'database.sql', false);
			$this->executeSQL($databaseSQLFileDir.'default_db.sql');
		} else {
			$this->executeSQL(dirname(__FILE__).'/sql/updates.sql');
		}

		if($all) {
			foreach (glob(dirname(__FILE__).'/modules/*') as $dirname) {
				$sqlFile = "$dirname/$fileName";

				echo "$prefix $sqlFile...\n\n";
				$this->executeSQL($sqlFile);
			}
		}
	}

	/**
	* Backup database method
	*
	* @return void
	*/
	public function backupDB() {
		$dbHost = $this->configs['database']['host'];
		$dbPort = $this->configs['database']['port'];
		$user = $this->configs['database']['user'];
		$password = $this->configs['database']['password'];
		$db = $this->configs['database']['db'];

		$currentDate = date('now');
		$command = "mysqldump -u $user -h $dbHost -p$password $db > $currentDate-$db.sql";

		shell_exec($command);
		echo "Database export complete.\n\n";
	}

	/**
	* Execute SQL method
	*
	* @param string $filePath File path for SQL file to run.
	* @return void
	*/
	private function executeSQL($filePath) {
		try {
			file_exists($filePath);

			$dbHost = $this->configs['database']['host'];
			$dbPort = $this->configs['database']['port'];
			$user = $this->configs['database']['user'];
			$password = $this->configs['database']['password'];
			$db = $this->configs['database']['db'];

			$mySQLString = "mysql -h $dbHost -P $dbPort -u $user -p$password -D $db -e 'source $filePath'";
		
			shell_exec($mySQLString);
			echo "Installed...\n\n";
		} catch(Exception $e) {
			echo "An error occured while processing $filePath with exception: ".$e->getMessage()."\n\n";
		}
	}
}

/* ==================== INTERACTIVE PORTION ==================== */

$command = '';
$runAll = true;
$backup = true;

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
		if(strtolower($argv[3]) == 'backup') {
			$backup = true;
		} else {
			die("Invalid option, ".$argv[3]."!!, valid option(s) are 'backup' to backup the existing database");
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