<?php 
/* App Object Logic
 * This file queries the Salesforce App Object, updates all App data found in the Apptopia data file
 * Then creates new ones if the Apptopia App object isn't in Salesforce
 *
 * Author: Digital Reach Agency
 */

echo "<h2>App Object</h2>"; 
 
//Error Handling - Error File

//Create new App log file
$newLogFile = fopen(__DIR__ ."/logs/apps/apps_".date('m-d-Y_hia').".log", "w");
fclose($newLogFile);  
//save permissions properly to newly created log file

$logFile = __DIR__ ."/logs/apps/apps_".date('m-d-Y_hia').".log";   
chmod($logFile, 0775);

/* START UPDATE ACCOUNT FUNCTION */
function update_sf_app($id, $app_obj, $instance_url, $access_token) {  
	global $logFile; 
	$url = "$instance_url/services/data/v20.0/sobjects/Apps__c/$id";   
	$content = json_encode($app_obj);
	$curl = curl_init($url);
	$date = date('d-m-y h:i:s');

	curl_setopt($curl, CURLOPT_HEADER, false); 
	curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth $access_token", "Content-type: application/json"));
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
	curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
	curl_exec($curl);

	$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

	if ( $status != 204 ) {  
		//Error message
		$error_message = "Error, failed to update ".$app_obj['Name'].": call to URL $url failed with status $status, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl)." ".$date.PHP_EOL;  
		//Log Error Message
		error_log($error_message, 3, $logFile);        
		//exit("Error: call to URL $url failed with status $status, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
	} else {  
		$success_message = "Success! Status ".$status." updated App ".$app_obj['Name']." ".$date.PHP_EOL; 
		error_log($success_message, 3, $logFile);   
	}   

	curl_close($curl); 
} 
/* END UPDATE FUNCTION */ 


/* START CREATE ACCOUNT FUNCTION */
function create_sf_app($app_obj, $instance_url, $access_token) {
	global $logFile;
	$url = "$instance_url/services/data/v20.0/sobjects/Apps__c/";  
	//$content = json_encode(array("Name" => $name)); 
	$content = json_encode($app_obj);
	$curl = curl_init($url);
	$date = date('d-m-y h:i:s');

	curl_setopt($curl, CURLOPT_HEADER, false); 
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
	curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth $access_token", "Content-type: application/json"));
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $content);

	$json_response = curl_exec($curl); 
	$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

	if ( $status != 201 ) { 
	  //Error message
	  $error_message = "Error, failed to create ".$app_obj['Name'].": call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl)." ".$date.PHP_EOL; 
	  error_log($error_message, 3, $logFile);   
	  //exit("Error: call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
	} else { 
	$success_message = "Success! Status ".$status." created App ".$app_obj['Name']." ".$date.PHP_EOL; 
	error_log($success_message, 3, $logFile);   
	}    
	curl_close($curl); 
}
/* END CREATE ACCOUNT FUNCTION */  

//Function that loops through accounts by Associated IDs
//and finds an account name for each associated ID
//and then returns the Apptopia ID/Name conversion in a string
//we explode/seperate it later
function __idNameConvertApps($accounts, $assIds) {
	$rt = "";
	$assIds = explode(", ", $assIds);
	foreach ($accounts as $account) {
		//Format account name
		$nameFormatted = strtolower(preg_replace("/[^A-Za-z0-9\-]+/", "", $account['Name'])); 
		foreach ($assIds as $assId) { 

			if ($account['Apptopia_Publisher_ID__c'] == $assId) {
				//echo $nameFormatted." ".$assId."<br>";
				 $rt = ($rt == "") ? $nameFormatted : $rt.", ".$nameFormatted;
			} 
		} 
	}
	return $rt;
} 

