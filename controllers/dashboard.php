<?php
global $conn, $twig;
$userId = $_SESSION['user_id'];
if (!$userId){
    header("Location: /");
    die;
}
$tm = new \webtocopy\entities\token($conn);

if($_POST && $userId){
    $path = $_POST['path'];
    $usable = $_POST['usable'];
    $token = $tm->saveToken($path,$userId,$usable);
}

$userCurrentTokens = $tm->getUnusedTokensByUserId($userId);
$userExpiredTokens = $tm->getUsedTokensByUserId($userId);

$defaultToken = $tm->getDefaultTokenByUserId($userId);

echo $twig->render("dashboard.html.twig", array(
    "token"=>$token,
    "validtokens"=>$userCurrentTokens,
    "expiredtokens"=>$userExpiredTokens,
    "dt"=>$defaultToken
));
