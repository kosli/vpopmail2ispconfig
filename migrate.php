#!/usr/bin/php
<?php
require('init.php');

$domains_homer_all = $domains_homer;

// print_r($domains_homer);
// die;

try {
// **** Start Migration
    echo "Starting migration... \n";

    $dotqmailfiles = array();
    $migrateddomains = array();
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
            addMailDomain($client_id, $aliasdomain, true, true);
            addMailDomainAlias($client_id, $aliasdomain, $domain, true);
            $migrateddomains[] = $aliasdomain;
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
                $manuallyCheck[$file] = 'sieve filter'; // sprintf("sieve filter: %s", $file);
              }elseif($buffer == "| /var/qmail/bin/preline -f /usr/lib/dovecot/deliver -d " . $user['pw_name'] . "@" . $domain ."\n") {
                // Filter active
                echo "  Sieve filter is active\n";
                $manuallyCheck[$file] = 'sieve filter';
              }elseif(preg_match("/^&(.*)/", $buffer, $matches)) {
                // Mail forward
                if($user['pw_name'] == 'postmaster') {
                  // Special handling of postmaster forwards
                  $manuallyCheck[$user['pw_Name'] . "@" . $domain] = 'user has forward';
                }else{
                  $source = sprintf("%s@%s", $user['pw_name'], $domain);
                  $destination = $matches[1];
                  addMailForward($client_id, $source, $destination, true);
                }
              }else{
                // unknown
                printf("unknown (%s): %s\n", __LINE__, $buffer);
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
            }elseif(preg_match("/^\| \/home\/vpopmail\/bin\/vdelivermail '' (.*)/", $buffer, $matches)) {
              // catch all
              addMailCatchAll($client_id, $domain, $matches[1], true);
            }else{
              // unknown
              printf("unknown (%s): %s\n", __LINE__, $buffer);
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
                $file = $vpopmailDir . "domains/"  . $domain . "/" . $entry;
		if (preg_match("/^\.qmail-(.*)-(accept-default|owner|reject-default|return-default)/", $entry, $matches)) {
                  $listdir = $vpopmailDir . "domains/"  . $domain . "/" . $matches[1];
                  $manuallyCheck[$listdir] = 'mailing list';
                }elseif (preg_match("/^\.qmail-(.*)-default/", $entry, $matches)) {
                  // echo "DEFAULT: $file \n";
		}elseif (preg_match("/^\.qmail-(.*)/", $entry, $matches)) {
		  if(!in_array($file, $dotqmailfiles)) {
		    $user = $matches[1];
                    $dotqmailfiles[] = $file;
                    $handle = @fopen($file, "r");
                    if ($handle) {
                      while (($buffer = fgets($handle, 4096)) !== false) {
                        if(preg_match("/^&(.*)/", $buffer, $matches)) {
                            $source = sprintf("%s@%s", strtr($user, ':', '.'), $domain);
                            $destination = $matches[1];
                            addMailForward($client_id, $source, $destination, true);
                        }elseif(preg_match("/^\|\/usr\/local\/bin\/ezmlm\/(.*)/", $buffer, $matches)) {
                          if(!preg_match("/^(.*)-default/", $user, $matches)) {
                            $listdir = $vpopmailDir . "domains/"  . $domain . "/" . $user;
                            $manuallyCheck[$listdir] = 'mailing list';
                          }
                        }else{
                            echo "  $user in file $file";
                            printf("\n  unknown (%s): %s\n", __LINE__, $buffer);
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
        $migrateddomains[] = $domain;
      }else{
        // Check if the domain is an alias domain
        $tFound = false;
        foreach ($domains_homer_all as $tDomain=>$tList) {
          if(is_array($tList) and in_array($domain, $tList)) {
            $tFound = true;
          }
        }
        
        if($tFound == false) {
          printf("Domain not found  %-30s \n", strtoupper($domain));
          $manuallyCheck[$domain] = 'domain not found';
        }
      }
      
      if(isset($stopDomainCount) and $i == $stopDomainCount) {
        printf("Migration aborted after %s domains", $i);
        break;
      }
    }

    echo "\nMigration finished:\n\n";

    echo "Counts:\n";
    foreach($count as $key=>$value) {
      printf(" %-12s: %s\n", $key, $value);         
    }
    
    echo "\ncheck manually:\n";
    foreach($manuallyCheck as $key=>$value) {
      printf("%s:%s\n", $value, $key);
    }
    
    echo "\nmigrated domains:\n";
    foreach($migrateddomains as $domain) {
      printf(" %s\n", $domain);
    }
    
    echo "DOMAINS NOT FOUND on otto\n";
    print_r($domains_homer);

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