<?php


/**
* returns streamer profiles which are suitable to produce streams for the given file
*/
function getAvailableConverters($file){
	//get file extension
	$pathinfo = pathinfo($file);
	$extension = strtolower($pathinfo["extension"]);
	
	//we may not have an extension entry for this. If not, just return an empty array
	try
	{
		$fromFT = FileType::getFileTypeFromExtension($extension);
	} catch (NoSuchFileTypeException $e) {
		
		return array();
	}
	return $fromFT->getAvailableConverters();
}

/**
* get a streamer profile from its id
*/
function getConverterById($id){
	//we may not have an extension entry for this. If not, just return an empty array
	try
	{
		if(checkUserPermission("accessStreamer", $id)) //check user has permission to use the streamer too
			return new FileConverter($id);
	} catch (NoSuchFileConverterException $e) {
		//don't need to do anything, just fail through
	}	
	return false; // no permission, or non existent FileConverter
	
	
}

/**
* output a JSON object representing the server FileType settings
*/
function outputFileTypeSettings_JSON()
{
	$settings = getFileTypeSettings();
	restTools::sendResponse(json_encode($settings), 200, JSON_MIME_TYPE);
}

/**
* output a JSON object representing the server Command settings
*/
function outputCommandSettings_JSON()
{
	$settings = getCommandSettings();
	restTools::sendResponse(json_encode($settings), 200, JSON_MIME_TYPE);
}

/**
* output a JSON object representing the server Command settings
*/
function outputFileConverterSettings_JSON()
{
	$settings = getFileConverterSettings();
	restTools::sendResponse(json_encode($settings), 200, JSON_MIME_TYPE);
}
/**
* get an array of all FileType objects
*/
function getAllFileTypes(){
	
	
	//db connection
	$conn = getDBConnection();
		
	//get all settings for each streamer apart from fromExt.Extension and aggregate rows which are identical (DISTINCT)
	$stmt = $conn->prepare("
		SELECT 
			idfileType			
		FROM FileType
	");
	$stmt->execute();	
	
	//results structure
	$FileTypes = array();
	
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);		
	foreach($rows as $row)
	{	
		$FileTypes[] = new FileType($row["idfileType"]);
		
	}		
	closeDBConnection($conn);
		
	return $FileTypes;
}

/**
* get an object representing the server settings
*/
function getFileTypeSettings()
{
	$fileTypes = getAllFileTypes();
	
	$settings = new SettingGroup();
	$settings->setSchema(getSchema("retrieveFileTypeSettings"));
	$settingsData = array();	
	
	foreach($fileTypes as $ft)
	{	
		$settingsData[] = array(
			"fileTypeID" => $ft->id,
			"extension" => $ft->extension,
			"mimeType" => $ft->mimeType,
			"mediaType" => $ft->mediaType,
			"bitrateCmdID" => $ft->bitrateCmdID,
			"durationCmdID" => $ft->durationCmdID,
		);
		
	}
	$settings->setData($settingsData);
	
	return $settings->getSettingsObject();
}

