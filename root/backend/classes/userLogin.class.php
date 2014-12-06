<?php
/*
	X-US-Authorization: Method Base64(username)."|".sha1PasswordHash
*/
require_once("include/functions.php");
class userLogin {
	/**
	* checks if a user is logged in and returns the userid. Optionally, basic auth can be tried if it is also enabled in the config
	*/
	public static function checkLoggedIn($allowBA = false)
	{
		//try getting auth from session
		if(isset($_SESSION["userid"])){
			return($_SESSION["userid"]);
		}
		else { // no session in progress - try header auth
			$headerAuth = false;
			$headerAuth = userLogin::checkHeaderAuth();

			if($headerAuth){
				//header auth was fine
				return $headerAuth;
			}
			else {
				//Header auth failed - lets try
				if(getConfig("enable_basic_auth") && $allowBA){
					//standard HTTP basic auth
					appLog("Trying basic auth", appLog_DEBUG);
					return userLogin::checkBasicAuth();
				}
			}
		}
		//user is not authenticated
		return false;
	}
	/**
	* Alias of checkLoggedIn() - used to return current userid
	*/
	public static function getCurrentUserID()
	{
		return userLogin::checkLoggedIn();
	}
	public static function getCurrentUsername()
	{
		$userid =  userLogin::checkLoggedIn();
		$userinfo = getUserInfoFromID($userid);
		return $userinfo["username"];
	}
	/**
	* check sent login credentials
	*/
	public static function validate()
	{			
		//trash old session if there is one - this needs to be a new one
		userLogin::logout();
		$_COOKIE = array();
		start_session();
		//try POST VAR auth
		if(isset($_POST["username"]) && isset($_POST["password"]))
		{
			$sentUsername = $_POST["username"];
			$sentPassword = $_POST["password"];
			$userid = userLogin::checkUserCredsValid($sentUsername, $sentPassword);
			if($userid)
			{
				//store userid
				$_SESSION["userid"] = $userid;
			}
			return true;
		}
		//if not session and no POST vars try HTTP header auth
		/*else{
			return userLogin::checkHeaderAuth();			
		}*/
		//No auth sent or no existing sessions 
		return false;
	}
	/**
	* check if there is auth data in headers and if it is valid
	*/
	public static function checkHeaderAuth()
	{
		$headers = apache_request_headers();
		if(!isset($headers['X-US-Authorization']))
		{
			reportError("Authentication Required", 401, "text/plain");
			return false;
		}
		list($method, $authData) = explode(" ", $headers['X-US-Authorization']);
		switch($method)
		{
			case "US-Auth1":
				list($sentUsername, $sentPassHash) = explode("|",$authData);
				$sentUsername = base64_decode($sentUsername);					
				appLog("Authing with credentials from HTTP header. username: $sentUsername", appLog_DEBUG);
				return userLogin::checkUserCredsValid($sentUsername, $sentPassHash);
				break;
		}
	}
	/**
	 * check standard http auth headers
	 */
	public static function checkBasicAuth()
	{
		if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
			header('WWW-Authenticate: Basic realm="My Realm"');
			header('HTTP/1.0 401 Unauthorized');
			return false;
		} else {
			$sentUsername = $_SERVER['PHP_AUTH_USER'];
			$sentPass = $_SERVER['PHP_AUTH_PW'];
			//we expect the password to be sha256'd and base64'd
			$sentPass = base64_encode(hash('sha256',$sentPass,true));

			return userLogin::checkUserCredsValid($sentUsername, $sentPass);
		}
	}


	/*
	* check that user credentials are valid and that the user is enabled etc, and return userid on success, false on failure
	* password should be as received from client, i.e. not rehashed or decoded yet
	*/
	public static function checkUserCredsValid($username, $password)
	{		
		$userRows = getUserInfo($username);
		$passhash = $userRows['password'];
		
		//passwords come in base64 encoded, strip encoding so we can rehash, then re-encode for storage in db
		$password = base64_decode($password);
		$ourPassStr = userLogin::hashPassword($password);		
		appLog("$ourPassStr $passhash");
		if($ourPassStr === $passhash) // passwords match and user not disabled
		{
			if($userRows["enabled"] == 1){
				return $userRows["idUser"];					
			} else {
				appLog("Login attempt for disabled user: $username", appLog_INFO);	
			}
		}
		//reportError("Authentication failed", 401, "text/plain");
		return false;
	}
	/**
	* log a user out if they have a session
	*/
	public static function logout()
	{
		// Unset all of the session variables.
		$_SESSION = array();
		// If it's desired to kill the session, also delete the session cookie.
		// Note: This will destroy the session, and not just the session data!
		if(ini_get("session.use_cookies"))
		{
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000,
				$params["path"], $params["domain"],
				$params["secure"], $params["httponly"]
			);
		}
		// Finally, destroy the session.
		session_destroy();
	}
	/**
	* returns a hashed, base64 encoded value of the password with the salt
	*/
	public static function hashPassword($cleartextPassword)
	{
		return base64_encode(hash("sha256",getConfig("passwordSalt").$cleartextPassword, true));
	}
}
