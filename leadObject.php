<?php 
/* Lead Object Logic
 * This file queries the Salesforce Lead Object, updates all Leads found in the Apptopia data file
 * Then creates new ones if the Apptopia Lead object isn't in Salesforce
 *
 * Author: Digital Reach Agency
 */

echo "<h2>Lead Object</h2>";    

//Error Handling - Error File
  

//Create new log file
$newLogFile = fopen(__DIR__ ."/logs/leads/leads_".date('m-d-Y_hia').".log", "w");
fclose($newLogFile);  
//save permissions properly to newly created log file

$logFile = __DIR__ ."/logs/leads/leads_".date('m-d-Y_hia').".log";  
chmod($logFile, 0775);

/* START UPDATE LEAD FUNCTION */
function update_sf_lead($id, $lead_obj, $instance_url, $access_token) {  
  global $logFile; 
  $url = "$instance_url/services/data/v20.0/sobjects/Lead/$id";   
  $content = json_encode($lead_obj);
  $curl = curl_init($url);
  $date = date('d-m-y h:i:s');   

  curl_setopt($curl, CURLOPT_HEADER, false); 
  curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth $access_token", "Content-type: application/json"));
  curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
  curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
  curl_exec($curl);

  $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  if ( $status != 204 ) {  
    $error_message = "Error, failed to update lead ".$lead_obj['Email'].": call to URL $url failed with status $status, curl_error ".curl_error($curl).", curl_errno ".curl_errno($curl)." ".$date.PHP_EOL; 
    error_log($error_message, 3, $logFile);  
    //exit("Error, failed to update lead: call to URL $url failed with status $status, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
  } else { 
    $success_message = "Success! Status ".$status." updated lead ".$lead_obj['Email']." ".$date.PHP_EOL; 
    error_log($success_message, 3, $logFile);   
  }  

  curl_close($curl);    
} 
/* END UPDATE FUNCTION */ 

/* START CREATE LEAD FUNCTION */
function create_sf_lead($lead_obj, $instance_url, $access_token) {
  global $logFile; 
  $url = "$instance_url/services/data/v20.0/sobjects/Lead/";  
  $content = json_encode($lead_obj);
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
    $error_message = "Error, lead ".$lead_obj['Email']." didn't create: call to URL $url failed with status $status, response $json_response, curl_error".curl_error($curl).", curl_errno ".curl_errno($curl)." ".$date.PHP_EOL;  
    error_log($error_message, 3, $logFile);  
    //exit("Error: call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
  } else { 
    $success_message = "Success! Status ".$status." created lead ".$lead_obj['Email']." ".$date.PHP_EOL; 
    error_log($success_message, 3, $logFile);   
  }   

  curl_close($curl);   
}
/* END CREATE LEAD FUNCTION */

