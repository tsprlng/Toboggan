<?php
require_once("functions.php");

function outputStream($streamerID, $file, $skipToTime = 0){

	//set errorhandler so errors are captures and not output
	set_error_handler("appErrorHandler");
	
	//check file exist
	if(!is_file($file) && !is_link($file))
	{
		appLog("Request for non-existant file: ".$file, appLog_INFO);
		reportError("Requested file does not exist");
		return false;
	}

	appLog("Streaming file: ". $file, appLog_VERBOSE);
	
	//info about the file and path
	$filepathInfo = pathinfo($file);
	
	//global config
	global $config;
	
	//default mime
	$mimeType = "application/octet-stream";
	
	//check if this is a download
	if($streamerID == 0){
		appLog("This is a download - going straight to passthrough", appLog_DEBUG);
		$filenameToSend = $filepathInfo["basename"]; // filename for the http header
	}
	else{	
		//streamer settings
		$streamerObj = getConverterById($streamerID);
		
		if(!$streamerObj)
		{
			appLog("Streamer object with id $streamerID does not exist", appLog_INFO);
			reportError("Invalid StreamerID");
			return false;
		}
		//check that the extension is compatible with the streamer
		if(strtolower($streamerObj->fromFileType->extension) != strtolower($filepathInfo["extension"]))
		{
			appLog("StreamerID: ". $streamerObj->id . " does not support this extension", appLog_INFO);
			reportError("Streamer specified by streamerID: ". $streamerObj->id . "  does not support this file");
			return false;
		}
	
		appLog("Using Streamer ID: ". $streamerObj->id, appLog_VERBOSE);			
		
		/**
		* Get media bitrate
		*/
		$maxBitrate = getCurrentMaxBitrate($streamerObj->toFileType->mediaType); //get from db in the end
		if($maxBitrate == 0 || $maxBitrate === false)
		{
			appLog("Could not retreive maxBitrate or it was 0 - ignoring", appLog_INFO);
			$maxBitrate = NO_MAX_BITRATE; // failover to no max bitrate
		}
		appLog("Max bitrate to be applied is: ". $maxBitrate, appLog_DEBUG);
		$mustTranscode = false;
		
		if($maxBitrate != NO_MAX_BITRATE)//if there is a max bitrate
		{ 
			//get the bitrate command with variables expanded
			$bitrateCommand = expandCmdString($streamerObj->fromFileType->bitrateCmd, 
				array(
					"path" 		=> $file,
				)
			);
			//check that the bitrate command is defined
			if(!$bitrateCommand){ 
				appLog("No valid command to get bitrate - skipping", appLog_VERBOSE);
				$mustTranscode = true; //don't know source file bitrate - be safe and transcode
			}
			else{ // if there is a command to get the bitrate - get the bitrate and compare with the max
				appLog("Retreiving bitrate from file using command: ". $bitrateCommand, appLog_DEBUG);
				$bitrateOutput = exec($bitrateCommand);
				appLog("Output from bitrate command: ". $bitrateOutput, appLog_DEBUG);
				$bitrate = (int) $bitrateOutput;
				appLog("Parsed bitrate number read from bitrate command output is " . ($bitrate==0? "invalid - no bitrate limit will be applied":$bitrate."kb/s"), appLog_DEBUG);
				if($bitrate == 0)
					appLog("Parsed bitrate number was 0 - no bitrate limit will be applied", appLog_DEBUG);				
			
				//check if max bitrate is over 
				if($bitrate > $maxBitrate)
				{
					appLog("Source file bitrate($bitrate) is greater than the max player bitrate($maxBitrate) - need to change bitrate", appLog_DEBUG);
					$mustTranscode = true;
				}
			}
		}
		else//no max bitrate
		{ 
			appLog("No maximum bitrate is to be applied", appLog_VERBOSE);
			$mustTranscode = false;
		}
		//mimetype of file to be output
		$mimeType = $streamerObj->toFileType->mimeType;		
		
		//filename to send
		$filenameToSend = substr($filepathInfo["basename"], 0, strrpos($filepathInfo["basename"], ".")). "." . $streamerObj->toFileType->extension;
		
	}// end if download
	
	//check skipToTime as we must not passThough if the file is to be seeked -- this needs to be addressed so that passthrough streams can be seeked too. 
	if($skipToTime != 0)
	{
		$mustTranscode = true;
	}
	
	/**
	* setup for streaming data and headers
	*/
	//sanitise filename
	$filenameToSend = preg_replace("/\"/","'",$filenameToSend);
	
	//do not buffer
	ini_set("output_buffering", "0");

	appLog("Using mime type: ". $mimeType, appLog_DEBUG);
	header("Content-Type: " . $mimeType);
	
	appLog("Using attachement filename: $filenameToSend", appLog_DEBUG);
	header("Content-Disposition: attachment; filename=".$filenameToSend);

	header("Cache-Control: no-cache");
	
	/**
	* Output the stream
	*/
	//make sure the session is closed because otherwise other requests will be blocked by php
	session_write_close();
	
	if(
		$streamerID == 0 || //straight passthrough file download
		(!$mustTranscode && $streamerObj->toFileType->extension == $streamerObj->fromFileType->extension)
	) // if the file's bitrate does not need to be change and the format is supported by the player, do not transcode
	{
		appLog("File does not need to be transcoded, streaming straight through", appLog_VERBOSE);
		passthroughStream($file);
	}
	else
	{	
		appLog("File must be transcoded", appLog_VERBOSE);
		transcodeStream($streamerObj, $file, $skipToTime);
	}
	
}
/**
* Streams a file straight through as is
*/
function passthroughStream($file){

	$fileSize = FileOps::filesize($file);
	
	//THis needs to be the size of the request - i.e. think about ranges
	header("Content-Length: $fileSize");

	//Handle HTTP Range requests
	if (isset($_SERVER['HTTP_RANGE'])) {
	    if (!preg_match("/^bytes=\d*-\d*(,\d*-\d*)*$/", $_SERVER['HTTP_RANGE'])) {
		header('HTTP/1.1 416 Requested Range Not Satisfiable');
		header('Content-Range: bytes */' . $fileSize); // Required in 416.
		exit;
	    }

	    $ranges = explode(',', substr($_SERVER['HTTP_RANGE'], 6));
	    foreach ($ranges as $range) {
		$parts = explode('-', $range);
		$start = $parts[0]; // If this is empty, this should be 0.
		$end = $parts[1]; // If this is empty or greater than than filelength - 1, this should be filelength - 1.

		if($start == "")
			$start = 0;
		if($end == "")
			$end = $fileSize - 1;

		if ($start > $end || $start < 0 || $end > $fileSize - 1) {
		    header('HTTP/1.1 416 Requested Range Not Satisfiable');
		    header('Content-Range: bytes */' . $fileSize); // Required in 416.
		    exit;
		}
		passthroughStreamRange($file, $start, $end, $fileSize);
		exit;
		break; //only handling single ranges at the moment		
	    }
	} else{
		passthroughStreamRange($file,false,false,$fileSize);
	}
}

