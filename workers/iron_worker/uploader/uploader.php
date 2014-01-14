<?php
require_once("iron/IronCore.class.php");
require_once("iron/IronCache.class.php");
require_once("iron/IronMQ.class.php");
require_once("common.php");

$start = microtime(true);
$consumerKey = "USE-YOUR-OWN-KEY";
$consumerSecret = "USE-YOUR-OWN-SECRET";
$key = "USE-YOUR-OWN-SECRET-KEY";
$payload = getPayload();
$taskId = getTaskId();
$encryptedToken = $payload->data;
$fileURL = $payload->url;
$copyPath = $payload->path;
$w2ctoken = $payload->token;
$copyFileName = $payload->filename;
$decryptedToken = mc_decrypt($encryptedToken, $key);
$at = $decryptedToken['at'];
$ts = $decryptedToken['ts'];

$apiURL = "http://api.copy.com/rest/";


$fileInfo = pathinfo($fileURL);
$localFileName = $w2ctoken . "-" . time() . "-" . $fileInfo['filename'] . "." . $fileInfo['extension'];

$headers = get_headers($fileURL, 1);
$size = $headers['Content-Length'];
$status = $headers[0];
//if (strpos($status, "200") !== false) {

$mq = new IronMQ(array(
    'token' => 'USE-YOUR-OWN-TOKEN',
    'project_id' => 'USE-YOUR-OWN-ID'
));

try {
    $OAuth = new OAuth($consumerKey, $consumerSecret);
    $OAuth->disableSSLChecks();
    $OAuth->setToken($at, $ts);
    $OAuth->enableDebug();

    $endpoint = 'files' . $copyPath;

    file_put_contents("./{$localFileName}", file_get_contents($fileURL));

    if ($copyFileName)
        $filename = $copyFileName;
    else
        $filename = $localFileName;
    $method = 'POST';
    $overwrite = '?overwrite=true';
    $boundary = "FormBoundary" . rand(1000000, 9999999);

    $additionalErrors = null;

    $bodyPrefix = "------$boundary\nContent-Disposition: form-data; name=\"file\"; filename=\"$filename\"\nContent-Type: application/octet-stream\n\n";
    $bodyPostfix = "\n------$boundary--";

    $encodedFile = $bodyPrefix . file_get_contents("./{$localFileName}") . $bodyPostfix;
    try {
        $result = $OAuth->fetch($apiURL . $endpoint . $overwrite, $encodedFile, $method, array(
                'X-Api-Version' => '1',
                'Accept' => 'application/json',
                'Content-Type' => "multipart/form-data; boundary=----$boundary"
                //'Accept' => 'application/vnd.copy-v1+json',
            ));
    } catch (OAuthException $E) {
        $additionalErrors = $E->getMessage();
    }

    $end = microtime(true);
    unset($encodedFile);
    @unlink("./" . $localFileName);
    $totalTime = ($end - $start);
    $responseBody = $OAuth->getLastResponse();

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
//} else {
//    //send to error queue
//    //end
//}
?>