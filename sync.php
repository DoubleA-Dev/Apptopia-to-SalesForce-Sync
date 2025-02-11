<?php 

/* Apptopia data sync with Salesforce
 * Cron Job
 *
 * This file is the primary file to run the Apptopia -> Salesforce sync application
 *
 * Author: Digital Reach Agency
 */
 
require __DIR__ .'/vendor/autoload.php'; // Dependencies installed by Composer 

// start references to AWS namespace
use Aws\Sts\StsClient; 
use Aws\S3\S3Client; 
use Aws\Exception\AwsException;   
 
?>

<!doctype html>
<html class="no-js" lang="">

<head>
  <meta charset="utf-8">
  <title>Apptopia Sync to Salesforce Integration App</title>
  <meta name="description" content="">
  <meta name="viewport" content="width=device-width, initial-scale=1">   
</head>

<body>     

<h2>S3 File Download Function</h2>  

<?php   

/* Useful AWS S3 CLI API Calls:

//List all buckets in another account
aws s3 ls s3://chartboost-apptopia-records

//List all buckets in another account by user profile
aws s3 ls s3://chartboost-apptopia-records
aws s3 ls s3://chartboost-apptopia-records

//Get Bucket Location
aws s3api get-bucket-location --bucket chartboost-apptopia-records

// Copy all files from bucket recursively
aws s3 cp s3://chartboost-apptopia-records /downloads --recursive

*/
   

// Connects to S3 Buckets
// this will simply read AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY from env vars
// you will need to set env vars key and secret through the CLI
$s3 = new Aws\S3\S3Client([     
    'region'   => 'us-east-1',
    'version'  => 'latest' 
]);   
/* 
echo "<pre>";
print_r($s3);
echo "</pre>";
*/  

// Where the files will be sourced from
// Apptopia's S3 Bucket: s3://chartboost-apptopia-records 
//$source = 's3://chartboost-apptopia-records/';
$source = 'chartboost-apptopia-records';    

/************************************************************ 
 * Function to query all buckets and get the latest
 ************************************************************/
/* 
//Listing all S3 Bucket
$buckets = $client->listBuckets();    
  
echo "<pre>";
print_r($buckets);
echo "</pre>"; 


// Loop through each bucket
$bucketNameArray = [];  
foreach ($buckets['Buckets'] as $bucket) { 
     //echo 'Bucket Name: '. $bucket['Name'] . "<br>"; 
     //echo 'Creation Date: '. $bucket['CreationDate']->format(\DateTime::ISO8601) . "<br><br>";

     //Push bucket name and created date to an array
     //convert date to a number
     $bucketNameArray[$bucket['Name']] = strtotime($bucket['CreationDate']->format(\DateTime::ISO8601)); 
}  
 
echo "<pre>";
print_r($bucketNameArray);
echo "</pre>"; 

//Find Largets number/date in our new array
$latestBucket = max($bucketNameArray); 
$bucketName = array_search($latestBucket, $bucketNameArray); //echo "Latest Bucket: ".$bucketName."<br>";   
//$bucketName = 'chartboost-json-test'; // Chartboost 
*/

/************************************************************ 
 * Function to query all objects in the latest bucket, 
 * and get the latest objects keyname
 ************************************************************/ 
 

$keyNameArray = []; 
 
// Use the plain API (returns ONLY up to 1000 of your objects). 
try { 
  $objects = $s3->listObjects([
      'Bucket' => $source
  ]); 
} catch (Exception $e)  {
  echo "<pre>";
  echo $e;
  echo "</pre>";
}
 
/*  
echo "<pre>";
print_r($objects);
echo "</pre>";
*/ 


foreach ($objects['Contents']  as $object) {
  //Echo each Object by key in bucket
  //echo $object['Key'] . '<br>';

  //If Object Key is a json file
  //Push key name and last modified date to an array
  //convert date to a number
  if (strpos($object['Key'], '.json') !== false) {
      $keyNameArray[$object['Key']] = strtotime($object['LastModified']->format(\DateTime::ISO8601));  
  }  
}

/* */
echo '<h3>JSON Files on S3</h3>';
echo "<pre>";
print_r($keyNameArray);
echo "</pre>";


//Find Largets number/date in our new array
$latestObject = max($keyNameArray); 
$keyname = array_search($latestObject, $keyNameArray); //echo "Latest Object: ".$keyname."<br>";
$newFileName = str_replace("/", "-", $keyname); //sanitize our file name for saving

/**************************************************************************************  
 * Function to find latest local file
 ************************************************************/  
  
$localDir = __DIR__ .'/downloads';
$localFilesDir = scandir($localDir); 

$localFilesArary = [];
foreach($localFilesDir as $file) { 
  if ($file != "." && $file != ".." && $file != ".gitignore") {
    $localFilesArary[$file] = filemtime($localDir.'/'.$file); 
    //echo "file: ".$file." | ".filemtime($localDir.'/'.$file).'<br>';
  } 
}

/**/
echo '<h3>JSON Files saved on Local</h3>';
echo "<pre>";
print_r($localFilesArary);
echo "</pre>";
 

//Find Largets number/date in our new local files array
$latestLocalFile = ($localFilesArary) ? max($localFilesArary) : 0;  
$latestLocalFileName = array_search($latestLocalFile, $localFilesArary); //echo "Latest Object: ".$keyname."<br>";   

/**************************************************************************************  
 * Function to download latest object from Apptopia-Chartboost bucket
 ************************************************************/  

echo "Current Server Date: ".$latestObject . "<br>"; 
//get local file mod date
echo "Current File Date: ".$latestLocalFile . "<br>";

