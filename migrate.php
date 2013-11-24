#!/usr/bin/php
<?php
require('init.php');

try {
// **** Start Migration
    echo "Starting migration... \n";

    $dotqmailfiles = array();
    $i = 0;
    
    foreach($domains_otto as $domain=>$user) {

      // only specific domain
      if(isset($onlyOneDomain) and $domain != $onlyOneDomain) continue;

      if(array_key_exists($domain, $domains_homer)) {
        $i++;
        $client_id = $mysoap->client_get_id($session_id, $user);
        addMailDomain($client_id, $domain, true);
  
        if(is_array($domains_homer[$domain])) {
          foreach($domains_homer[$domain] as $key => $aliasdomain) {
            printf(" Add alias domains\n");
            addMailDomain($client_id, $aliasdomain, true);
            addMailDomainAlias($client_id, $aliasdomain, $domain, true);
          }
        }else{
          printf(" No alias domains\n");
        }

        // check each user of that domain (.qmail forwards etc)
	$users = getOldUsersFromDomain($domain);
	foreach($users as $key => $user) {
	  addMailUser($client_id, $user['pw_name'], $user['pw_domain'], $user['pw_clear_passwd'], $user['pw_gecos'], true);

          $file = $vpopmailDir . "domains/" . $domain . "/" . $user['pw_name'] . "/.qmail";
          $dotqmailfiles[] = $file;
          $handle = @fopen($file, "r");
          if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {
              if($buffer == "| /var/qmail/bin/preline -f /usr/lib/dovecot/deliver -d \$EXT@\$USER\n") {
                // Filter active
                echo "  Sieve filter is active\n";
                $manuallyCheck[] = sprintf("sieve filter: %s", $file);
              }elseif($buffer == "| /var/qmail/bin/preline -f /usr/lib/dovecot/deliver -d " . $user['pw_name'] . "@" . $domain ."\n") {
                // Filter active
                echo "  Sieve filter is active\n";
                $manuallyCheck[] = sprintf("sieve filter: %s", $file);
              }elseif(preg_match("/^&(.*)/", $buffer, $matches)) {
                // Mail forward
                $source = sprintf("%s@%s", $user['pw_name'], $domain);
                $destination = $matches[1];
                addMailForward($client_id, $source, $destination, true);
              }else{
                // unknown
                printf("unknown (%s): %s\n", __LINE__. $buffer);
                exit;
              }
            }
            if (!feof($handle)) {
              echo "Error: unexpected fgets() fail\n";
            }
            fclose($handle);
          }
	}

	// check domains .qmail-default
	$file = $vpopmailDir . "domains/" . $domain . "/.qmail-default";
        $dotqmailfiles[] = $file;
	$handle = @fopen($file, "r");
        if ($handle) {
          while (($buffer = fgets($handle, 4096)) !== false) {
            $regex = sprintf("\| %sbin/vdelivermail '' %sdomains/%s/(.*)", $vpopmailDir, $vpopmailDir, $domain);
            $regex = str_replace('/', '\/', $regex);
            if($buffer == "| /home/vpopmail/bin/vdelivermail '' bounce-no-mailbox\n") {
              // No catch all
              echo " Default: bounce-no-mailbox\n";
            }elseif(preg_match("/^" . $regex . "\n/", $buffer, $matches)) {
              // catch all
              // print_r($matches);
              $destination = $matches[1] . "@" . $domain;
              addMailCatchAll($client_id, $domain, $destination, true);
            }else{
              // unknown
              printf("unknown (%s): %s\n", __LINE__. $buffer);
              // echo $regex;
              exit;
            }
          }
          if (!feof($handle)) {
            echo "Error: unexpected fgets() fail\n";
          }
          fclose($handle);
        }

	// check all other .qmail
        if ($dirhandle = opendir($vpopmailDir . "domains/" . $domain)) {
            while (false !== ($entry = readdir($dirhandle))) {
		if (preg_match("/^\.qmail-(.*)/", $entry, $matches)) {
		  $file = $vpopmailDir . "domains/"  . $domain . "/" . $entry;
		  if(!in_array($file, $dotqmailfiles)) {
		    $user = $matches[1];
                    $dotqmailfiles[] = $file;
                    $handle = @fopen($file, "r");
                    if ($handle) {
                      while (($buffer = fgets($handle, 4096)) !== false) {
                        if(preg_match("/^&(.*)/", $buffer, $matches)) {
                            $source = sprintf("%s@%s", $user, $domain);
                            $destination = $matches[1];
                            addMailForward($client_id, $source, $destination, true);
                        }else{
                            echo "  $user";
                            printf("\n  unknown (%s): %s\n", __LINE__. $buffer);
                            exit;
                        }
                      }
                      if (!feof($handle)) {
                        echo "Error: unexpected fgets() fail\n";
                      }
                      fclose($handle);
                    }

                  }
                }
            }
            closedir($dirhandle);
        }

        unset($domains_homer[$domain]);
        
      }else{
        printf("Domain not found  %-30s \n", strtoupper($domain));
        $manuallyCheck[] = sprintf("domain not found: %s", $domain);
      }
      if($i == 5) break;
    }

    echo "\nMigration finished:\n\n";
        
    print_r($count);
    print_r($manuallyCheck);

#    echo "DOMAINS NOT FOUND on otto\n";
#    print_r($domains_homer);

//*** LOGOUT
    if($mysoap->logout($session_id)) {
        // echo 'Logged out.<br />';
    }


} catch (SoapFault $e) {
    echo $mysoap->__getLastResponse();
    echo 'SOAP Error: '.$e->getMessage();
}

mysqli_close($con);
?>