<?php
function getDomainsFromHomer() {
  global $mysoap, $session_id, $domains_homer;

  $handle = fopen("/var/qmail/users/assign", "r");
  if ($handle) {
    while (($line = fgets($handle)) !== false) {
     list($alias, $main) = explode(':', $line);
     $alias = substr($alias, 1, -1);
     if($main == '') continue;
     if($main == $alias) {
       if(!array_key_exists($main, $domains_homer)) {
         $domains_homer[$main] = '';
       }
     }else{
       $domains_homer[$main][] = $alias;
     }
#     printf("%s  %s    Rest: %s", $alias, $main, $line);
#     exit;
    }
  } else {
    echo "could not open file";
    // error opening the file.
  }
  ksort($domains_homer);

  $dir = "/home/vpopmail/domains/";
  if ($dirhandle = opendir($dir)) {
     while (false !== ($entry = readdir($dirhandle))) {
       if(is_dir($dir . $entry) and !array_key_exists($entry, $domains_homer) and $entry != '.' and $entry !='..') {
//         echo "Directory for non-existent domain found: " . $entry . "\n";
//         die();
       }
    }
    closedir($dirhandle);
  }
}


function getDomainsFromOtto() {
  global $mysoap, $session_id, $domains_otto, $domains_otto_byuser;

  //* Set the function parameters.
  $params = array ('domain_id' => '%',
                  );

  $domain_list = $mysoap->domains_domain_get($session_id, $params);
  foreach($domain_list as $key=>$value) {
    $domains_otto[$value['domain']] = $value['sys_groupid'];
    $domains_otto_byuser[$value['sys_groupid']][] = $value['domain'];
  }
  ksort($domains_otto);
}

function addMailDomain($client_id, $domain, $delete = false, $ident = false) {
  global $server_id, $mysoap, $session_id, $count;
  
  $id = false;
  $params = array ('domain' => $domain,
                   'active' => 'Y',
                   'server_id' => $server_id,
                  );

  if($ident) {
    echo "  ";                  
  }else{
    echo "\n";
  }
  printf("Add domain %-30s: ", strtoupper($domain));

  $result = $mysoap->mail_domain_get_by_domain($session_id, $domain);

  // Force delete?
  if(count($result) == 1 and $delete) {
    echo " Deleted. ";
    $mysoap->mail_domain_delete($session_id, $result[0]['domain_id']);
    $result = $mysoap->mail_domain_get_by_domain($session_id, $domain);
  }

  if(count($result) == 0) {
    $id = $mysoap->mail_domain_add($session_id, $client_id, $params);
    echo " Added.\n";
    $count['domain']++;
  }else{
    printf(" exists already\n");
    // print_r($result);
  }

  return $id;
}

function addMailForward($client_id, $source, $destination, $delete = false) {
  global $server_id, $mysoap, $session_id, $count;
  
  $params_get = array('source' => $source,
                      'destination' => $destination,
                      'type' => 'forward',
                    );

  $params_add = array('server_id' => $server_id,
                      'source' => $source,
                      'destination' => $destination,
                      'type' => 'forward',
                      'active' => 'y',
                     );

  printf(" add forward %30s => %-27s:", $source, $destination);

  $result = $mysoap->mail_alias_get($session_id, $params_get);

  // Force delete?
  if(count($result) == 1 and $delete) {
    echo " Deleted. ";
    $mysoap->mail_alias_delete($session_id, $result[0]['forwarding_id']);
    $result = $mysoap->mail_alias_get($session_id, $params_get);
  }

  if(count($result) == 0) {
    $id = $mysoap->mail_alias_add($session_id, $client_id, $params_add);
    echo " Added.\n";
    $count['forward']++;
  }else{
    printf(" exists already\n");
  }

  return $id;
}

