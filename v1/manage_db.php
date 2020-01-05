#!/usr/bin/php

<?php
require_once('api_utilities/APIHelper.php');

// db manager class
class DBManager extends APIHelper {
	function __construct() {
		parent::__construct();

		if(!$this->configs['database']) {
			die('No database configs were found, please check your config.json!');
		}
	}

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

	private function executeSQL($filePath) {
		try {
			file_exists($filePath);

			$user = $this->configs['database']['user'];
			$password = $this->configs['database']['password'];
			$db = $this->configs['database']['db'];

			$mySQLString = "mysql -u $user -p$password -D $db -e 'source $filePath'";
		
			shell_exec($mySQLString);
			echo "Installed...\n\n";
		} catch(Exception $e) {
			echo "An error occured while processing $filePath with exception: ".$e->getMessage()."\n\n";
		}
	}
}

// interactive portion

$command = '';
$runAll = true;

if(isset($argv) && isset($argv[1])) {
	$command = strtolower($argv[1]);

	if(isset($argv[2])) {
		if(strtolower($argv[2]) == 'base') {
			$runAll = false;
		} else {
			die("Invalid option, ".$argv[2]."!!, valid option(s) are 'base' to install base data only, empty value for all.");
		}
	}
}

$dbManager = new DBManager();

if($command == 'update') {
	$dbManager->parseSQLFiles(true, $runAll);
} else if($command == 'install') {
	$dbManager->parseSQLFiles(false, $runAll);
} else {
	die('Invalid command!, valid commands are install and update');
}

?>