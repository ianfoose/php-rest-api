<?php
/**
* Utilities Class
*
* @version 1.0
*/

/**
* Formats date
*
* @param string $date String version of a date
* @param string $format String date format can be left empty for default conversion
* @return string
*/
function formatDate($date, $format = null) { 
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
* @param string $time Time String
* @return string
*/
function formatTime($time) {
	return date('g:i A,', strtotime($time));
} 

/**
* Formats a number to decimal places with an optional suffix
*
* @param int $number The number being formated
* @param boolean $suffix Determines wheather to output a suffix
* @return int | string 
*/
function formatNumber($number, $suffix = true) {
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
* @param int $bytes The number of bytes at the lowest level
* @param boolean $suffix Determines wheater to print the suffix
* @return string
*/
function formatSize($bytes, $suffix=true) {
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
* Returns the format specified
*
* @param string $function The input function
* @return string
*/
function getFormat($function) {
	$formatSuffix = explode('.',$function);
	
	if(count($formatSuffix)>1) {
		if($formatSuffix[1] == 'json' || $formatSuffix[1] == 'xml' || $formatSuffix[1] == 'csv') {
			return $formatSuffix[1];
		}
	}
	return 'json';
}  

/**
* Turns unicode into an emoji
* 
* @param string $input String to be processed 
* @return string
*/
function processEmoji($input) {
	return preg_replace("/\\\\u([0-9A-F]{2,5})/i", "&#x$1;", $input);
}

/**
* Zips a file
*
* @return void
*/
function zipFile($name, $files) {
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

/**
* Gets the since ID
*
* @return int
*/
function getSinceID() {
	if(!empty(@$_GET['sinceID'])) {
		if($_GET['sinceID'] > 0) 
			return $_GET['sinceID'];
	}
	return 0;
}

/**
* Gets the max ID
*
* @return int
*/
function getMaxID() {
	if(!empty(@$_GET['maxID'])) {
		if($_GET['maxID'] > 0) 
			return $_GET['maxID'];
	}
	return 0;
}

/**
* Gets deleted
*
* @return string
*/
function getDeleted() {
	if(@$_GET['deleted'] != null) 
		return $_GET['deleted'];

	return null;
}

/**
* Gets the limit
*
* @return int
*/
function getLimit() {
	if(!empty(@$_GET['limit'])) {
		if($_GET['limit'] > 0) 
			return $_GET['limit'];
	}
	return 35;
}

/**
* Returns a string representing a bool from an int
*
* @param int $value Int to be converted to bool  
* @return string
*/
function getStringBoolFromInt($value) {
	if($value >= 1) 
		return 'true';  
	return 'false';
}

/**
* Returns a string value from a boolean
*
* @param boolean $value Boolean to be converted to bool  
* @return string
*/
function getStringBoolFromBool($value) {
	if($value == true) 
		return 'true';  
	return 'false';
}

/**
* Returns a boolean value from an int
*
* @param int $value Int to be converted to bool  
* @return string
*/
function getBoolFromInt($value) {
	if($value >= 1) 
		return true; 
	return false;
}

/**
* Returns a bool from a string
*
* @param string $value String bool value
* @return boolean
*/
function getBoolFromString($value) {
	if($value == 'true') 
		return true; 
	return false;
}

/**
* Returns an int value for a bool
*
* @param bool $bool Input Bool
* @return int
*/
function getIntFromBool($bool) {
	if($bool == true) 
		return 1; 
	return 0;
}
?>