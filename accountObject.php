<?php

/* Account Object Logic
 * This file queries the Salesforce Account Object, updates all accounts found in the Apptopia data file
 * Then creates new ones if the Apptopia account object isn't in Salesforce
 *
 * Author: Digital Reach Agency
 */ 
echo "<h2>Account Object</h2>";
   
//Error Handling - Error File  
//Create new log file
$createNewlogFile = fopen(__DIR__ ."/logs/account/account_".date('m-d-Y_hia').".log", "w");
fclose($createNewlogFile);
//save permissions properly to newly created log file  

$logFile = __DIR__ ."/logs/account/account_".date('m-d-Y_hia').".log";  
chmod($logFile, 0775);

/* START UPDATE ACCOUNT FUNCTION */
function update_sf_account($id, $account_obj, $instance_url, $access_token) {  
  global $logFile;
  $url = "$instance_url/services/data/v20.0/sobjects/Account/$id";   
  $content = json_encode($account_obj);
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
    $error_message = "Error, failed to update".$account_obj['Name'].": call to URL $url failed with status $status, curl_error ".curl_error($curl).", curl_errno ".curl_errno($curl)." ".$date.PHP_EOL; 
    error_log($error_message, 3, $logFile);  
    //exit("Error, failed to update $account_obj['Name']: call to URL $url failed with status $status, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
  } else {  
    $success_message = "Success! Status ".$status." updated account ".$account_obj['Name']." ".$date.PHP_EOL; 
    error_log($success_message, 3, $logFile);   
  }   
  curl_close($curl); 
} 
/* END UPDATE FUNCTION */ 
 
 /* START CREATE ACCOUNT FUNCTION */
function create_sf_account($name, $account_obj, $instance_url, $access_token) {
  global $logFile;
  $url = "$instance_url/services/data/v20.0/sobjects/Account/";  
  $content = json_encode($account_obj);
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
    $error_message = "Error, failed to create ".$account_obj['Name'].": call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl)." ".$date.PHP_EOL; 
    error_log($error_message, 3, $logFile); 
    //exit("Error, failed to create ".$account_obj['Name'].": call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
  } else { 
    $success_message = "Success! Status ".$status." created account ".$account_obj['Name']." ".$date.PHP_EOL; 
    error_log($success_message, 3, $logFile);   
  }  
  curl_close($curl); 
}
/* END CREATE ACCOUNT FUNCTION */
 

//Query Salesforce database
$query = "SELECT Name, Id, Website, BillingCountry, Apptopia_Publisher_ID__c, recordtypeid, Additional_Website_URLs__c, Associated_IDs__c from Account"; 

//Build our URL to the Salesforce database
$queryUrl = "$instance_url/services/data/v20.0/query?q=" . urlencode($query); 
$queryCurl = curl_init($queryUrl);

curl_setopt($queryCurl, CURLOPT_HEADER, false); 
curl_setopt($queryCurl, CURLOPT_RETURNTRANSFER, true);   
curl_setopt($queryCurl, CURLOPT_HTTPHEADER, array("Authorization: OAuth $access_token")); 
 
$json_response_ini = curl_exec($queryCurl);    
if (!$json_response_ini) { exit('SFDC did not send a response when queried. Most likely a problem with the security token.'); } //error check to make sure security token is correct
$response_ini = json_decode($json_response_ini, true); // crap out salesforce JSON  
$total_size = $response_ini['totalSize']; 
echo "<p><strong>Total Accounts in Salesforce before filtering of duplicates: ".$total_size."</strong></p>"; // Total Size     

/*
//Test SFDC connection and make sure data is coming through in the response
echo "<pre>";
var_dump($response_ini);
echo "</pre>";   
*/

