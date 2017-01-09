<?php

require __DIR__ . '/../vendor/autoload.php';

$jsonRpcService = new \PhpJsonRpc\Server\Service\Service('jsonrpc/v1');

$jsonRpcService->add(new \PhpJsonRpc\Server\Service\Method\Closure(
    'welcome',
    new \PhpJsonRpc\Server\Service\Method\Callables\CallableClosure(
        function () {
            return 'hello';
        }
    )
));

$JsonRpcHttpServer = new \PhpJsonRpc\HttpServer\HttpServer();
$JsonRpcHttpServer->addService($jsonRpcService);
$JsonRpcHttpServer->handle();
