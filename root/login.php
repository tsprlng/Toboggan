<?php
	//hacked login page to test login backend
?>
<html>
<head>
	    <link href='./css/?layout.css' rel='stylesheet' type='text/css' />
	    <link href='./css/?theme.css' rel='stylesheet' type='text/css' />
	    <!-- jPlayer theme -->
	    <link href='./css/jQuery.jPlayer.Theme/jplayer.ultrasonic.css' rel='stylesheet' type='text/css' />
	    <link href='./css/jQuery-ui/smoothness/jquery-ui-1.8.17.custom.css' rel='stylesheet' type='text/css' />

        <script type="text/javascript" src="./js/jQuery/jQuery.1.7.1.min.js"></script>
        <script type="text/javascript" src="./js/jQuery-ui.1.8.17/jquery-ui-1.8.17.custom.min.js"></script>
        <script type="text/javascript" src="./js/jQuery.jPlayer.2.1.0/jquery.jplayer.min.js"></script>
		<script type="text/javascript" src="./js/sha.js"></script>
		<script type="text/javascript" src="./js/ultrasonic.login.js"></script>
		<title>"Ultrasonic" Mockup</title>
</head>
//post arguments = username=$username&password=sha256(password);
<form action='backend/rest.php?action=login' method='POST' id='loginForm'>
	<p>username: <input type='text' id='username' name='username'/></p>
	<p>password: <input type='password' id='passwordInput' name='' /><input type='hidden' id='password' name='password' /></p>
	<p><input type='submit' /></p>
</form>
</html>