$arraySF = array(); // Create an array of SF accounts, we'll use it later 
$arraySFdupes = array();
//Start pagination while loop    
$isDone = false;  
while(!$isDone) {

  $json_response_loop = curl_exec($queryCurl);   
  $response_loop = json_decode($json_response_loop, true); // crap out salesforce JSON 
  $isDone = $response_loop["done"] == true;     
   
 /*   
  echo "<pre>";
  var_dump($response_loop);
  echo "</pre>"; 
*/ 

  foreach ( (array) $response_loop['records'] as $SfdcRecord ) {     
    //push to our above array the SF account name
    //array_push($arraySF, $SfdcRecord['Apptopia_Publisher_ID__c']." | ".$SfdcRecord['Name']);   
    //array_push($arraySF, $SfdcRecord['Apptopia_Publisher_ID__c']);   
    $arraySF[$SfdcRecord['Id']] = $SfdcRecord['Apptopia_Publisher_ID__c']; 

    if ($SfdcRecord['Associated_IDs__c']) {
      //if SFDC account has an Associated ID string
      //seperate string where there's a comma deliminater 
      $sfdcAssocIDs = explode(", ", $SfdcRecord['Associated_IDs__c']);
      
      // Add primary account aptopia ID as key to new array
      // where associated account id is key
      $arraySFdupes[$SfdcRecord['Apptopia_Publisher_ID__c']] = $sfdcAssocIDs;
    }

  } //end loop through SFDC  

  // if not done, then paginate
  if (!$isDone) { curl_setopt($queryCurl, CURLOPT_URL, "$instance_url" . $response_loop["nextRecordsUrl"]); }
}// end pagination while loop   
curl_close($queryCurl);      

/*
echo "<h2>Account Publisher IDs Before Sync Logic</h2>";
echo "<p>#: ".count($arraySF)."</p>";
echo "<pre>";
print_r($arraySF);
echo "</pre>";  
  */ 

/*
echo "<h2>arraySFdupes</h2>"; 
echo "<pre>";
var_dump($arraySFdupes);
echo "</pre>";  
   */ 

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
echo "<p><strong>Total Number of Accounts from Account Objects in Apptopia File before filtering of duplicates</strong>: ".$accountInc."</p>";     

/*
$testingThirdDucplicate = array(
    "Name"=> "37GAMES",
    "Apptopia_Publisher_ID__c"=> 123467,
    "HQ_Country__c"=> "CN",
    "Website"=> "https://www.digitalreachagency.com",
    "name_formatted"=> "37games",
    "Associated_IDs__c"=> 0
); 
array_push($apptopiaAccounts, $testingThirdDucplicate);
*/

//sort list
sort($apptopiaAccounts); 

/* 
echo "<h1>apptopiaAccounts</h1>";
echo "<pre>";
var_dump($apptopiaAccounts);
echo "</pre>";   
 */ 

$apptopiaAccountsFiltered = array(); //create empty list to hold the primary accounts
$apptopiaAccountsDupes = array(); //create empty list to hold the Apptopia IDs of the duplicate accounts

/**
* Matcher function that loops through an array looking for same name but not same Apptopia ID 
* Purpose is to find associative Apptopia Publisher ID of duplicate accounts
* @param array $array = The list you want to loop through
* @param array $data = The data you want to match and add to
*/

function _matcher($array, $data) {   
    $associatedData = array();

    foreach ($array as $item) {    
      //if account name = the value sent through the function
      // and account apptopia id doesn't equal the apptopia id of the account we're currently on      
      if ($item['name_formatted'] == $data['name_formatted'] && $item['Apptopia_Publisher_ID__c'] != $data['Apptopia_Publisher_ID__c']) {   
        array_push($associatedData, $item);  
      } 

    }   

    //return duplicate account data in new array
    return $associatedData; 
}   

foreach ($apptopiaAccounts as $account) {   
  $accountAssociatedIds = $account['Associated_IDs__c']; //Variable that will help concat associatedIds if we find them, rather than overwrite them 

  // echo "<h1>".$account['name_formatted']."</h1>";

  //Call our matcher function
  //Pass Array, Key, Value and apptopiaID
  $matched = _matcher($apptopiaAccounts, $account);    
 
  // echo "<pre>";
  // var_dump($matched);
  // echo "</pre>";  

  if ($matched) { 
    //If a match is found 

    //Check to see if the apptopia Publisher ID has been added to our duplicate account list
    if (!in_array($account['Apptopia_Publisher_ID__c'], $apptopiaAccountsDupes)) {
      //if not, build our associated IDs      

      //concat variables
      $assocIds = "";
      $assocWeb = "";

      foreach ($matched as $dupAcct) {
        $assocIds = ($assocIds == "") ? $dupAcct['Apptopia_Publisher_ID__c'] : $assocIds.', '.$dupAcct['Apptopia_Publisher_ID__c']; 
        $assocWeb = ($assocWeb == "") ? $dupAcct['Website'] : $assocWeb.', '.$dupAcct['Website'];      
        array_push($apptopiaAccountsDupes, $dupAcct['Apptopia_Publisher_ID__c']); //Add Apptopia Id of the duplicate to our duplicate list
      }    
        
      $account['Associated_IDs__c'] = $assocIds;    
      $account['Additional_Website_URLs__c'] = $assocWeb;   
      array_push($apptopiaAccountsFiltered, $account); //add acount with associated ids to our filtered, primary acocunt list  
    }   
  } else {
    //No duplicate found, simply add the account to our primary account list
    array_push($apptopiaAccountsFiltered, $account);
  }   
} 