//Create an empty list of aAccounts
$apptopiaAccounts = array(); 
//count number of account objects that we split into strings 
//keep in mind, there will be duplicates in the account object
//so the accounts returned in apptopia will be greater then what's imported into salesforce
$accountInc = 0;
for($x = 0; $x < count($jsonStrObjects); $x++) {   
  //remake every string in the newly combined array, a readable array
  $jsonStrDecodeNew = json_decode($jsonStrObjects[$x], true); 

  //If Account Object exists and it's not null
  if ($jsonStrDecodeNew['Account']) {
     //filter out apptopia accounts if it's missing name, website or apptopia publisher ID
    if ( isset($jsonStrDecodeNew['Account']['Name']) && isset($jsonStrDecodeNew['Account']['Website']) && isset($jsonStrDecodeNew['Account']['Apptopia_Publisher_ID__c']) ) {  
 
      //Clean account name which will be used to filter out duplicates with
      $nameFormatted = strtolower(preg_replace("/[^A-Za-z0-9\-]+/", "", $jsonStrDecodeNew['Account']['Name']));   
      $jsonStrDecodeNew['Account']['name_formatted'] = $nameFormatted;

      //Add empty key filed to hold Associated_IDs__c
      $jsonStrDecodeNew['Account']['Associated_IDs__c'] = '';

      //Add each account name to our new primary acocunt list
      array_push($apptopiaAccounts, $jsonStrDecodeNew['Account']);   

      //increment
      $accountInc++; 
    } 
  } 
}
echo "<h3>Total Number of Accounts from Account Objects in Apptopia File before filtering of duplicates: ".$accountInc."</h3>"; 


/* ******************************************************************************************************* */
// Query SFDC Account Object so we can create list of account names asscoaited with the Account ID in SFDC 
// You need this because you're looking for the related account ID and adding it to your Apptopia data that's uploaded into SFDC
$accountQuery = "SELECT Name, Id, Apptopia_Publisher_ID__c, Associated_IDs__c from Account"; 

//Build our URL to the Salesforce database
$accountQueryUrl = "$instance_url/services/data/v20.0/query?q=" . urlencode($accountQuery); 
$accountQueryCurl = curl_init($accountQueryUrl);

curl_setopt($accountQueryCurl, CURLOPT_HEADER, false); 
curl_setopt($accountQueryCurl, CURLOPT_RETURNTRANSFER, true);   
curl_setopt($accountQueryCurl, CURLOPT_HTTPHEADER, array("Authorization: OAuth $access_token")); 

$accountJson_response = curl_exec($accountQueryCurl); 
$accountResponse = json_decode($accountJson_response, true); // crap out salesforce JSON 
$accountTotal = $accountResponse['totalSize']; 
echo "<h3>Total Accounts in Salesforce: ".$accountTotal."</h3>"; // Total Size   

 
$accountNameArray = array(); //List of account ids and account names  
//Start pagination while loop
$accountIsDone = false;   
while(!$accountIsDone) {

  $accountJson_response_loop = curl_exec($accountQueryCurl); 
  $accountResponse_loop = json_decode($accountJson_response_loop, true); // crap out salesforce JSON 
  $accountIsDone = $accountResponse_loop["done"] == true;     

  //loop through acount names 
  foreach ( (array) $accountResponse_loop['records'] as $accountRecord ) {    
    //echo $oldRecord['Name'].'<br>'; 

  	//Format account name
  	$nameFormatted = strtolower(preg_replace("/[^A-Za-z0-9\-]+/", "", $accountRecord['Name']));   

  	//Build a string of our data
    $accountData = $nameFormatted;  

    if ($accountRecord['Associated_IDs__c']) {

    	//run ID/Name conversion function using the list of apptopia accounts
    	$idNameConvrt = __idNameConvertApps($apptopiaAccounts, $accountRecord['Associated_IDs__c']); 

    	// if Name convert is success
    	// add our IP/Name conversion to our accountData string 
    	if ($idNameConvrt) {
    		$accountData = $accountData.", ".$idNameConvrt;  
    	}  
    } 

    // explode our data, making it an associative array. Makes it simpler to search through.
    $accountData = explode(", ", $accountData);

    //push to our above array the SF account data that includes any dupes associations  
    $accountNameArray[$accountRecord['Id']] = $accountData; 

  }   

  // if not done, then paginate
  if(!$accountIsDone) { curl_setopt($accountQueryCurl, CURLOPT_URL, "$instance_url" . $accountResponse_loop["nextRecordsUrl"]); }
} // end pagination while loop   
curl_close($accountQueryCurl);    

 
/* 
echo "<h3>Account Name Array</h3>";
echo "<pre>";
print_r($accountNameArray);
echo "</pre>";  
die();
 */