/**/
// conditional to check modification date, should fire on page load
if(  $latestObject > $latestLocalFile ) {
  // file was modified
  echo "<p>File <strong>was</strong> modified!</p>"; 
  echo "<p>Download our file.</p>"; 
  //downloads our file 
  $result = $s3->getObject([
      'Bucket' => $source,
      'Key'    => $keyname,
      'SaveAs' => __DIR__.'/downloads/'.$newFileName //this part saves the object apparently
  ]); 

} else { 
  echo "<p>File <strong>was not</strong> modified!</p>";
  //exit('There is no new Apptopia file. Stop app.');
}    

/************************************************************ 
 * Function to uncompress .gz file
 ************************************************************/

/* */  
//This input should be from somewhere else, hard-coded in this example
$dir = __DIR__ .'/downloads/';
//$file_name = 'part-00000.json.gz';
$out_file_name = str_replace('.gz', '', $newFileName); 

echo 'Directory Name: '.$dir.$out_file_name.'<br>';

//Check if target file was already extracted
if (!file_exists($dir.$out_file_name)) { 

  //Check if the non extratcted target file exists
  if ($newFileName) {
    echo "Target file was downloaded, but not unzipped. Extract away!"."<br>";
    // Raising this value may increase performance
    $buffer_size = 4096; // read 4kb at a time 

    //chmod($dir.$newFileName, 0775);

    // Open our files (in binary mode)
    $gFile = gzopen($dir.$newFileName, 'rb');
    $out_file = fopen($dir.$out_file_name, 'wb'); 

    // Keep repeating until the end of the input file
    while (!gzeof($gFile)) {
        // Read buffer-size bytes
        // Both fwrite and gzread and binary-safe
        fwrite($out_file, gzread($gFile, $buffer_size));
    }

    // Files are done, close files
    fclose($out_file);
    gzclose($gFile); 

    //save permissions properly to newly unzipped apptopia file
    chmod($dir.$out_file_name, 0775);  

  } else {
    echo "Target file doesn't exist for .gz exctraction."."<br>";
  }  

} else {
  echo "Target file was already extracted. Dont unzip!"."<br>";
} 
 
/************************************************************************************** 
 * Get contents from newly downloaded file and start parsing the data
 **************************************************************************************/ 

//Local JSON file pulled down from AWS server
//After we run function to uncompress the file
$str = file_get_contents(__DIR__ .'/downloads/'.$out_file_name);  

/*
echo "<pre>";
print_r(json_encode($str));
echo "</pre>";
*/ 
 
/**
 * json_split_objects - Return an array of many JSON objects
 *
 * In some applications (such as PHPUnit, or salt), JSON output is presented as multiple
 * objects, which you cannot simply pass in to json_decode(). This function will split
 * the JSON objects apart and return them as an array of strings, one object per indice.
 *
 * http://ryanuber.com/07-31-2012/split-and-decode-json-php.html
 *
 * @param string $json  The JSON data to parse
 *
 * @return array
*/
/* */  
function json_split_objects($json) {
    $q = FALSE;
    $len = strlen($json);
    for($l=$c=$i=0;$i<$len;$i++)
    {   
        $json[$i] == '"' && ($i>0?$json[$i-1]:'') != '\\' && $q = !$q;
        if(!$q && in_array($json[$i], array(" ", "\r", "\n", "\t"))){continue;}
        in_array($json[$i], array('{', '[')) && !$q && $l++;
        in_array($json[$i], array('}', ']')) && !$q && $l--;
        (isset($objects[$c]) && $objects[$c] .= $json[$i]) || $objects[$c] = $json[$i];
        $c += ($l == 0);
    }   
    return $objects;
}   

//Now you can do Array things to the uploaded JSONL file, if the JSON is malformed. 
//Here is where you'd looop through it and pull the Contacts into a new list 
$jsonStrObjects = json_split_objects($str);  

/*
echo "<pre>";
var_dump( $jsonStrObjects );
echo "</pre>";  
*/ 

// DO NOT DELETE
?> 

<h2>Salesforce API</h2>

<?php   

  $access_token = $_SESSION['access_token'];
  $instance_url = $_SESSION['instance_url'];  

  if (!isset($access_token) || $access_token == "") { 
    exit("Error - access token missing!"); 
  } else {
    //echo 'Access Token:'.$access_token.'<br>';
  } 

  if (!isset($instance_url) || $instance_url == "") { 
    exit("Error - access token missing!"); 
  } else {
    //echo 'Instance URL:'.$instance_url.'<br><br>'; 
  }   

/*
  //Access tokens saved to .txt files and pulled from there.
  $access_token = file_get_contents(__DIR__ .'/tmp/.access_token');
  $instance_url = file_get_contents(__DIR__ .'/tmp/.instance_url'); 
  echo "<h2>Tokens Saved Locally:</h2>"; 
  echo "Access Token saved locally: ".$access_token.'<br>'; 
  echo "Instace URL saved locally: ".$instance_url.'<br>';  
*/
  
  /* Create log directories */
  if (!file_exists(__DIR__.'/logs')) {
    mkdir(__DIR__.'/logs', 0777, true); 
  }

  //Account Logs
  if (!file_exists(__DIR__.'/logs/account')) {
    mkdir(__DIR__.'/logs/account', 0777, true);
  }

  //Leads Logs
  if (!file_exists(__DIR__.'/logs/leads')) {
    mkdir(__DIR__.'/logs/leads', 0777, true);
  }

  //Apps Logs
  if (!file_exists(__DIR__.'/logs/apps')) {
    mkdir(__DIR__.'/logs/apps', 0777, true);
  }  

  die(); 
  require_once __DIR__ .'/accountObject.php';
  require_once __DIR__ .'/leadObject.php';
  require_once __DIR__ .'/appObject.php'; 

?> 

</body> 
</html>