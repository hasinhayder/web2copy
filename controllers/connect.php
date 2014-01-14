<?php
global $consumerKey, $consumerSecret, $requestURL, $callbackURL;
$tokenInfo = null;
try {
    $OAuth = new OAuth($consumerKey, $consumerSecret);
    //print_r($OAuth);
    $OAuth->enableDebug();
    if ($self_signed) $OAuth->disableSSLChecks();
    $tokenInfo = $OAuth->getRequestToken($requestURL, $callbackURL);
    print_r($tokenInfo);
} catch (Exception $E) {

}
$_SESSION['oauth_token_secret'] = $tokenInfo['oauth_token_secret'];
$_SESSION['oauth_token_secret'] = $tokenInfo['oauth_token_secret'];

$location = $authorizeURL . '?oauth_token=' . $tokenInfo['oauth_token'];
header('Location: ' . $location);