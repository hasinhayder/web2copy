<?php
require_once("iron/IronCore.class.php");
require_once("iron/IronCache.class.php");
require_once("iron/IronMQ.class.php");
require_once("common.php");
require_once("OAuthSimple.php");

$start = microtime(true);
$consumerKey = "YOUR-OWN-KEY";
$consumerSecret = "YOUR-OWN-SECRET";
$key = "YOUR-OWN-SECRET-KEY";

$signatures = array(
    'consumer_key' => $consumerKey,
    'shared_secret' => $consumerSecret);

$payload = getPayload();
$taskId = getTaskId();
$encryptedToken = $payload->data;
$fileURL = $payload->url;
$copyPath = $payload->path;
$w2ctoken = $payload->token;
$decryptedToken = mc_decrypt($encryptedToken, $key);
$at = $decryptedToken['at'];
$ts = $decryptedToken['ts'];

$signatures['oauth_token'] = $at;
$signatures['oauth_secret'] = $ts;

$apiURL = "http://api.copy.com/rest/";


$fileInfo = pathinfo($fileURL);
$localFileName = $w2ctoken . "-" . time() . "-" . $fileInfo['filename'] . "." . $fileInfo['extension'];
$localAbsFileName = __DIR__ . "/" . $localFileName;

$headers = get_headers($fileURL, 1);
$size = $headers['Content-Length'];
$status = $headers[0];

$mq = new IronMQ(array(
    'token' => 'YOUR-OWN-TOKEN',
    'project_id' => 'YOUR-OWN-PROJECT-ID'
));

try {
    $oauthObject = new OAuthSimple();
    $oauthObject->setAction("POST");
    $post = array('file_contents'=>'@'.$localAbsFileName);

    $oauthsighning = $oauthObject->sign(array(
        'path' => $apiURL."files".$copyPath,
        'signatures' => $signatures
    ));

    file_put_contents("./{$localFileName}", file_get_contents($fileURL));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST,1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_HTTPHEADER,array(
        'X-Api-Version: 1',
        'Accept: application/json',
    ));
    curl_setopt($ch, CURLOPT_URL, $oauthsighning['signed_url']);
    $responseBody =curl_exec($ch);
    $end = microtime(true);
    @unlink("./".$localFileName);
    $totalTime = ($end - $start);


    $mq->postMessage("YOUR-JOB-QUEUE-NAME", base64_encode(serialize(array(
        "copydata" => json_decode($responseBody, 1),
        "start" => $start,
        "end" => $end,
        "token" => $w2ctoken,
        "url" => $fileURL,
        "size" => $size,
        "path" => $copyPath,
        "taskid" => $taskId,
    ))), array(
        "timeout" => 120,
        "expires_in" => 5 * 3600
    ));

} catch (Exception $e) {
    print_r($e);
}

?>