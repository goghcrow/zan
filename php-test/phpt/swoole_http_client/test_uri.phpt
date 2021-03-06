--TEST--
swoole_http_client: test server[uri]
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

 require_once __DIR__ . "/../../apitest/swoole_http_client/simple_http_client.php";


$host = HTTP_SERVER_HOST;
$port = HTTP_SERVER_PORT;

$pid = pcntl_fork();
if ($pid < 0) {
    exit;
}

if ($pid === 0) {
    usleep(500000);

    $httpClient = makeHttpClient($host, $port);

    addTimer($httpClient);
    $ok = $httpClient->get("/uri", function(\swoole_http_client $httpClient) {
        //echo json_encode($httpClient);

        cancelTimer($httpClient);
        assert($httpClient->statusCode === 200);
        assert($httpClient->errCode === 0);
        assert($httpClient->body === "/uri");

    });
    assert($ok);

} else {

    $httpServ = new \swoole_http_server($host, $port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        
    $httpServ->set([
        // 输出限制
        'user' => 'www-data',
        'group' => 'www-data',
        // 'log_file' => __DIR__.'/swoole.log',
        'worker_num' => 1,
        'net_worker_num' => 1,
        ]);


    $httpServ->on('WorkerStart', function (\swoole_http_server $httpServ, $workerId) {
        //echo "onWorkerStart! worker_id=$workerId\n";
    });

    $httpServ->on('WorkerStop', function (\swoole_http_server $httpServ, $workerId) {
        //echo "onWorkerStop! worker_id=$workerId\n";
    });

    $httpServ->on('connect', function ($httpServ, $fd) {
        //echo "HttpServer: onConnected, client_fd=$fd\n";
        //$httpServ->send($fd, "Hello Client!");
    });

    $httpServ->on('receive', function (\swoole_http_server $swooleServer, $fd, $fromId, $data) {
        $recv_len = strlen($data);
        echo "receive: len $recv_len\n";
        //$httpServ->send($fd, "HelloHttpClient!");
        $httpServ->shutdown();
    });


    $httpServ->on('request', function (\swoole_http_request $request, \swoole_http_response $response) use($httpServ) {
        $uri = $request->server["request_uri"];
        if ($uri === "/favicon.ico")  {
            $response->status(404);
            $response->end();
            $httpServ->shutdown();
            return;
        }

        testSetCookie:
        {
            $name = "name";
            $value = "value";
            $expire = 0;
            $path = "/";
            $domain = "";
            $secure = false;
            $httpOnly = true;
            $response->cookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
            $expect = "name=value; path=/; httponly";
            assert(in_array($expect, $response->cookie, true));
        }

        if ($uri === "/uri") {
            echo "onRequest/uri\n";
            echo "SUCCESS\n";
            $response->end($request->server['request_uri']);
            $httpServ->shutdown();
            return;
        }
    });

    $httpServ->start();
}

?>
--EXPECT--
onRequest/uri
SUCCESS
