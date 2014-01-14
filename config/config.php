<?php
global $conn, $key;
$dbname = "NOT-YOUR-WIFES-NAME";
$dbuser = "NOT-YOUR-NAME-EITHER";
$dbhost = "127.0.0.1";
$dbpwd = "NOT-YOUR-CREDIT-CARD-NUMBER";


$ironWorkerToken1 = "USE-YOUR-OWN-TOKEN";
$ironWorkerProjectID1 = "USE-YOUR-OWN-ID";

$mailgunAPI = "USE-YOUR-OWN-API-KEY";
$mailgunDomain = "USE-YOUR-OWN-DOMAIN";
$fileSizeLimit = 52428800;


$key = "USE-YOUR-OWN-KEY";
$pharSecret = "YOUR-SECRET-PERHAPS";

$config = new \Doctrine\DBAL\Configuration();
$connectionParams = array(
    'dbname' => $dbname,
    'user' => $dbuser,
    'password' => $dbpwd,
    'host' => $dbhost,
    'driver' => 'pdo_mysql',
);

$conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);
