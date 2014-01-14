<?php
session_start();
require_once("entities/user.php");
require_once("entities/token.php");
require_once("entities/jobs.php");
require_once("entities/pharstats.php");
require_once("vendor/autoload.php");
require_once("config/config.php");
require_once("config/oauthconfig.php");
require_once("utility/common.php");
require_once("vendor/oauth/OAuthSimple.php");
global $conn, $twig;


$loader = new \Twig_Loader_Filesystem(__DIR__ . "/views",array("cache" => "/tmp"));
$twig = new \Twig_Environment($loader);
$twig->addGlobal("session", $_SESSION);
//$twig = new \Twig_Environment($loader, array("cache" => "/tmp"));

/**
 * Routes
 */
if ($_GET['connect']) {
    require_once("controllers/connect.php");
} else if ($_GET['api']) {
    require_once("controllers/api.php");
} else if ($_GET['ajax']) {
    require_once("controllers/ajax.php");
} else if ($_GET['mailer']) {
    require_once("controllers/mailer.php");
} else if ($_GET['dashboard']) {
    require_once("controllers/dashboard.php");
} else if ($_GET['auth']) {
    require_once("controllers/authorize.php");
} else if ($_GET['page']){
    require_once("controllers/pages.php");
} else {
    echo $twig->render("index.html.twig");
}
?>