<?php
global $conn, $key, $fileSizeLimit, $ironWorkerToken1, $ironWorkerProjectID1;

$apiParams = explode("/", $_REQUEST['data']);
$action = $apiParams[0];

if ($action == "jobstatus") {
    //webhook push queue call from iron mq. so process the file transfer details
    processWebhookCall();
} else if ($action == "token") {
    //register a job to transfer a file from web to copy storage
    createJob();
} else if ($action == "enctoken") {
    //will be done in next version. this will be used to store encrypted tokens from iron worker instead of storing user's oauth tokens
    processEncryptedToken();
} else if ($action == "signedurl") {
    //generate signed url for file submission for the command line tool
    generateSignedUrl();
} else if ($action == "phar") {
    //create personalized phar
    generatePhar();
} else if ($action == "download") {
    //download personalized phar
    downloadPhar();
}else if ($action == "clean") {
    //download personalized phar
    deletePhar();
}

/**
 * This function accepts the webhook call from IronMQ once a job is complete, and update the statistics
 */
function processWebHookCall()
{
    global $conn, $key, $fileSizeLimit, $ironWorkerToken1, $ironWorkerProjectID1;
    //sendMail("hasin@leevio.com","RAW JOB DATA",$HTTP_RAW_POST_DATA,"no-reply@web2copy.com");
    $data = unserialize(base64_decode($HTTP_RAW_POST_DATA));
    $copyURL = $data['copydata']['objects'][0]['url'];
    $size = $data['size'];
    $taskId = $data['taskid'];
    $url = $data['url'];
    $start = $data['start'];
    $end = $data['end'];
    $token = $data['token'];

    $jm = new \webtocopy\entities\jobs($conn);

    $jobDetails = $jm->getJobByTaskId($taskId);
    $jobId = $jobDetails['id'];

    $job = $jm->updateJob($jobId, array(
        "transfer_start" => $start,
        "transfer_end" => $end,
        "transfer_time" => ($end - $start),
        "copy_file_url" => $copyURL,
        "task_id" => $taskId,
        "transfer_status" => 1
    ));
    //$token = "4XZAR3NCM5";
    $size = number_format(($size / (1024 * 1024)), 2, '.', '');
    $user = getUserByToken($token);
    $name = $user['name'];
    $email = $user['email'];
    $subject = "Web2Copy Notification: Someone Just Transferred a File to Your Copy.com Storage";
    $body = "Hi {$name},\nSomeone just transferred a file from {$url} to your copy.com storage. File size was {$size} MB and the transfer was successful. You can download this file by going to {$copyURL}.\n
\nThanks\nWeb2Copy Team";
    sendMail($email, $subject, $body, "no-reply@web2copy.com");

    echo "ok - done";
}

/**
 * Registers a job for web to copy.com storage transferring. It sends the payload to IronWorker to complete to task.
 */
function createJob()
{
    global $conn, $key, $fileSizeLimit, $ironWorkerToken1, $ironWorkerProjectID1;
    $token = $_POST['token'];
    $url = $_POST['url'];
    $filename = $_POST['filename'];

    $headers = get_headers($url, 1);
    $size = $headers['Content-Length'];
    if (is_array($size)) $size = $size[1];
    if ($size > $fileSizeLimit) {
        //size is bigger
        echo json_encode(array("error" => 1, "message" => "Sorry, File size is bigger than 50 Megabytes. At this moment we only transfer files which are smaller than 50 MB."));
        die;
    } else {
        //size is ok, now register the job
        $tm = new \webtocopy\entities\token($conn);
        $tokenDetails = $tm->getDetailsByToken($token);
        if ($tokenDetails['usable'] > $tokenDetails['used']) {
            //this token is still usable
            //get user details
            $um = new \webtocopy\entities\user($conn);
            $user = $um->getUserById($tokenDetails['user_id']);
            $userTokens = array("at" => $user['access_token'], "ts" => $user['token_secret']);
            $encUserTokens = mc_encrypt($userTokens, $key); //encrypted token

            //register the job
            $jm = new \webtocopy\entities\jobs($conn);
            $job = $jm->addNewJob($tokenDetails['id'], $url, $size, $_SERVER['REMOTE_ADDR']);

            //now send the job to Iron Worker
            $worker = new \IronWorker(array(
                'token' => $ironWorkerToken1,
                'project_id' => $ironWorkerProjectID1
            ));
            $worker->ssl_verifypeer = false;
            $payLoad = array(
                "url" => $url,
                "path" => $tokenDetails['token_path'],
                "token" => $token,
                "data" => $encUserTokens,
                "filename" => $filename
            );

            try {
                $task = $worker->postTask("uploader", $payLoad);

                if ($task) {
                    //job is successfully sent to IronWorker, so update the job with task id
                    $jm->updateJob($job['id'], array("task_id" => $task));
                }
            } catch (Exception $e) {
                print_r($e);
            }

            if ($task) {
                //job is successfully sent to IronWorker, so update the token used counter
                $tm->updateTokenUsedCounter($tokenDetails['id'], 1);
                $tm->updateTokenBandwidth($tokenDetails['id'], $size);
            }
            echo json_encode(array("error" => 0, "message" => "Successful"));

        } else {
            echo json_encode(array("error" => 1, "message" => "This token has expired."));
        }

    }
}