/*
echo "<h2>Apptopia Duplicates filter</h2>";
echo "<strong>Number of accounts that are duplicate:</strong>".count($apptopiaAccountsDupes)."<br>";
echo "<strong>Number of accounts minus the duplicates:</strong> ".count($apptopiaAccountsFiltered)."<br>";

echo "<h1>apptopiaAccountsDupes</h1>";
echo "<pre>";
var_dump($apptopiaAccountsFiltered);
echo "</pre>";    
  */

/*
Find duplicate accounts in salesforce and combine the dupe data with the primary account
*/

function _sfMatcher($array, $assIds) {
    $sfAssociatedData = array(); //the data our function will return
    $sfAssIds = array(); //relative list to hold dupliate Ids if there's more than one
 
    foreach ($assIds as $assId) {
      array_push($sfAssIds, $assId);
    }  

    foreach ($array as $item) {     
      //if account name = the value sent through the function
      // and account apptopia id doesn't equal the apptopia id of the account we're currently on      
      if (in_array($item['Apptopia_Publisher_ID__c'], $sfAssIds)) {
        array_push($sfAssociatedData, $item);  
      }  
    }   

    //return duplicate account data in new array
    return $sfAssociatedData; 
} 

$salesForceAccountsFiltered = array(); //create empty list to hold the primary accounts for salesforce
foreach ($apptopiaAccountsFiltered as $account) { 

  //loop through each SF primary apptopia id that's set as a key
  //then find the values, which are the duplicate accounts set in SF
  //and then run anthor function that returns the duplicate acount data
  //and then combine them for our filtered array finally
  foreach ($arraySFdupes as $primaryId => $assIds) {    
    if ($primaryId == $account['Apptopia_Publisher_ID__c']) {
      // echo $account['name_formatted'].'<br>';
      // echo "<pre>";
      // var_dump($assIds);
      // echo "</pre>"; 
    
      //echo $assId."<br>";
      $sfMatcher = _sfMatcher($apptopiaAccountsFiltered, $assIds);    

      if ($sfMatcher) {
        // echo "<pre>";
        // var_dump($sfMatcher);
        // echo "</pre>";

        //concat variables
        $assocIds = "";
        $assocWeb = "";

        foreach ($sfMatcher as $sfDupeAccount) { 
          $assocIds = ($assocIds == "") ? $sfDupeAccount['Apptopia_Publisher_ID__c'] : $assocIds.', '.$sfDupeAccount['Apptopia_Publisher_ID__c']; 
          $assocWeb = ($assocWeb == "") ? $sfDupeAccount['Website'] : $assocWeb.', '.$sfDupeAccount['Website'];   
          array_push($apptopiaAccountsDupes, $sfDupeAccount['Apptopia_Publisher_ID__c']); //Add Apptopia Id of the duplicate to our duplicate list
        } // end $sfMatcher

        $account['Associated_IDs__c'] = $assocIds;    
        $account['Additional_Website_URLs__c'] = $assocWeb;    
        array_push($salesForceAccountsFiltered, $account); //add acount with associated ids to our filtered, primary acocunt list  

      }//end if $sfMatcher    
    } 
  } // foreach $arraySFdupes   

} // end $apptopiaAccountsFiltered