//Function that loops through accounts by Associated IDs
//and finds an account name for each associated ID
//and then returns the Apptopia ID/Name conversion in a string
//we explode/seperate it later
function __idNameConvertLeads($accounts, $assIds) {
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
echo "<strong>Total Accounts in Salesforce: ".$accountTotal."</strong>"; // Total Size  

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
      $idNameConvrt = __idNameConvertLeads($apptopiaAccounts, $accountRecord['Associated_IDs__c']); 

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
//Query Leads from SFDC  
$query = "SELECT Id, Name, Title, FirstName, LastName, Email, Company from Lead"; //This works! 

//Query Salesforce database    
//$query = "SELECT Id, Name, (SELECT Id, Name, Email, Related_Account__c, App__c FROM Leads__r), (SELECT Id, Name, Store_URL__c FROM Apps__r) FROM Account"; 

$queryUrl = "$instance_url/services/data/v20.0/query?q=" . urlencode($query); 
$queryCurl = curl_init($queryUrl);

curl_setopt($queryCurl, CURLOPT_HEADER, false); 
curl_setopt($queryCurl, CURLOPT_RETURNTRANSFER, true);   
curl_setopt($queryCurl, CURLOPT_HTTPHEADER, array("Authorization: OAuth $access_token")); 

$json_response_ini = curl_exec($queryCurl);    
$response_ini = json_decode($json_response_ini, true); // crap out salesforce JSON  
$total_size = $response_ini['totalSize']; 
echo "<p><strong>Total Leads in Salesforce: ".$total_size."</strong></p>"; // Total Size  

//Create the Apptopia Email array here, so the update function doesn't blow up
//For more info, see the update lead function below
$leadApptopiaEmailArray_count = array(); 
//count number of emails that we converted into strings
//in each lead object 
$leadInc = 0;
for($x = 0; $x < count($jsonStrObjects); $x++) {   
  //remake every string in the newly combined array, a readable array
  $jsonStrDecodeNew = json_decode($jsonStrObjects[$x], true); 
  //If Leads array exists and it's not null
  if ($jsonStrDecodeNew['Leads']) {
    //for each leads object
    foreach ($jsonStrDecodeNew['Leads'] as $leadObjects) { 
      //For each Leads object Key

        $apptopiaEmailStr = implode(", ", $leadObjects['Emails']);
        //echo "Emails: ".$apptopiaEmailStr."<br>";  

        //filter out apptopia leads if first name, last name is set and email is not empty
        if ( isset($leadObjects['FirstName']) && isset($leadObjects['LastName']) && !empty($apptopiaEmailStr) && !in_array($apptopiaEmailStr, $leadApptopiaEmailArray_count, true) ) {  
          /* 
          echo "<pre>";
          print_r($leadObjects);
          echo "</pre>"; 
          */ 
          array_push($leadApptopiaEmailArray_count, $apptopiaEmailStr);  
          $leadInc++; 
        }  
 
    } //foreach  
  } //if  
} //for
echo "<p><strong>Number of Emails from Lead Objects in Apptopia File</strong>: ".$leadInc."</p>";     

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
    //echo $oldRecord['Name'].'<br>'; 
     $leadEmailArraySF[$SfdcRecord['Id']] = $SfdcRecord['Email']; 
  }   
  
  // if not done, then paginate
  if(!$isDone) { curl_setopt($queryCurl, CURLOPT_URL, "$instance_url" . $response_loop["nextRecordsUrl"]); }
}// end pagination while loop   
curl_close($queryCurl);      

/* 
echo "<h3>Lead Emails Before Sync Logic</h3>";
echo "<p>#: ".count($leadEmailArraySF)."</p>";
echo "<pre>";
print_r($leadEmailArraySF);
echo "</pre>";   
*/  


/*
* Function to find the Account ID in salesforce by account name
*/                    
function __accountNameConvLeads($accountNameArray, $name) {
  $accountId ="";
  foreach ($accountNameArray as $key => $value) {
    if (in_array($name, $value)) {
      $accountId = $key;
    }
  }
  return $accountId;
} 

