<?php
namespace webtocopy\entities;
class user
{
    private $connection;

    function __construct(\Doctrine\DBAL\Connection $connection)
    {
        $this->connection = $connection;
    }

    function saveUser($username, $email, $copyUserId, $accessToken, $tokenSecret)
    {
        $user = $this->getUserByEmail($email);
        if (!$user) {
            //user doesn't exists, create
            $signature = generateRandomString();
            while($this->getUserBySignature($signature)){
                $signature = generateRandomString();
            }
            $this->connection->insert("users", array(
                "name" => $username,
                "email" => $email,
                "access_token" => $accessToken,
                "token_secret" => $tokenSecret,
                "copy_user_id" => $copyUserId,
                "created" => time(),
                "active" => 1,
                "signature"=>$signature
            ));
            $user = $this->getUserByEmail($email);

            //create a default token for this user which can be used 100000 times
            $tm = new token($this->connection);
            $tm->saveToken("/web2copy/",$user['id'],"100000");
            return $user;
        }else{
            //user exists, so update the tokens
            $this->connection->update("users",array(
                "access_token"=>$accessToken,
                "token_secret"=>$tokenSecret
            ),array("id"=>$user['id']));
        }
        return $user;
    }

    function getUserById($id)
    {
        $user = $this->connection->fetchAssoc("select * from users where id=?", array($id));
        return $user;
    }

    function getUserByEmail($email)
    {
        $user = $this->connection->fetchAssoc("select * from users where email=?", array($email));
        return $user;
    }

    function getUserByCopyId($copyUserId)
    {
        $user = $this->connection->fetchAssoc("select * from users where copy_user_id=?", array($copyUserId));
        return $user;
    }

    function getUserBySignature($signature){
        $user = $this->connection->fetchAssoc("select * from users where signature=?", array($signature));
        return $user;
    }
}