<!DOCTYPE html>
<html>
<head>

	<!-- jPlayer theme -->
	<link href='./css/jQuery.jPlayer.Theme/jplayer.ultrasonic.css' rel='stylesheet' type='text/css' />
	<link href='./css/jQuery-ui/custom-theme/jquery-ui-1.8.21.custom.css' rel='stylesheet' type='text/css' />
	<link href='./css/jQuery.dynatree/default.css' rel='stylesheet' type='text/css' />

	<!-- internal stylesheets -->
	<link href='./css/?layout.css' rel='stylesheet' type='text/css' />
	<link href='./css/?theme.css' rel='stylesheet' type='text/css' />
	<link href='./css/?admin.css' rel='stylesheet' type='text/css' />

	<script type="text/javascript" src="./js/jQuery/jQuery.1.7.1.min.js"></script>
	<script type="text/javascript" src="./js/jQuery-ui/jquery-ui-1.8.21.custom.min.js"></script>
	<script type="text/javascript" src="./js/jQuery.jPlayer.2.1.0/jquery.jplayer.min.js"></script>
	<script type="text/javascript" src="./js/jQuery.dynatree/jquery.dynatree.min.js"></script>

	<script type="text/javascript" src="./js/jQuery.jPlayer.2.1.0/add-on/jquery.jplayer.inspector.js"></script>
	<script type="text/javascript">
<?php
	echo "var g_ultrasonic_basePath=\"".dirname($_SERVER['REQUEST_URI'])."/\";";
?>
	</script>
	<script type="text/javascript" src="./js/ultrasonic.admin.js"></script>

	<!-- login form related -->
	<script type="text/javascript" src="./js/sha.js"></script>

	<title>"Ultrasonic" Mockup</title>
</head>
<body>
<div id='loginFormContainer'>
	<?php include 'loginForm.include.php'; ?>
</div>

</body>
</html>
