<?php
require_once("config/oauthconfig.php");
try {
    $OAuth = new OAuth($consumerKey, $consumerSecret);
    if ($self_signed) $OAuth->disableSSLChecks();
    $OAuth->setToken($_GET['oauth_token'], $_SESSION['oauth_token_secret']);
    $OAuth->enableDebug();
    $tokenInfo = $OAuth->getAccessToken($accessURL);

    if (!isset($tokenInfo['oauth_token']) || !isset($tokenInfo['oauth_token_secret'])) {

    }

    $OAuth->setToken($tokenInfo['oauth_token'], $tokenInfo['oauth_token_secret']);
    $result = $OAuth->fetch($apiURL . "user", $body, "GET", array(
            'X-Api-Version' => '1',
            'Accept' => 'application/json',
            //'Accept' => 'application/vnd.copy-v1+json',
        ));
    $response_body = $OAuth->getLastResponse();
    $ouser = json_decode($response_body, true);
    if ($ouser) {
        $copyUserId = $ouser['user_id'];
        $email = $ouser['email'];
        $name = $ouser['first_name'] . " " . $ouser['last_name'];
        $accessToken = $tokenInfo['oauth_token'];
        $tokenSecret = $tokenInfo['oauth_token_secret'];
        $um = new webtocopy\entities\user($conn);
        $newuser = $um->saveUser($name, $email, $copyUserId, $accessToken, $tokenSecret);
        $_SESSION['user_id'] = $newuser['id'];
        $_SESSION['user_name'] = $name;
        header("Location: /dashboard");
    }
} catch (Exception $E) {

}