/**
* Streams the speficied byte ranges of the file straight through
* if $startByte or $endByte is false, handle as a full file request, no range
*/
function passthroughStreamRange($file, $startByte, $endByte, $fileSize){

	appLog("Handling passthough stream for bytes ranges: $startByte to $endByte", appLog_DEBUG);
	$isByteRangeReq = $startByte !== false && $endByte !== false; // are we dealing with a HTTP Range request?

	$fh = @fopen($file,'rb');
	if(!$fh)
	{
		throw new Exception('Unable to open file');
		exit;
	}

//	appLog($fileSize);


	//limit bandwidth
	$maxBandwidth = getCurrentMaxBandwidth();
	if(!(is_numeric($maxBandwidth) && $maxBandwidth >= 0))
	{
		reportError("Could not get maxBandwidth or it is 0");
		exit();
	}
	appLog("Limiting bandwidth to ". $maxBandwidth . "KB/s", appLog_DEBUG);
	
	//calc bytes to read each second
	$bytesToRead = $maxBandwidth*1024;
	
	while(!feof($fh))
	{
		//prevent php timeout
		set_time_limit(60);
		
		//traffic limits
		$remainingTraffic = getRemainingUserTrafficLimit(userLogin::getCurrentUserID());
		if($remainingTraffic !== false)
		{
			if($remainingTraffic <= 0)
			{
				appLog("Traffic limit exceeded for user with id: " . userLogin::getCurrentUserID(), appLog_INFO);
				exit();
			}
			elseif($remainingTraffic < $bytesToRead/1024)
			{
				appLog("Setting \$bytesToRead to $bytesToRead", appLog_DEBUG);
				$bytesToRead = $remainingTraffic*1024;
			}
		}
		//get start position of file pointer
		$pointerStart = ftell($fh);
		
		//start time for bandwidth throttling
		$startTime = microtime(true);
		
		//read the file and output the data
		//read a max of singleReadMaxBytes at a time to prevent putting too much in memory - first read 0 - the required number of singleReadMaxBytes, then read the remainder
		$numReads = intval($bytesToRead / getConfig("singleReadMaxBytes"));
		for($c = 0; $c < $numReads; $c++){
			print(fread($fh, getConfig("singleReadMaxBytes")));
		}
		//read the remainder
		print(fread($fh, $bytesToRead % getConfig("singleReadMaxBytes")));
		
		//calc the number of bytes actually read
		$bytesRead = ftell($fh) - $pointerStart;
		
		//update traffic used for traffic limit
		updateUserUsedTraffic(userLogin::getCurrentUserID(), (int)($bytesRead/1024));
		
		//sleep for 1 second minus the time taken to read the data - for bandwidth limiting
		$sleeptime = (1 - (microtime(true) - $startTime))*1000000;
		$sleeptime = ($sleeptime < 0 ? 0:$sleeptime);
		usleep($sleeptime);
	}

	fclose($fh);
}

