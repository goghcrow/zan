--TEST--
swoole_server: sendfile
--SKIPIF--
<?php require __DIR__ . "/../inc/skipif.inc"; ?>
--INI--
assert.active=1
assert.warning=1
assert.bail=0
assert.quiet_eval=0


--FILE--
<?php

require_once __DIR__ . "/../inc/zan.inc";

$host = TCP_SERVER_HOST1;
$port = TCP_SERVER_PORT1;

$pid = pcntl_fork();
if ($pid < 0) {
    exit;
}

if ($pid === 0) {
    usleep(500000);

    $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
    
    $file_path = __DIR__ . "/sendfile.txt";

    //设置事件回调函数
    $client->on("connect", function($cli) use ($file_path) {
        $cli->sendfile($file_path);
    });

    $client->on("receive", function($cli, $data){
        echo "Client Received: $data";
        $cli->close();
    });

    $client->on("error", function($cli){
        echo "Clinet Error.";
    });
    $client->on("close", function($cli){
        //echo "Client Close.";
    });
    //发起网络连接
    $client->connect($host, $port, 0.5);

} else {

    $serv = new swoole_server($host, $port);
    $serv->set([
        'worker_num' => 1,
        'net_worker_num' => 1,
        'log_file' => '/tmp/test_log.log',
    ]);

    $serv->on('Connect', function ($serv, $fd){
        //echo "Server: onConnected, client_fd=$fd\n";
    });

    $serv->on('Receive', function ($serv, $fd, $from_id, $data) {
        echo "Server: Receive data: $data";
        $serv->send($fd, "Hello Client!");
        $serv->shutdown();
    });

    $serv->start();
}


?>
--EXPECT--
Server: Receive data: sendifle
sendfile
Client Received: Hello Client!
