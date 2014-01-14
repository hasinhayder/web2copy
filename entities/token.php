<?php
namespace webtocopy\entities;

class token
{
    private $connection;

    function __construct(\Doctrine\DBAL\Connection $connection)
    {
        $this->connection = $connection;
    }

    function saveToken($path, $userId, $usable)
    {
        $token = strtoupper(generateRandomString());

        while ($this->getDetailsByToken($token)) {
            $token = generateRandomString();
        }

        if(substr($path,0,1)!="/") $path = "/".$path;

        $this->connection->insert("tokens", array(
            "token" => $token,
            "user_id" => $userId,
            "status" => 1,
            "usable" => $usable,
            "used" => 0,
            "created" => time(),
            "token_path" => $path
        ));

        return $this->getDetailsByToken($token);
    }

    function getTokenById($id)
    {
        $token = $this->connection->fetchAssoc("select * from tokens where id=?", array($id));
        return $token;
    }

    function getDetailsByToken($token)
    {
        $token = $this->connection->fetchAssoc("select * from tokens where token=?", array($token));
        return $token;
    }

    function getUnusedTokensByUserId($userId)
    {
        $tokens = $this->connection->fetchAll("select * from tokens where user_id=? and used<usable and status=1", array($userId));
        return $tokens;
    }

    function getDefaultTokenByUserId($userId)
    {
        try {
            $token = $this->connection->fetchAssoc("select * from tokens where usable=100000 and user_id=?", array($userId));
        } catch (\Exception $e) {
            pr($e);
        }
        return $token;
    }

    function getUsedTokensByUserId($userId)
    {
        $tokens = $this->connection->fetchAll("select * from tokens where user_id=? and used=usable", array($userId));
        return $tokens;
    }

    function getTotalUsageByUserId($userId)
    {
        $total = $this->connection->fetchColumn("select sum(total_bandwidth) as total from tokens where user_id ?=",array($userId),0);
        return $total;
    }


    function updateTokenUsedCounter($tokenId, $count = 1)
    {
        $token = $this->getTokenById($tokenId);
        $used = $token['used'];
        $this->connection->update("tokens", array("used" => ($used + $count)), array("id" => $tokenId));
    }

    function updateTokenBandwidth($tokenId, $size)
    {
        $token = $this->getTokenById($tokenId);
        $tb = $token['total_bandwidth'];
        $this->connection->update("tokens", array("total_bandwidth" => ($tb + $size)), array("id" => $tokenId));
    }
}