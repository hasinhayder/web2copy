<?php

class TestQueueing extends IronUnitTestCase {

    function setUp() {
        parent::setUp();
        $this->worker = new IronWorker('_config.json');
        $this->worker->ssl_verifypeer = false;
        $this->worker->upload($this->workerDir(), 'worker.php', 'TestWorker');
    }

    function tearDown() {
        parent::tearDown();
    }

    function testPostTask(){
        $task_id = $this->worker->postTask('TestWorker');
        $this->assertTrue(is_string($task_id));
        $this->assertTrue(strlen($task_id) > 0);
    }

    function testPostTasks(){
        $ids = $this->worker->postTasks('TestWorker', array(array(), array()));
        $this->assertTrue(is_array($ids));
        $this->assertEqual(sizeof($ids), 2);
    }

    function testWaitFor(){
        $task_id = $this->worker->postTask('TestWorker');
        $details = $this->worker->waitFor($task_id);
        $this->assertEqual($details->id, $task_id);
        $this->assertEqual($details->code_name, 'TestWorker');
        $this->assertEqual($details->status, 'complete');
    }

    function testTaskDetails(){
        $task_id = $this->worker->postTask('TestWorker');
        $details = $this->worker->getTaskDetails($task_id);
        $this->assertEqual($details->id,  $task_id);
        $this->assertEqual($details->code_name, 'TestWorker');
    }

    function testTaskLog(){
        $task_id = $this->worker->postTask('TestWorker', array('test' => 'search_string'));
        $this->worker->waitFor($task_id, 4, 60);
        $log = $this->worker->getLog($task_id);

        $this->assertTrue(strlen($log) > 0);
        $this->assertTrue(strpos($log, 'Hello PHP') !== false);
        $this->assertTrue(strpos($log, 'search_string') !== false);
    }

    function testTaskProgress(){
        $task_id = $this->worker->postTask('TestWorker');
        $res = $this->worker->setTaskProgress($task_id, 50, 'Job half-done');
        $this->assertEqual($res->msg, 'Progress set');
    }

    function testDeleteTask(){
        $task_id = $this->worker->postTask('TestWorker');
        $res = $this->worker->deleteTask($task_id);
        $this->assertEqual($res->msg, 'Cancelled');
    }

    function testPostTaskOptions(){
        $task_id = $this->worker->postTask('TestWorker', array(), array(
            'priority' => 2,
            'timeout' => 300,
            'delay' => 10,
        ));
        sleep(4);
        $details = $this->worker->getTaskDetails($task_id);
        $this->assertEqual($details->timeout,  300);
        $this->assertEqual($details->status,   'queued');
        $this->assertEqual($details->priority, 2);
        $this->assertEqual($details->delay,    10);
        $details = $this->worker->waitFor($task_id, 4, 60);
        $this->assertEqual($details->status, 'complete');
    }

    function  testListTasksAll(){
        $task_id = $this->worker->postTask('TestWorker');
        $data = $this->worker->getTasks();
        $this->assertIsA($data, 'Array');
    }

    function  testListTasksFilter(){
        $task_id = $this->worker->postTask('TestWorker', Array(), Array('delay' => 200));

        #$this->worker->debug_enabled = true;

        $data = $this->worker->getTasks(0, 10, array('queued' => 1));
        $this->assertIsA($data, 'Array');
        $this->assertTrue(sizeof($data) > 0);
        foreach($data as $task){
            #TODO: test & uncomment
            #$this->assertEqual($task->status, 'queued');
        }
    }


    function testSetProgress(){
        $task_id = $this->worker->postTask('TestWorker');

        $this->worker->setProgress($task_id, 77, 'test value');
        $details = $this->worker->getTaskDetails($task_id);
        $this->assertEqual($details->percent, 77);
        $this->assertEqual($details->msg, 'test value');
    }








}