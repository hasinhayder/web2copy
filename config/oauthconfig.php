<?php
error_reporting(0);

$server = "api.copy.com";
$secure = false;
$self_signed = false;
$www = "www.copy.com";

$consumerKey = "USE-YOUR-OWN-KEY";
$consumerSecret = "USE-YOUR-OWN-SECRET";


$requestURL = "http://api.copy.com/oauth/request";
$accessURL = "http://api.copy.com/oauth/access";
$apiURL = "http://api.copy.com/rest/";
$authorizeURL = "http://www.copy.com/applications/authorize";

// This URL points to your local third party app
$callbackURL = 'http://' . $_SERVER['SERVER_NAME'] . '/auth';