function processEncryptedToken()
{

}

/**
 * This is used by the command line phar tool to discover the signed URL, where it can send the payload
 */
function generateSignedUrl()
{
    global $conn, $key, $consumerKey, $consumerSecret, $ironWorkerProjectID1;
    $signature = $_POST['signature'];
    $bytes = $_POST['bytes'];
    if ($signature) {

        $um = new \webtocopy\entities\user($conn);
        $user = $um->getUserBySignature($signature);


        $signatures = array('consumer_key' => $consumerKey,
            'shared_secret' => $consumerSecret,
            'oauth_token' => $user['access_token'],
            'oauth_secret' => $user['token_secret']
        );


        $oauthObject = new OAuthSimple();
        $oauthObject->setAction("POST");
        $result = $oauthObject->sign(array(
            'path' => "http://api.copy.com/rest/files/web2copy/",
            'signatures' => $signatures));

        //save the statistics
        $pm = new \webtocopy\entities\pharstats($conn);
        $ip = $_SERVER['REMOTE_ADDR'];
        $pm->saveStatistics($user['id'], $ip, $bytes);

        header('Content-Type:text/plain;');
        echo $result['signed_url'];
    } else {
        echo "darn!";
    }
}

/**
 * Create a personalized phar with user's password
 */
function generatePhar()
{
    global $conn, $pharSecret;
    $secret = $pharSecret;
    $password = $_POST['password'];
    if ($password) {
        $userId = $_SESSION['user_id'];
        $fileName = "phar" . md5($userId . $secret) . ".phar";
        $absFileName = str_replace("controllers", "", __DIR__) . "pharchive/generated/{$fileName}";
        if(file_exists($absFileName)) unlink($absFileName);
        copy(str_replace("controllers", "", __DIR__) . "pharchive/original/web2copy.phar", $absFileName);

        $um = new \webtocopy\entities\user($conn);
        $user = $um->getUserById($userId);
        $signature = $user['signature'];
        $data = array("signature" => $signature);
        $encryptedData = mc_encrypt($data, $password);
        $phpData = "<?php\n\$data='{$encryptedData}';";
        file_put_contents("phar://{$absFileName}/data/data.php", $phpData);
    }
    echo json_encode(array("error"=>0,"message"=>"phar generated successfully"));

}

/**
 * Deletes the phar files. This function is invoked from the dashboard after someone downloads his personalized phar file
 */
function deletePhar(){
    global $pharSecret;
    $secret = $pharSecret;
    $userId = $_SESSION['user_id'];
    $fileName = "phar" . md5($userId . $secret) . ".phar";
    $absFileName = str_replace("controllers", "", __DIR__) . "pharchive/generated/{$fileName}";
    unlink($absFileName);
    echo json_encode(array("error"=>0,"message"=>"phar cleaned successfully"));
}

/**
 * Sends the download instructions to the browser, to force download the phar file
 */
function downloadPhar()
{
    global $pharSecret;
    $secret = $pharSecret;
    $userId = $_SESSION['user_id'];
    $fileName = "phar" . md5($userId . $secret) . ".phar";
    $absFileName = str_replace("controllers", "", __DIR__) . "pharchive/generated/{$fileName}";
    if ($userId && file_exists($absFileName)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . "web2copy.phar");
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($absFileName));
        readfile($absFileName);
        unlink($absFileName);
    }else if(!$userId){
        echo "Unauthorized Access. Please Login First.";
    }
}

/**
 * This is just a quick wrapper function to find a user by token
 * @param $token
 * @return array
 */
function getUserByToken($token)
{
    global $conn;
    $tm = new webtocopy\entities\token($conn);
    $tokenDetails = $tm->getDetailsByToken($token);
    $userId = $tokenDetails['user_id'];

    $um = new webtocopy\entities\user($conn);
    $user = $um->getUserById($userId);
    return $user;
}

