<?php
	registerTest(
		//checks
		"listDirContents",  

		//get args
		array(
			"dir" => "/",
			"mediaSourceID" => 1, //hopefully there's a 1
		),

		//checks
		array(
			"statusCodes" => array(
				"pass" => "^200$"
			),	
			"json"	=> array(
				"CurrentPath"	=> array("notblank"),
				"Directories"	=> array("array"),
				"/Directories/*/" => array("notblank"),
				"Files"		=> array("array"),
				"/Files/*/filename" => array("notblank"),
				"/Files/*/displayName" => array("notblank"),
				"/Files/*/converters/*/extension" => array("notblank"),
				"/Files/*/converters/*/fileConverterID" => array("int"),
				"/Files/*/converters/*/mediaType" => array("regex /[av]/"),
			),
		)
	);

?>
