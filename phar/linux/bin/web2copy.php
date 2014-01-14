#!/usr/bin/env php
<?php
error_reporting(0);
global $data;
require __DIR__ . '/../data/data.php';
$head = <<<ETD


__        __   _       ____     ____
\ \      / /__| |__   |___ \   / ___|___  _ __  _   _
 \ \ /\ / / _ \ '_ \    __) | | |   / _ \| '_ \| | | |
  \ V  V /  __/ |_) |  / __/  | |__| (_) | |_) | |_| |
   \_/\_/ \___|_.__/  |_____|  \____\___/| .__/ \__, |
                                         |_|    |___/



ETD;
echo $head;
echo "Password: ";
system('stty -echo');
$pwd = trim(fgets(STDIN));
system('stty echo');

$decData = w2c_decrypt($data, $pwd);
$start = microtime(true);
if (is_array($decData)) {
    //successful decryption - get the signed url
    $arguments = $argv;
    $path = $arguments[1];
    $fileSize = filesize($path);

    $ch = curl_init("http://dev.web2copy.com/api/signedurl/");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array("signature" => $decData['signature'],"bytes"=>$fileSize));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $signedURL = curl_exec($ch);
    if(!$signedURL){
        echo "\nError: There was a problem communicating with Web2Copy server. So please try again later.\n";
        die;
    }
    curl_close($ch);


    if (file_exists($path)) {
        $pathInfo =pathinfo($path);
        $fileName = $pathInfo['basename'];
        echo "\nTransferring...\n";
//        echo "\n\n\n".$signedURL."\n\n\n";
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $post = array('file_contents' => '@' . $path);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'X-Api-Version: 1',
                'Accept: application/json',
            ));
            curl_setopt($ch, CURLOPT_URL, $signedURL);
            $output = curl_exec($ch);
            $formattedData = json_decode($output, 1);
            $end = microtime(true);
            $total = $end-$start;
            if (!$formattedData['error'])
                echo "\nYour file transfer was successful and it took {$total} seconds. You can download this file from " . $formattedData['objects'][0]['url'] . "\n";
            else
                echo "\nError: ".$formattedData['message']."\n";
            curl_close($ch);
        } catch (Exception $e) {
            //print_r($e);
        }

        //echo $output;
    } else {
        echo "\nError: File Doesn't Exist\n";
    }

} else {
    echo "\nError: Wrong Password\n";
}


function w2c_decrypt($decrypt, $key)
{
    $decrypt = explode('|', $decrypt);
    $decoded = base64_decode($decrypt[0]);
    $iv = base64_decode($decrypt[1]);
    $key = pack('H*', $key);
    $decrypted = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $decoded, MCRYPT_MODE_CBC, $iv));
    $mac = substr($decrypted, -64);
    $decrypted = substr($decrypted, 0, -64);
    $calcmac = hash_hmac('sha256', $decrypted, substr(bin2hex($key), -32));
    if ($calcmac !== $mac) {
        return false;
    }
    $decrypted = unserialize($decrypted);
    return $decrypted;
}

