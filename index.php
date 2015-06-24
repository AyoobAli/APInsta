<?php

/**
 * 
 * APInsta v0.1
 * By AyoobAli.com
 * License: The MIT License (MIT)
 * Copyright (c) 2015 Ayoob Ali
 * 
 * This class was made as a workaround for Instagram Notification API.
 * IMPORTANT: This was not made to be a secure API as it uses the plain-text username/password for authentication, and it will save the user session ID in the directory 'savedsessions'.
 * 
 * Use:-
 * include('APInsta.class.php');
 * $insta = new \APInsta\Instagram();
 * $insta->login("Username", "Password");
 * $json = $insta->getNotifications();
 * 
 */

include('APInsta.class.php');
$insta = new \APInsta\Instagram();
$insta->login("Username", "Password", true);
$json = $insta->getNotifications();
var_dump($json);
// To logout and delete the saved session ID use:-
// $insta->logout();

?>