/* ******************************************************************************************************* */ 
//Query Salesforce database    
$query = "SELECT Id, Name, Store_URL__c from Apps__c"; //This works! 
//$query = "SELECT Id, Name, (SELECT Id, Name, Store_URL__c FROM Apps__r) FROM Account";  

$queryUrl = "$instance_url/services/data/v20.0/query?q=" . urlencode($query); 
$queryCurl = curl_init($queryUrl);

curl_setopt($queryCurl, CURLOPT_HEADER, false); 
curl_setopt($queryCurl, CURLOPT_RETURNTRANSFER, true);   
curl_setopt($queryCurl, CURLOPT_HTTPHEADER, array("Authorization: OAuth $access_token")); 

$json_response_ini = curl_exec($queryCurl);    
$response_ini = json_decode($json_response_ini, true); // crap out salesforce JSON  
$total_size = $response_ini['totalSize']; 
echo "<p><strong>Total Leads in Salesforce: ".$total_size."</strong></p>"; // Total Size  

//count number of Apps by App Name in each Apptopia App object
$apptopiaAppsInc = 0;
for($x = 0; $x < count($jsonStrObjects); $x++) { 
	$jsonStrDecodeNew = json_decode($jsonStrObjects[$x], true); 
	//If Leads array exists in Apptopia file and it's not null
	if ($jsonStrDecodeNew['Apps']) { 
		//foreach apps object, assign as variable
		foreach ($jsonStrDecodeNew['Apps'] as $appObjects) {
			//filter out apptopia apps 
          	if ( isset($appObjects['Name']) && isset($appObjects['App_Store_Platform__c']) && isset($appObjects['Store_URL__c']) && isset($appObjects['Apptopia_Publisher_ID__c']) ) {  
          		
                /* 
                echo "<pre>";
                print_r($appObjects);
                echo "</pre>";*/  

          		$apptopiaAppsInc++;
      		}
		}
	}
}
echo "<p><strong>Number of Apps from App Objects in Apptopia File</strong>: ".$apptopiaAppsInc."</p>";   

$appArraySF = array(); // Create an array of SF accounts, we'll use it later 
//Start pagination while loop
$isDone = false;   
while(!$isDone) {
	$json_response_loop = curl_exec($queryCurl);   
	$response_loop = json_decode($json_response_loop, true); // crap out salesforce JSON 
	$isDone = $response_loop["done"] == true;      

	/*  
	echo "<pre>";
	print_r($response_loop);
	echo "</pre>";
	*/ 

	// Create an array of SF Leads by Email, we'll use it later 
	foreach ( (array) $response_loop['records'] as $SfdcRecord ) {     
		//push to our above array the SF account name  
		$appArraySF[$SfdcRecord['Id']] = $SfdcRecord['Store_URL__c'];   
	}  

	// if not done, then paginate
	if(!$isDone) { curl_setopt($queryCurl, CURLOPT_URL, "$instance_url" . $response_loop["nextRecordsUrl"]); } 
} // end pagination while loop   
curl_close($queryCurl);    

/* 
echo "<h3>App Name Before Sync Logic</h3>";
echo "<p>#: ".count($appArraySF)."</p>";
echo "<pre>";
print_r($appArraySF);
echo "</pre>";   
*/  

/*
* Function to find the Account ID in salesforce by account name
*/	              	  
function __accountNameConvApps($accountNameArray, $name) {
	$accountId ="";
	foreach ($accountNameArray as $key => $value) {
		if (in_array($name, $value)) {
			$accountId = $key;
		}
	}
	return $accountId;
} 
  