//Create the Apptopia Email array here, so the update function doesn't blow up
//For more info, see the update lead function below
$leadApptopiaEmailArray = array(); 
//Loop through our Apptopia file
for($x = 0; $x < count($jsonStrObjects); $x++) {      
  //remake every string in the newly combined array, a readable array
  $jsonStrDecodeNew = json_decode($jsonStrObjects[$x], true);     

  /* 
  echo "<pre>";
  var_dump($jsonStrDecodeNew['Leads']);
  echo "</pre>";
  */     

  //If Leads array exists and it's not null
  if ($jsonStrDecodeNew['Leads']) { 
    //foreach leads object, assign as variable 
    foreach ($jsonStrDecodeNew['Leads'] as $leadObjects) {   
      $apptopiaName = strtolower($leadObjects['FirstName']." ".$leadObjects['LastName']);
      //echo "Name: ".$apptopiaName."<br>";
      $apptopiaEmailConv = implode(", ", $leadObjects['Emails']);
      //echo "Emails: ".$apptopiaEmailConv."<br>";  

      //filter out apptopia leads if first name, last name is set and email is not empty
      if ( isset($leadObjects['FirstName']) && isset($leadObjects['LastName']) && !empty($apptopiaEmailConv) ) { 

        /**/
        //Programatically find each field in the Leads object and add it to an array
        foreach ($leadObjects as $key => $value) {  
          if ( is_array($value) && $key != "Emails" && $key != "App_Names" ) { 
            $arrayStrConv = implode(", ", $value);
            $sfLeadObjects[$key] = $arrayStrConv;
          } elseif ($key == 'Emails') { 
            $sfLeadObjects['Email'] = $apptopiaEmailConv; 
          } elseif ($key == 'company') {
            $sfLeadObjects['Company'] = $value; 
          } elseif ($key == 'Account_Name') { 
            //Get account  ID by comparing what's in our SF account name list 
            //Format account name
            $valueFormatted = strtolower(preg_replace("/[^A-Za-z0-9\-]+/", "", $value));    
            //Run function to find the SFDC account ID
            $accountNameConv = __accountNameConvLeads($accountNameArray, $valueFormatted); 
            //You need to add the account Name/Conv for both update and create functions
            if ($accountNameConv) {
              $sfLeadObjects['Related_Account__c'] = $accountNameConv;  
            }  

          } elseif ($key == 'App_Names') {
            //Do nothing.
            //Not sure about what to do with you yet.
          } else {
            //not in an array
            //so simply just add your value
            $sfLeadObjects[$key] = $value; 
          }   
        } //foreach    

/*
        echo "<pre>";
        print_r($sfLeadObjects);
        echo "</pre>";  
*/   
        
        /**/ 
        // If key in array $leadEmailArraySF is not false, 
        // then update lead by SFDC Id
        // else create lead
        if ( false !== $SfdcId = array_search($apptopiaEmailConv, $leadEmailArraySF) ) { 
          if ( !in_array($apptopiaEmailConv, $leadApptopiaEmailArray, true) ) { 
            //Update Lead record using Apptopia lead data
            //echo "Update Lead: ".$apptopiaEmailConv.' | '.$apptopiaName.' | '.$SfdcId.'<br>';               

            update_sf_lead($SfdcId, $sfLeadObjects, $instance_url, $access_token);                 
             
            // The problem is SFDC won't allow you to create duplicate leads with same information: Name, Email, company.
            // But Apptopia data includes some leads that fit that description where there are multile leads with the same contact info
            // Only difference might be the App_Names field associated with them
            // This is a problem for the sync app, but no solution has been agreed upon
            // So for the momemnt, ignore any proceeding apptopia leads that are the same as previous leads that have already been updated.
            // Push new lead to our Apptopia array so our update function doesn't update the same lead twice
            array_push($leadApptopiaEmailArray, $apptopiaEmailConv);  
          }
        } else {   

          //add Lead Source - Top Chart to leads created by the sync app 
          //Only do this on creating accounts
          $sfLeadObjects['LeadSource'] = 'Top Chart';

          // Create new Lead object record here
          //echo "Create SF Lead: ".$apptopiaEmailConv.' | '.$apptopiaName.' | '.'<br>';
          create_sf_lead($sfLeadObjects, $instance_url, $access_token); 

          //Push new lead  to our SF array so our create function doesn't create the same lead more than once  
          array_push($leadEmailArraySF, $apptopiaEmailConv);   

          //Also need to push email address to our no update list here, so the app doesn't try to update accounts it thinks it created.
          //But due to "Dedupe" (duplicate) rules in SFDC, it didn't
          array_push($leadApptopiaEmailArray, $apptopiaEmailConv);  
        }   

      } // end if filter 
    } // end foreach leads object  
  } // end if leads object exists  
} // end Apptopia Loop      

   
echo "<h3>Leads Emails After Sync Logic</h3>";
echo "<p>#: ".count($leadEmailArraySF)."</p>";
/* 
echo "<pre>";
print_r($leadEmailArraySF);
echo "</pre>"; 
*/  

echo "Finished Lead Object!<br>"; 

?>