<?php  

require __DIR__ .'/vendor/autoload.php'; // Dependencies installed by Composer 
require_once 'config.php'; 
session_start();

//Example: https://login.salesforce.com/services/oauth2/token
$token_url = LOGIN_URI . "/services/oauth2/token";

//Grab the Authorization Code sent by SFDC after login
$code = (isset($_GET['code'])) ? $_GET['code'] : '';  
if ($code !== '' || !isset($code)) {
   //If Autho code is in the url
   //Then run regular Web Server authorization flow
   echo "<h1>Run Web Server OAuth Flow</h1>";
   echo "<p>Authorization Code: ". $code ."</p>"; 

   $params = "code=" . $code
      . "&grant_type=authorization_code"
      . "&client_id=" . CLIENT_ID
      . "&client_secret=" . CLIENT_SECRET
      . "&redirect_uri=" . urlencode(REDIRECT_URI);   
      
   $curl = curl_init($token_url);
   curl_setopt($curl, CURLOPT_HEADER, false);
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($curl, CURLOPT_POST, true);
   curl_setopt($curl, CURLOPT_POSTFIELDS, $params);

   $json_response = curl_exec($curl);  

} else {
   //If not, then run the Refresh Token flow   
   echo "<h1>Run Refresh Token Flow</h1>";

   //make sure refresh token is saved locally.
   $refresh_token = file_get_contents(__DIR__ .'/tmp/.refresh_token'); 
   if (!$refresh_token) { 
      die("Error - Refresh Token not saved locally!");
   }

   $params = "&grant_type=refresh_token"
      . "&client_id=" . CLIENT_ID
      . "&client_secret=" . CLIENT_SECRET
      . "&refresh_token=" . $refresh_token; 

   $curl = curl_init($token_url);
   curl_setopt($curl, CURLOPT_HEADER, false);
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($curl, CURLOPT_POST, true);
   curl_setopt($curl, CURLOPT_POSTFIELDS, $params);

   $json_response = curl_exec($curl);  
}   

 
$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);  
if ( $status !== 200 ) {
   die('<p>Error: call to token URL ('.$token_url.') failed with HTTP Status: '.$status.', response $json_response, curl_error '.curl_error($curl).', curl_errno '.curl_errno($curl).'</p>');
} else {
   echo '<h2>HTTP Status code: '.$status.'</h2>';
   echo '<p>success!</p>';
} 
curl_close($curl);

$response = json_decode($json_response, true);   

/*
echo "<h2>OAuth Response</h2>";
echo "<pre>";
var_dump($response);
echo "</pre>";   
 */

//Save Instance URL
if (isset($response['instance_url'])) {
   $instance_url = $response['instance_url'];
   $_SESSION['instance_url']  = $instance_url; //save you as a session variable that will be referenced later
   //Save you to a file, but only need you when debugging status
   $instanceUrlFile = __DIR__ .'/tmp/.instance_url';
   if (file_put_contents($instanceUrlFile, $instance_url) === false) {
      throw new Exception("Couldn't save Instance URL");
   } 
}   

//Save access token
if (isset($response['access_token'])) {
   $access_token = $response['access_token'];
   $_SESSION['access_token']  = $access_token; //save you as a session variable that will be referenced later
   //Save you to a file, but only need you when debugging status
   $accessTokenFile = __DIR__ .'/tmp/.access_token';
   if (file_put_contents($accessTokenFile, $access_token) === false) {
      throw new Exception("Couldn't save Access Token");
   } 
}  
 
//Save refresh token to a file. will need you when session ends to request a new access token
if (isset($response['refresh_token'])) {
   $refresh_token = $response['refresh_token'];
   $refreshTokenFile = __DIR__ .'/tmp/.refresh_token';
   if (file_put_contents($refreshTokenFile, $refresh_token) === false) {
      throw new Exception("Couldn't save refresh token");
   } 
}   
 
//continue to run sync
require_once __DIR__ .'/sync.php';
//header( 'Location: sync.php' ); 

?>
 