<?php
if (!isset($_SERVER['argc'])) {
 echo "You have to run this script from commandline";
 echo "Bye.";
 exit;
}

require('config.php');
require('functions.php');

$vpopmailDir = rtrim($vpopmailDir, '/') . '/';

$domains_otto = array();
$domains_otto_byuser = array();
$domains_homer = array();

$manuallyCheck = array();

$count = array();
$count['domain'] = 0;
$count['aliasdomain'] = 0;
$count['user'] = 0;
$count['catchall'] = 0;
$count['forward'] = 0;

// Create MySQL connection
$con = mysqli_connect($mysql_hostname, $mysql_username, $mysql_password, $mysql_database);
if (mysqli_connect_errno($con)) {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
}
 
 
// Create SOAP Connection
$server_id = '9';
$mysoap = new SoapClient(null, array('location' => $soap_location,
                                     'uri'      => $soap_uri,
                                     'trace' => 1,
                                     'exceptions' => 1)
                                    );

try {
    if($session_id = $mysoap->login($soap_username, $soap_password)) {
        // echo 'Logged successfull. Session ID:'.$session_id.'<br />';
    }

//    $functions = $mysoap->get_function_list($session_id);
//    asort($functions);
//    print_r($functions);

/*
    $clients = $mysoap->client_get_all($session_id);
    print_r($clients);
*/
  
/*
    foreach($clients as $key=>$value) {
      $client = $mysoap->client_get($session_id, $value);
      print_r($client);
    }
*/   
     
/*   
    $domains = $mysoap->domains_get_all_by_user($session_id, array());
    print_r($domains);
*/
  
   getDomainsFromOtto();
   getDomainsFromHomer();

} catch (SoapFault $e) {
   echo $mysoap->__getLastResponse();
   echo 'SOAP Error: '.$e->getMessage();
}

if (isset($argv[1])) {
  $onlyOneDomain = $argv[1];
}
?>
