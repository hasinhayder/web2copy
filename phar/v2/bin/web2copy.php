#!/usr/bin/env php
<?php
error_reporting(0);
global $data;
require __DIR__ . '/../data/data.php';
require __DIR__ . '/../libs/copy.php';
require __DIR__ . '/../libs/OAuthSimple.php';
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

    $arguments = $argv;
    $path = $arguments[1];
    $fileSize = filesize($path);

    $consumerKey = $decData['ck'];
    $consumerSecret = $decData['cs'];
    $accessToken = $decData['at'];
    $tokenSecret = $decData['ts'];

    if (file_exists($path)) {

        /* Track the File Transfer */
        $ch = curl_init("http://dev.web2copy.com/api/signedurl/");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array("signature" => $decData['signature'],"bytes"=>$fileSize));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $signedURL = curl_exec($ch);

        $pathInfo = pathinfo($path);
        $fileName = $pathInfo['basename'];
        $copy = new \Barracuda\Copy\API($consumerKey, $consumerSecret, $accessToken, $tokenSecret);
        $fh = fopen($path, 'rb');

        $parts = array();
        $i = 0;
        echo "\nUploading...\n";
        while ($data = fread($fh, 1024 * 1024)) {
            $i++;
            echo $i." MB.. ";
            $part = $copy->sendData($data);
            array_push($parts, $part);
        }

        fclose($fh);
        $copy->createFile("/web2copy/{$fileName}", $parts);
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
