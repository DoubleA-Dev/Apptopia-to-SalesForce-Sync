<?php  

/**/
//Sandbox
//Callback URL 
define("REDIRECT_URI", "https://chartboost.com/callbackurl.php"); //Sandbox 
//SFDC Parameters
define("CLIENT_ID", ""); // Consumer Key/clientID
define("CLIENT_SECRET", ""); // Consumer Secret/client_secret
define("LOGIN_URI", "https://test.salesforce.com");  
define("USER_NAME", "");
define("PASSWORD", "");
define("SECURITY_TOKEN", "");
 

/*  
//Production
//Callback URL 
define("REDIRECT_URI", "https://apptopia.bluecaffeine.io/callbackurl.php"); //Sandbox 
//SFDC Parameters
define("CLIENT_ID", "3MVG9y6x0357Hlefy7N0PjufsZcFYwyR9G6Hm4c72Gq9XviR2wljKp1Lf31LAjaZ7fNe4MloR2HKGTHbaCyBD"); // Consumer Key/clientID
define("CLIENT_SECRET", "0A6DBD0352A96C4801AA246A6CEAB6FC6D6E6978C63C9735BC127356AC1D4F95"); // Consumer Secret/client_secret
define("LOGIN_URI", "https://login.salesforce.com/");  
define("USER_NAME", "r.rosati@digitalreachagency.com");
define("PASSWORD", "0AfXltZU5s87");
define("SECURITY_TOKEN", "6Cel800DE0000000JOuk8880h000000L4ByweKYaTjlYMqOnztLVu5givgwQ1nO62hjHTSsvnZV83IDVFW2f9fiRrs7LZ4hAexuGAtzCpfU");
*/

/*
// Log in to Salesforce using your favorite browser, then enter the following request Url in a new tab to get the auth code. 
// Not necessary to do this since this is done programmatically in callbackurl.php
// https://<YOUR_INSTANCE>.salesforce.com/services/oauth2/authorize?response_type=code&client_id=<CONSUMER_KEY>&redirect_uri=https://login.salesforce.com/
// https://DRAnew21.salesforce.com/services/oauth2/authorize?response_type=code&client_id=3MVG9GiqKapCZBwGSTPX2Wf.wQnllipLK33LjfMaSVvC5zw6l_rj8h0Wmta9bzweQ6gfaoX4.DE9ALyVC.cQj&redirect_uri=https://test.salesforce.com/
*/
?>