/**
* Transcodes a file using the streamerObj data provideded and streams it out
*/
function transcodeStream($streamerObj, $file, $skipToTime){
	/**
	* Set up streamer execution environment
	*/
	//stream descriptors
	$descriptors = array(
		0 => array("pipe", "r"), //STDIN
		1 => array("pipe", "w"), //STDOUT
		2 => array("pipe", "w"), //STDERR
	);

	//expand placeholders in command string
	$cmd = expandCmdString($streamerObj->cmd, 
		array(
			"path" 		=> $file,
			"bitrate"	=> getCurrentMaxBitrate($streamerObj->toFileType->mediaType),
			"skipToTime"	=> $skipToTime,
		)
	);

	//start the process
	$process = proc_open($cmd, $descriptors, $pipes, sys_get_temp_dir());
	appLog("Running streamer: ". $cmd, appLog_VERBOSE);
	
	if(!is_resource($process)){
		throw new Exception("Streamer process failed to start");
		exit();
	}
	
	//stop output streams from blocking
	stream_set_blocking($pipes[1],0); //STDOUT
	stream_set_blocking($pipes[2],0); //STDERR
	
	$previousPointerLoc=0; // var to remember how many bytes were last read from STDOUT once the process is dead
	$output = null;
	
	//bandwidth limit
	$maxBandwidth = getCurrentMaxBandwidth();
	if(!(is_numeric($maxBandwidth) && $maxBandwidth >= 0))
	{
		reportError("Could not get maxBandwidth or it is 0");
		exit();
	}
	appLog("Limiting bandwidth to ". $maxBandwidth . "KB/s", appLog_DEBUG);
	
	//number times per sec that we need to fread to output the max bandwidth
	$iterationsPerSec = $maxBandwidth/8.0;
	/**
	* loop until trancode process dies outputing the data stream
	*/
	$bytesToRead = 8192; // this is the normal size to read, it will be reduced in order to hit the traffic limit and not exceed it.
	while(true){
	
		//start time for bandwidth throttling
		$startTime = microtime(true);
		
		//prevent php timeout
		set_time_limit(60);
		
		//check traffic limit
		$remainingTraffic = getRemainingUserTrafficLimit(userLogin::getCurrentUserID());
		if($remainingTraffic !== false)
		{
			if($remainingTraffic <= 0)
			{
				appLog("Traffic limit exceeded for user with id: " . userLogin::getCurrentUserID(), appLog_INFO);
				exit();
			} // adjust the number of bytes to read next time to avoid overstepping the limit
			elseif($remainingTraffic < $bytesToRead/1024)
			{
				appLog("Setting \$bytesToRead to $bytesToRead", appLog_DEBUG);
				$bytesToRead = $remainingTraffic*1024;
			}
		}
		
		//get a chunk of data
		$output = fread($pipes[1],$bytesToRead);
		print $output;
		
		//update traffic used for traffic limit
		updateUserUsedTraffic(userLogin::getCurrentUserID(), (int)($bytesToRead/1024));
		
		//get error output
		$errOutput = fgets($pipes[2]);
		if($errOutput != "")
			appLog("STREAMER_ERRSTREAM: ".$errOutput,appLog_DEBUG2);
		
		//get process status
		$status = proc_get_status($process);
		
		//continue until process stops
		if(!$status["running"]) {
			//continue until all remaining data in the stream buffer is read
			appLog("Streamer STDOUT Pointer moved: ". (ftell($pipes[1])-$previousPointerLoc) . " bytes", appLog_DEBUG);
			if((ftell($pipes[1])-$previousPointerLoc) == 0){
				break;
			}
			$previousPointerLoc = ftell($pipes[1]);
		}
		//flush();
		
		//sleep for 1 second minus the time taken to read the data - for bandwidth limiting
		$sleeptime = ((1.0/$iterationsPerSec) - (microtime(true) - $startTime))*1000000;
		$sleeptime = ($sleeptime < 0 ? 0:$sleeptime);
		usleep($sleeptime);
	}
	
	appLog("Finished transcode: ". $cmd, appLog_DEBUG);
	
	//fclose($pipes[1]);
	fclose($pipes[2]);

	//set the process group pid
	posix_setpgid($status["pid"],$status["pid"]);

	//kill the process to ensure that the ffmpeg sub process is dead
	posix_kill($status["pid"],9);
	
	//terminate the process
	proc_terminate($process);
}

?>
