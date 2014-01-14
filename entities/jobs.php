<?php
namespace webtocopy\entities;

class jobs
{
    private $connection;

    function __construct(\Doctrine\DBAL\Connection $connection)
    {
        $this->connection = $connection;
    }

    function addNewJob($tokenId, $url, $size, $fromIP){
        $time = time();
        $data = $this->connection->insert("jobs",array(
            "token_id"=>$tokenId,
            "url"=>$url,
            "from_ip"=>$fromIP,
            "transfer_size"=>$size,
            "created"=>$time,
            "transfer_status"=>0
        ));
        if($data){
            //find the inserted job
            $jobid = $this->connection->lastInsertId();
            $job = $this->connection->fetchAssoc("select * from jobs where id=?",array($jobid));
            return $job;
        }
    }

    function getJobById($id){
        $job = $this->connection->fetchAssoc("select * from jobs where id=?",array($id));
        return $job;
    }
    function getJobByTaskId($id){
        $job = $this->connection->fetchAssoc("select * from jobs where task_id=?",array($id));
        return $job;
    }

    function updateJob($id, $data){
        $res = $this->connection->update("jobs",$data,array("id"=>$id));
//        echo $res;

        if($res){
            $job = $this->getJobById($id);
            return $job;
        }
        return false;
    }


}