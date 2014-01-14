<?php
global $conn, $twig;
$pageParams = explode("/",$_REQUEST['data']);
$page = $pageParams[0];
if($page=="about-us"){
    echo $twig->render("aboutus.html.twig");
}else if($page=="tc"){
    echo $twig->render("tos.html.twig");
}else if($page=="api"){
    echo $twig->render("api.html.twig");
}else if($page=="contact"){
    $v1 = mt_rand(1,20);
    $v2 = mt_rand(10,30);
    $res = ($v1+$v2) * 34;
    $showModal=0;

    if($_POST['email']){
        $showModal=1;

        $email ="{$_POST['name']}<{$_POST['email']}>";

        $subject = "Web2Copy Contact Mail" ;
        $body = $_POST['message'];

        sendMail("hasin@leevio.com",$subject,$body,$email);
    }

    echo $twig->render("contact.html.twig",array("v1"=>$v1, "v2"=>$v2, "chk"=>$res,"sm"=>$showModal));
}