/*
Now add the rest of the accounts that haven't been flagged as duplicates to our list
*/ 
function _idMatcher($haystack, $needle) {  
  $dupsIdsInSF = array();
  foreach ($haystack as $key) { 
    array_push($dupsIdsInSF, $key['Apptopia_Publisher_ID__c']);
  }   
 
  if (in_array($needle, $dupsIdsInSF)) {
    return true;
  } else {
    return false;
  } 
}

foreach ($apptopiaAccountsFiltered as $account) {     
  $idMatcher = _idMatcher($salesForceAccountsFiltered, $account['Apptopia_Publisher_ID__c']);
  //echo "idMatcher: ".$idMatcher."<br>";

  if (!in_array($account['Apptopia_Publisher_ID__c'], $apptopiaAccountsDupes) && !$idMatcher) { 
    //if there is no duplicate match
    //simply push account data to new SFDC filtered list
    array_push($salesForceAccountsFiltered, $account);
  } 

} 
sort($salesForceAccountsFiltered);

 
echo "<h1>salesForceAccountsFiltered</h1>";
echo "<p><strong>Count number of accounts for filtered Apptopia List:</strong> ".count($apptopiaAccountsFiltered)."</p>";
//echo "<p><strong>Count number of dupes from Salesforce:</strong> ".count($arraySFdupes)."</p>";
echo "<p><strong>Count Total salesForceAccountsFiltered:</strong> ".count($salesForceAccountsFiltered)."</p>";

/*
echo "<pre>";
var_dump($salesForceAccountsFiltered);
echo "</pre>";   
 */

/*
// Temporary testing data until MA team gets Sandbox env with correct fields
$sfAccountObjectsTemp =  array(  
  "Name" => '37GAMES', 
  "Website" => 'https://mnews.37games.com/eoa/',
  "Apptopia_Publisher_ID__c" => '763843'
);  
*/

//$sfAccountObjects = array();
foreach ($salesForceAccountsFiltered as $account) { 

  foreach ($account as $key => $value) {
    if ( $key == 'name_formatted' ) {
      //dont add to SF list for update/create functions
      //because name_formatted was just a way to find duplicates
      //SF won't take it
    } elseif ( is_array($value) ) {
      //If is array string
      //convert to string and then add value to array
      $strConv = implode(", ", $value);
      //echo "string: ".$strConv.'<br>';
      $sfAccountObjects[$key] = $strConv;  
    } else {
      //key is not array
      //add to our new array  
      $sfAccountObjects[$key] = $value; 
    }  
  }   

/*   
  //Array of account info you're going to create/update in SFDC
  echo "<pre>";
  var_dump($sfAccountObjects);
  echo "</pre>";
*/ 

  //If Apptopia Publisher ID is in our SFDC array, Update account, else create it
  //if ( in_array($account['Apptopia_Publisher_ID__c'], $arraySF) ) { 
  if ( false !== $SfdcId = array_search($account['Apptopia_Publisher_ID__c'], $arraySF) ) {
    
    // echo "Update Account: ".$account['Name'].' | '.$account['Apptopia_Publisher_ID__c'].'<br>'; 
    // echo "<pre>";
    // var_dump($sfAccountObjects);
    // echo "</pre>"; 
    // echo $SfdcId.'<br>';  
    update_sf_account($SfdcId, $sfAccountObjects, $instance_url, $access_token);  

  } else {    

    //add field to our RecordTypeId of prospect to SF array
    //Only do this on creating accounts
    $sfAccountObjects['RecordTypeId'] = '012E00000002LuH';
    
    // echo "Create SF Account: ".$account['Name'].' | '.$account['Apptopia_Publisher_ID__c'].'<br>'; 
    // echo "<pre>";
    // var_dump($sfAccountObjects);
    // echo "</pre>";
    create_sf_account($account['Name'], $sfAccountObjects, $instance_url, $access_token);

    //Push new account name to our SF array so our create function doesn't create the same account more than once
    array_push($arraySF, $account['Apptopia_Publisher_ID__c']); 
  }   
} // /foreach

/*   
//asort($arraySF); //sort by alphabet for debugging
echo "<h3>Account Publisher IDs After sync logic</h3>";
echo "<p>#: ".count($arraySF)."</p>";
echo "<pre>";
print_r($arraySF);
echo "</pre>";  
*/

echo "Finished Account Object!<br>";

?>