/**
* get an object representing the server settings
*/
function getCommandSettings()
{
	//db connection
	$conn = getDBConnection();
		
	//get all settings for each streamer apart from fromExt.Extension and aggregate rows which are identical (DISTINCT)
	$stmt = $conn->prepare("
		SELECT 
			idcommand,
			command,
			displayName
		FROM Command
	");
	$stmt->execute();	
	
	$settings = new SettingGroup();
	$settings->setSchema(getSchema("retrieveCommandSettings"));
	$settingsData = array();
	
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach($rows as $ft)
	{		
		$settingsData[] = array(		
			"commandID" => $ft["idcommand"],
			"command" => $ft["command"],
			"displayName" => $ft["displayName"],
		);
		
	}		
	closeDBConnection($conn);
	
	$settings->setData($settingsData);
	
	return $settings->getSettingsObject();
}

/**
* get an array of all FileType objects
*/
function getAllFileConverters(){	
	
	//db connection
	$conn = getDBConnection();
		
	//get all settings for each streamer apart from fromExt.Extension and aggregate rows which are identical (DISTINCT)
	$stmt = $conn->prepare("
		SELECT 
			idfileConverter			
		FROM FileConverter
	");
	$stmt->execute();	
	
	//results structure
	$FileConverters = array();
	
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);		
	foreach($rows as $row)
	{	
		$FileConverters[] = new FileConverter($row["idfileConverter"]);
		
	}		
	closeDBConnection($conn);
		
	return $FileConverters;
}

/**
* get an object representing the server settings
*/
function getFileConverterSettings()
{
	$fileConverters = getAllFileConverters();
	
	$settings = new SettingGroup();
	$settings->setSchema(getSchema("retrieveFileConverterSettings"));
	$settingsData = array();	

	foreach($fileConverters as $fc)
	{		
		$settingsData[] = array(		
			"fileConverterID" => $fc->id,
			"fromFileTypeID" => $fc->fromFileType->id,
			"toFileTypeID" => $fc->toFileType->id,
			"commandID" => $fc->cmdID,
		);
		
	}
	
	$settings->setData($settingsData);
	
	return $settings->getSettingsObject();
}

function saveFileTypeSettings($settings_JSON)
{

	appLog("Saving new File Type settings", appLog_VERBOSE);
	//get object
	$settings = json_decode($settings_JSON, true);

	if($settings == null){ //json parse failed
		reportError("invalid json", 400);
	}	
	//validate the bitch!
	//basic validation
	$av = new ArgValidator("handleArgValidationError");
		
	//validate more
	foreach($settings as $ft)
	{
		$av->validateArgs($ft, array(
			"fileTypeID"	=> array("int", "optional"),
			"extension"		=> array("string", "notblank"),
			"mimeType"		=> array("string", "notblank"),
			"mediaType"		=> array("string", "notblank", "regex /[av]/"),
			"bitrateCmdID" 	=> array(function($a){return (is_int($a+0) || is_null($a));}),
			"durationCmdID" => array(function($a){return (is_int($a+0) || is_null($a));}),
		));
	}

	$conn = getDBConnection();
	try	{	
		$conn->beginTransaction();

		//get a list of ids we've been passed
		$FTToKeep = array();
		foreach($settings as $FT)
		{
			if(isset($FT["fileTypeID"])) //new FileTypes to create do not have this
				$FTToKeep[] = $FT["fileTypeID"];
		}
		appLog("Not deleting extensions: ". implode(',',$FTToKeep), appLog_DEBUG);
		
		//now build the delete - so like (?,?,?,? ...
		$query = "DELETE FROM FileType WHERE idFileType NOT IN (" . implode(',', array_fill(0,count($FTToKeep),'?')) . ")";
		$stmt = $conn->prepare($query);

		foreach($FTToKeep as $key => $c)
		{
			$stmt->bindValue($key+1, $c, PDO::PARAM_INT);
		}
		$stmt->execute(); //delete the omitted ones	
		
		//Whack in the new ones
		foreach($settings as $ft)
		{
			if(isset($ft["fileTypeID"]) && checkFileTypeExists($ft["extension"])){ // need to update
				$stmt = $conn->prepare("
					UPDATE 
						FileType 
					SET
						extension 		= :extension,
						mimeType		= :mimeType,
						mediaType		= :mediaType,
						idbitrateCmd	= :idbitrateCmd,
						iddurationCmd	= :iddurationCmd
					WHERE
						idFileType = :idFileType
				");
				$stmt->bindValue(":idFileType",$ft["fileTypeID"], PDO::PARAM_INT);
			} else if (checkFileTypeExists($ft["extension"])){ // the ext already exists - we should have an id, but we don't
				$conn->rollback();
				reportError("FileType with extension " . $ft["extension"] . " already exists, but fileTypeID was not given", 400);				
			} else{//new - need to insert
				appLog("inserting ". $ft["extension"]);
				$stmt = $conn->prepare("
					INSERT INTO 
						FileType (extension, mimeType, mediaType, idbitrateCmd, iddurationCmd)
					VALUES
						(:extension, :mimeType, :mediaType, :idbitrateCmd, :iddurationCmd)
				");
			}
			
			$stmt->bindValue(":extension",$ft["extension"], PDO::PARAM_STR);
			$stmt->bindValue(":mimeType",$ft["mimeType"], PDO::PARAM_STR);
			$stmt->bindValue(":mediaType",$ft["mediaType"], PDO::PARAM_STR);
			$stmt->bindValue(":idbitrateCmd",$ft["bitrateCmdID"], PDO::PARAM_INT);
			$stmt->bindValue(":iddurationCmd",$ft["durationCmdID"], PDO::PARAM_INT);
			$stmt->execute();
		}	
		
		$conn->commit();		
	} catch (PDOException $pe) { // watch for constraint violations since we don't check elsewhere. Should change this
		appLog($pe, appLog_DEBUG);
		$conn->rollback();
		if($pe->errorInfo[0] == "23000"){ // constraint violation - TODO: expand this to give info
				reportError("Constraint violation while removing FileType",409);
		}
	}
	closeDBConnection($conn);
}

function saveCommandSettings($settings_JSON)
{

	appLog("Saving new command settings", appLog_VERBOSE);
	//get object
	$settings = json_decode($settings_JSON, true);
	
	//validate the bitch!
	//basic validation
	$av = new ArgValidator("handleArgValidationError");

	//validate more
	foreach($settings as $com)
	{
		$av->validateArgs($com, array(
			"commandID"		=> array("int", "optional"), //omit for new commands
			"command"		=> array("string"),
			"displayName"		=> array("string", "notblank"),			
		));
	}

	//start the transaction
	$conn = getDBConnection();
	try {
		$conn->beginTransaction();
		
		//delete omitted commands - DB constraints should take care of most problems here
		//get a list of ids we've been passed
		$commandsToKeep = array();
		foreach($settings as $com)
		{
			if(isset($com["commandID"]))
				$commandsToKeep[] = $com["commandID"];
		}
		
		//now build the delete - so like (?,?,?,? ...
		$query = "DELETE FROM Command WHERE idcommand NOT IN (" . implode(',', array_fill(0,count($commandsToKeep),'?')) . ")";
		$stmt = $conn->prepare($query);
		
		foreach($commandsToKeep as $key => $c)
		{
			$stmt->bindValue($key+1, $c, PDO::PARAM_INT);
		}
		$stmt->execute();

		//now update existing/insert new
		foreach($settings as $com)
		{
			if(isset($com["commandID"])) // existing command
			{
				$stmt = $conn->prepare("
					UPDATE 
						Command 
					SET
						command			= :command,
						displayName		= :displayName
					WHERE
						idcommand = :idcommand
				");
				$stmt->bindValue(":idcommand",$com["commandID"], PDO::PARAM_INT);
			} else { // new command
				$stmt = $conn->prepare("
					INSERT INTO 
						Command (command, displayName)
					VALUES
						(:command, :displayName)
				");
			}
			$stmt->bindValue(":command",$com["command"], PDO::PARAM_STR);
			$stmt->bindValue(":displayName",$com["displayName"], PDO::PARAM_STR);
			$stmt->execute();	
			
		}			
		$conn->commit();
		
	} catch (PDOException $pe) { // watch for constraint violations since we don't check elsewhere. Should change this
			$conn->rollback();
			appLog($pe, appLog_DEBUG);
			if($pe->errorInfo[0] == "23000"){ // constraint violation
					reportError("Constraint violation while removing Command",409);
			}
	}	
	closeDBConnection($conn);
}

function saveFileConverterSettings($settings_JSON)
{
	appLog("Saving new File Converter settings", appLog_VERBOSE);
	//get object
	$settings = json_decode($settings_JSON, true);
	
	//validate the bitch!
	//basic validation
	$av = new ArgValidator("handleArgValidationError");

	//validate more
	foreach($settings as $com)
	{
		$av->validateArgs($com, array(
			"fileConverterID"		=> array("int", "optional"), //omit for new commands
			"fromFileTypeID"		=> array("int", "notblank"),
			"toFileTypeID"		=> array("int", "notblank"),
			"commandID"		=> array("int"),			
		));
	}

	//start the transaction
	$conn = getDBConnection();
	$conn->beginTransaction();
	
	//delete omitted commands - DB constraints should take care of most problems here
	//get a list of ids we've been passed
	$FCToKeep = array();
	foreach($settings as $com)
	{
		if(isset($com["fileConverterID"]))
			$FCToKeep[] = $com["fileConverterID"];
	}
	
	//now build the delete - so like (?,?,?,? ...
	$query = "DELETE FROM FileConverter WHERE idfileConverter NOT IN (" . implode(',', array_fill(0,count($FCToKeep),'?')) . ")";
	$stmt = $conn->prepare($query);	
	
	foreach($FCToKeep as $key => $c)
	{
		$stmt->bindValue($key+1, $c, PDO::PARAM_INT);
	}
	$stmt->execute();

	//now update existing/insert new
	foreach($settings as $com)
	{
		if(isset($com["fileConverterID"])) // existing command
		{
			$stmt = $conn->prepare("
				UPDATE 
					FileConverter 
				SET
					fromidfileType	= :fromidfileType,
					toidfileType	= :toidfileType,
					idcommand		= :idcommand
				WHERE
					idfileConverter = :idfileConverter
			");
			$stmt->bindValue(":idfileConverter",$com["fileConverterID"], PDO::PARAM_INT);
		} else { // new command
			$stmt = $conn->prepare("
				INSERT INTO 
					FileConverter (fromidfileType, toidfileType, idcommand)
				VALUES
					(:fromidfileType, :toidfileType, :idcommand)
			");
		}
		$stmt->bindValue(":fromidfileType",$com["fromFileTypeID"], PDO::PARAM_INT);
		$stmt->bindValue(":toidfileType",$com["toFileTypeID"], PDO::PARAM_INT);
		$stmt->bindValue(":idcommand",$com["commandID"], PDO::PARAM_INT);
		$stmt->execute();		
	}
	
	$conn->commit();	
	closeDBConnection($conn);
}

/**
 * updates a streamer - (has to work inside an existing transaction, hence the $conn)
*/
function updateStreamer($conn, $streamer)
{
	appLog("Updating streamer mapping in DB with id {$streamer->id}", appLog_VERBOSE);
	
	appLog("Updating fromExt with new settings for  ({$streamer->fromExt})", appLog_DEBUG);
	
	//update fromExt
	$stmt = $conn->prepare("
		 UPDATE fromExt 
		 SET Extension = :fromExt, bitrateCmd = :bitrateCmd
		 WHERE idfromExt = 
		(
			SELECT idfromExt 
			FROM extensionMap 
			WHERE idExtensionMap = :idExtMap
		)");
	
	
	$stmt->bindValue(":fromExt", $streamer->fromExt);
	$stmt->bindValue(":bitrateCmd", $streamer->bitrateCmd);
	$stmt->bindValue(":idExtMap", $streamer->id);
	$stmt->execute();
	
	appLog("Updating toExt with new settings for ({$streamer->fromExt})", appLog_DEBUG);
	//update toExt
	$stmt = $conn->prepare("
		 UPDATE toExt 
		 SET Extension = :toExt, MimeType = :mime, MediaType = :mediatype
		 WHERE idtoExt = 
		(
			SELECT idtoExt 
			FROM extensionMap 
			WHERE idExtensionMap = :idExtMap
		)");
	$stmt->bindValue(":toExt", $streamer->toExt);
	$stmt->bindValue(":mime", $streamer->mime);
	$stmt->bindValue(":mediatype", $streamer->outputMediaType);
	$stmt->bindValue(":idExtMap", $streamer->id);
	$stmt->execute();
	
	appLog("Updating transcode_cmd with new settings for ({$streamer->fromExt})", appLog_DEBUG);
	//update transcode_cmd
	$stmt = $conn->prepare("
		 UPDATE transcode_cmd 
		 SET command = :command
		 WHERE idtranscode_cmd = 
		(
			SELECT idtranscode_cmd 
			FROM extensionMap 
			WHERE idExtensionMap = :idExtMap
		)");
	
	$stmt->bindValue(":command", $streamer->cmd);
	$stmt->bindValue(":idExtMap", $streamer->id);
	$stmt->execute();
	
}
/**
 * Inserts a new streamer mapping into the DB
*/
function insertStreamer($conn, $streamer)
{
	appLog("Inserting new streamer mapping to DB", appLog_DEBUG);

	appLog("Inserting (if needed) into fromExt", appLog_DEBUG);
	//add/update fromExt
	$fromExtID = updateOrInsertFromExt($conn, $streamer->fromExt, $streamer-> bitrateCmd);
	
	appLog("Inserting (if needed) into toExt", appLog_DEBUG);	
	//add/update toExt
	$toExtID = updateOrInsertToExt($conn, $streamer->toExt, $streamer->mime, $streamer->outputMediaType);
	
	appLog("Inserting (if needed) into transcode_cmd", appLog_DEBUG);
	//add/update transcodeCmd
	$transcode_cmdID = updateOrInsertTranscodeCmd($conn, $streamer->cmd);
	
	appLog("Inserting new transcode mapping into extensionMap", appLog_DEBUG);
	//extensionMap	
	$stmt = $conn->prepare("INSERT INTO extensionMap(idfromExt, idtoExt, idtranscode_cmd) VALUES(:idfrom, :idto, :idtrans)");
	$stmt->bindValue(":idfrom", $fromExtID);
	$stmt->bindValue(":idto", $toExtID);
	$stmt->bindValue(":idtrans", $transcode_cmdID);
	$stmt->execute();
	
}

/**
* Remove a streamer from the db
*/
function removeStreamer($conn, $streamerID)
{
	appLog("Removing streamer with ID $streamerID from DB", appLog_DEBUG);

	//remove streamer mapping
	$stmt = $conn->prepare("DELETE FROM extensionMap WHERE idextensionMap = :sid");
	$stmt->bindValue(":sid", $streamerID);
	$stmt->execute();

	//clean up orphans
	//fromExt
	$stmt = $conn->query("DELETE FROM fromExt WHERE idfromExt NOT IN (SELECT idfromExt FROM extensionMap);");
	$stmt->execute();
	//toExt
	$stmt = $conn->query("DELETE FROM toExt WHERE idtoExt NOT IN (SELECT idtoExt FROM extensionMap);");
	$stmt->execute();
	//transcode_cmd
	$stmt = $conn->query("DELETE FROM transcode_cmd WHERE idtranscode_cmd NOT IN (SELECT idtranscode_cmd FROM extensionMap);");
	$stmt->execute();

}

/**
* inserts and returns, or just returns the fromext ID depending on whether or not the extension is already in the table
*/
function updateOrInsertFromExt($conn, $fromExtension, $bitrateCmd)
{
	$stmt = $conn->prepare("SELECT idfromExt FROM fromExt WHERE Extension = :fromExt");
	$stmt->bindValue(":fromExt", $fromExtension);
	$stmt->execute();
	
	$result = $stmt->fetch(PDO::FETCH_ASSOC);
	$fromExtID = null;
	
	if(!$result) // need to insert
	{
		$stmt = $conn->prepare("INSERT INTO fromExt(Extension, bitrateCmd) VALUES(:ext, :bcmd)");
		$stmt->bindValue(":ext", $fromExtension);
		$stmt->bindValue(":bcmd", $bitrateCmd);
		$stmt->execute();
		$fromExtID = $conn->lastInsertId();
	}
	else
		 $fromExtID = $result["idfromExt"];
	/*else //need to update
	{
		$stmt = $conn->prepare("UPDATE fromExt SET bitrateCmd = :bcmd WHERE Extension = :ext");
		$stmt->bindValue(":ext", $fromExtension);
		$stmt->bindValue(":bcmd", $bitrateCmd);
		$stmt->execute();
		
		$fromExtID = $result[0]["fromextid"]; // id for later from original result
	}*/
	
	return $fromExtID;
}

/**
*inserts and returns, or just returns the toExt id depending on whether or not the extension is already in the table
*/
function updateOrInsertToExt($conn, $toExtension, $mimeType, $mediaType)
{
	$stmt = $conn->prepare("SELECT idtoExt FROM toExt WHERE Extension = :toExt");
	$stmt->bindValue(":toExt", $toExtension);
	$stmt->execute();
	
	$result = $stmt->fetch(PDO::FETCH_ASSOC);
	$toExtID = null; //init var
	
	if(!$result) // need to insert
	{
		$stmt = $conn->prepare("INSERT INTO toExt(Extension, MimeType, MediaType) VALUES(:ext, :mime, :mediatype)");
		$stmt->bindValue(":ext", $toExtension);
		$stmt->bindValue(":mime", $mimeType);
		$stmt->bindValue(":mediatype", $mediaType);
		$stmt->execute();
		$toExtID = $conn->lastInsertId();
		
	}
	else //need to update
	{
		$toExtID = $result["idtoExt"];
	/*
		$stmt = $conn->prepare("UPDATE toExt SET MimeType = :mime, MediaType = :mediatype WHERE Extension = :ext");
		$stmt->bindValue(":ext", $toExtension);
		$stmt->bindValue(":mime", $mimeType);
		$stmt->bindValue(":mediatype", $mediaType);
		$stmt->execute();
		$toExtID = $result[0]["idtoExt"]; // id for later from original result*/
	}
	
	return $toExtID;
}

/**
*inserts and returns, or just returns the transcode_cmd id depending on whether or not the command is already in the table
*/
function updateOrInsertTranscodeCmd($conn, $command)
{
	$stmt = $conn->prepare("SELECT idtranscode_cmd FROM transcode_cmd WHERE command = :command");
	$stmt->bindValue(":command", $command);
	$stmt->execute();
	
	$result = $stmt->fetch(PDO::FETCH_ASSOC);
	$transcode_cmdID = null; //init var
	
	if(!$result) // need to insert
	{
		$stmt = $conn->prepare("INSERT INTO transcode_cmd(command) VALUES(:command)");
		$stmt->bindValue(":command", $command);
		$stmt->execute();
		$transcode_cmdID = $conn->lastInsertId();
		
	}
	else //need to update
	{
		$transcode_cmdID = $result["idtranscode_cmd"];
	/*
		$stmt = $conn->prepare("UPDATE INTO transcode_cmd(command) VALUES(:command)");
		$stmt->bindValue(":command", $command);
		$stmt->execute();*/
		//nothing to update
	}
	return $transcode_cmdID;
}

/**
* checks if a FileType (extension) is in the DB
*/
function checkFileTypeExists($ext){
	try{
		FileType::getFileTypeFromExtension($ext);
	} catch(NoSuchFileTypeException $e) {
		return false;
	}
	return true;
}


?>