function addMailCatchall($client_id, $domain, $destination, $delete = false) {
  global $server_id, $mysoap, $session_id, $count;
  
  $params_get = array('source' => "@" . $domain,
                      'destination' => $destination,
                      'type' => 'catchall',
                    );

  $params_add = array('server_id' => $server_id,
                      'source' => "@" . $domain,
                      'destination' => $destination,
                      'type' => 'catchall',
                      'active' => 'y',
                     );

  printf(" add catchall %29s => %-27s:", "@" . $domain, $destination);

  $result = $mysoap->mail_alias_get($session_id, $params_get);

  // Force delete?
  if(count($result) == 1 and $delete) {
    echo " Deleted. ";
    $mysoap->mail_catchall_delete($session_id, $result[0]['forwarding_id']);
    $result = $mysoap->mail_catchall_get($session_id, $params_get);
  }

  if(count($result) == 0) {
    $id = $mysoap->mail_catchall_add($session_id, $client_id, $params_add);
    echo " Added.\n";
    $count['catchall']++;
  }else{
    printf(" exists already\n");
  }

  return $id;
}



function addMailDomainAlias($client_id, $source, $destination, $delete = false) {
  global $server_id, $mysoap, $session_id, $count;
  
  $params_get = array('source' => "@" . $source,
                      'destination' => "@" . $destination,
                      'type' => 'aliasdomain',
                    );

  $params_add = array('server_id' => $server_id,
                      'source' => "@" . $source,
                      'destination' => "@" . $destination,
                      'type' => 'aliasdomain',
                      'active' => 'y',
                     );

  printf("  Add alias  %s => %s : ", strtoupper($source), strtoupper($destination));

  $result = $mysoap->mail_aliasdomain_get($session_id, $params_get);

  // Force delete?
  if(count($result) == 1 and $delete) {
    echo " Deleted. ";
//    print_r($result); exit;
    $mysoap->mail_aliasdomain_delete($session_id, $result[0]['forwarding_id']);
    $result = $mysoap->mail_aliasdomain_get($session_id, $params_get);
  }

  if(count($result) == 0) {
    $id = $mysoap->mail_aliasdomain_add($session_id, $client_id, $params_add);
    echo " Added.\n";
    $count['aliasdomain']++;
  }else{
    printf(" exists already\n");
    // print_r($result);
  }

  return $id;
}

function addMailUser($client_id, $user, $domain, $pass, $name, $delete = false) {
  global $server_id, $mysoap, $session_id, $count;

  $login = $user . "@" . $domain;

  $params_get = array('server_id' => $server_id,
                      'login' => $login,
                    );

  $params_add = array('server_id' => $server_id,
                      'login' => $login,
                      'email' => $login,
                      'password' => $pass,
                      'name' => $name,
                      'postfix' => 'y',
                      'quota' => 0,
                      'uid' => 5000,
                      'gid' => 5000,
                      'homedir' => '/var/vmail',
                      'maildir' => '/var/vmail/' . $domain . '/' . $user,
                     );


  printf(" add user    %30s %-30s:", $login, $name);

  if($user == 'postmaster') {
	echo " Skipped\n";
  }else{
    $result = $mysoap->mail_user_get($session_id, $params_get);

    // Force delete?
    if(count($result) == 1 and $delete) {
      echo " Deleted. ";
      $mysoap->mail_user_delete($session_id, $result[0]['mailuser_id']);
      $result = $mysoap->mail_user_get($session_id, $params_get);
    }

    if(count($result) == 0) {
      $id = $mysoap->mail_user_add($session_id, $client_id, $params_add);
      echo " Added.\n";
      $count['user']++;
    }else{
      printf("  exists already\n");
      // print_r($result);
    }
  }
}

function getOldUsersFromDomain($domain) {
  global $con;
  $result = mysqli_query($con, sprintf("SELECT * FROM vpopmail WHERE pw_domain = '%s'", $domain));

  $users = array();
  while($row = mysqli_fetch_assoc($result)) {
	$users[] = $row;
  }
  return $users;
}

?>