//Loop through our Apptopia file
for($x = 0; $x < count($jsonStrObjects); $x++) {    
	//remake every string in the newly combined array, a readable array
	$jsonStrDecodeNew = json_decode($jsonStrObjects[$x], true);     
 
	/* 
	echo "<pre>";
	var_dump($jsonStrDecodeNew['Apps']);
	echo "</pre>";
	*/   

	//If Leads array exists in Apptopia file and it's not null
	if ($jsonStrDecodeNew['Apps']) { 

		//echo "Number of Leads: ".count($jsonStrDecodeNew['Leads']).'<br>';
		//foreach apps object, assign as variable
		foreach ($jsonStrDecodeNew['Apps'] as $appObjects) {  

			 //filter out apptopia apps  
			 if ( isset($appObjects['Name']) && isset($appObjects['App_Store_Platform__c']) && isset($appObjects['Id']) && isset($appObjects['Apptopia_Publisher_ID__c']) ) {  

				/*  
				// Temporary testing data until MA team gets Sandbox env with correct fields
				$sfAppObjectsTemp =  array(
					"Name" => $appObjects['Name'],  //required 
					"Store_URL__c" => $appObjects['Store_URL__c'],  //required
					"App_Store_Platform__c" => $appObjects['App_Store_Platform__c'], //required 
					//"Account__c" => $record['Id'] //Don't programatically add this field, it'll break the update function
				);  
				*/  

				/**/
	            //Programatically find each field in the App object and add it to an array
	            foreach ($appObjects as $key => $value) {   
	              if ( is_array($value) ) {
	                $arrayStrConv = implode(", ", $value);
	                $sfAppObjects[$key] = $arrayStrConv;
	              } elseif ($key == 'Id') {
	              	//map to App_Store_ID__c
	              	$sfAppObjects['App_Store_ID__c'] = $value; 
	              } elseif ( $key == 'Overall_Rank_as_defined_by_store__c') { 
	              	//map to Apptopia_Overall_Rank__c
	              	$sfAppObjects['Apptopia_Overall_Rank__c'] = $value;  
	              } elseif ( $key == 'Account__c') { 

	              	//$sfAppObjects['Account__c_name'] = $value; //delete this, only use it for testing  
	              	//do not add to sfAppObjects list
	              	//compare string with salesforce
	              	//and find the Id assocaited with the company name in salesforce

	              	//Format account name
  					$valueFormatted = strtolower(preg_replace("/[^A-Za-z0-9\-]+/", "", $value));    
	              	//Run function to find the SFDC account ID
	              	$accountNameConv = __accountNameConvApps($accountNameArray, $valueFormatted); 
	              	//You need to add the account Name/Conv for both update and create functions
	              	
	              	if ($accountNameConv) {
	              		$sfAppObjects['Account__c'] = $accountNameConv;  
	              	} 

	              } else {
					//not in an array
					//key is not Id or Account__C, which will break update function
					//add your value
					$sfAppObjects[$key] = $value; 
	              }    
	            } //foreach      

/* 
				echo "<pre>";
				print_r($sfAppObjects);
				echo "</pre>";  
 */
				if ( false !== $SfdcId = array_search($appObjects['Store_URL__c'], $appArraySF) ) {  
					//echo "Update App: ".$appObjects['Name'].' | '.$appObjects['Store_URL__c'].'<br>'; 
					update_sf_app($SfdcId, $sfAppObjects, $instance_url, $access_token);  
				} else {
		            //Push Account ID to app array because it's required to create a new app
		            //But doesn't have the proper security setting to update
		            $sfAppObjects['Apptopia_Publisher_ID__c'] = $appObjects['Id'];   
 					
 					//echo "Create App: ".$appObjects['Name'].' | '.$appObjects['Store_URL__c'].'<br>'; 
				    create_sf_app($sfAppObjects, $instance_url, $access_token); 

				    //Push new lead  to our SF array so our create function doesn't create the same lead more than once 
				    array_push($appArraySF, $appObjects['Store_URL__c']); 
				} 
				
			} // end filter out apptopia apps   
		} //foreach 
	} //if leads array exists  
 } // Loop through Apptopia file    

/* 
echo "<h3>Account Names After sync logic</h3>";
echo "<pre>";
print_r($appArraySF);
echo "</pre>";
*/ 
 
echo "Finished App Object!<br>";

?>