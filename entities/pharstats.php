<?php
namespace webtocopy\entities;

class pharstats
{
    private $connection;

    function __construct(\Doctrine\DBAL\Connection $connection)
    {
        $this->connection = $connection;
    }

    function saveStatistics($userId, $ip, $bytes)
    {

        $this->connection->insert("pharstats", array(
            "user_id" => $userId,
            "ip" => $ip,
            "bytes" => $bytes,
            "created"=>time()
        ));
    }
}