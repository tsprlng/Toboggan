<?php
/**
* function to log messages to a file with a verbosity level
*/
function appLog($message, $level = -1){
	global $config;
	if($level > $config["logLevel"]) return; //verbosity cut-off level
	
	$debugInfo = debug_backtrace();
	if(count($debugInfo) > 1)
		$callingfn = $debugInfo[1]["function"];
	$file = fopen($config["logFile"], "a");
	fwrite($file, date("Y/m/d H:i:s") . ": ". $level. ": " . $callingfn . ": " . $message."\n");
	fclose($file);
}

/**
* replaces placeholders in command strings with sanitized replacements
*/
function expandCmdString($cmd, $data){

	$allowedPatterns = array(
		"path",
		"bitrate"
	);
	
	$patterns = array();
	$replacements = array();
	
	foreach($allowedPatterns as $item)
	{
		if(isset($data[$item]))
		{
			$patterns[]		 = "/%".$item."/";
			$replacements[]	 =	escapeshellarg($data[$item]);
		}
	}
	
	return preg_replace($patterns, $replacements, $cmd);
}
/**
* debugging function
*/
function var_dump_pre($var){
	echo "<pre>";
	var_dump($var);
	echo "</pre>";
}

/**
* custom error handler 
*/
function appErrorHandler($errNo, $errStr, $errFile, $errLine){
	if (!(error_reporting() & $errNo)) {
        // This error code is not included in error_reporting
        return;
    }
	switch ($errNo) {
		case E_USER_ERROR:
			appLog("PHP ERROR in ${errFile} line ${errLine}:".$errStr,appLog_INFO);
			exit(1);
			break;

		case E_USER_WARNING:
			appLog("PHP Warning in ${errFile} line ${errLine}:".$errStr,appLog_VERBOSE);
			break;

		case E_USER_NOTICE:
			appLog("PHP Notice in ${errFile} line ${errLine}:".$errStr, appLog_VERBOSE);
			break;

		default:
			appLog("Unknown error type in ${errFile} line ${errLine}:".$errStr,appLog_INFO);
			break;
    }
}
/**
* Generic exception handler
*/
function handleExeption($exception){
	appLog("Uncaught PHP Exception: ". var_export($exception,true));
}

/**
* function to clean-up and sanitize relative file system paths
*/
function normalisePath($fn){
	$fnArray = explode("/",$fn);
	$ofnArray = array();

	foreach($fnArray as $val)
	{
		if($val == "..")
		{
			//delete last location from array
			array_pop($ofnArray);
		}
		else if ($val != "." && $val != "")
			$ofnArray[] = $val;
	}

	//construct new path
	$newPath = "";
	foreach($ofnArray as $dir)
	{
		$newPath .= "/".$dir;
	}

	return $newPath;
}



?>