<?php

namespace PhpJsonRpc\HttpServer;

use League\Route\RouteCollection;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\Response\SapiEmitter;
use League\BooBoo\Runner as BooBooRunner;
use PhpJsonRpc\Server\Error\MethodNotFound;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\EmitterInterface;
use PhpJsonRpc\Server\Response\AbstractResponse;
use Zend\Diactoros\Response as DiactorosResponse;
use PhpJsonRpc\Server\Response\UnsuccessfulResponse;
use PhpJsonRpc\Server\Service\Service as JsonRpcService;
use League\Route\Http\Exception\MethodNotAllowedException;
use PhpJsonRpc\HttpServer\Formatter\UncaughtExceptionFormatter;
use PhpJsonRpc\Server\Response\ErrorFormatter\DefaultErrorFormatter;

final class HttpServer
{
    /**
     * @var RouteCollection
     */
    private $routeCollection;

    /**
     * @var EmitterInterface
     */
    private $emitter;

    /**
     * @var BooBooRunner
     */
    private $boobooRunner;

    /**
     * @param bool $isDebug
     */
    public function __construct($isDebug = true)
    {
        $this->routeCollection  = new RouteCollection;
        $this->emitter          = new SapiEmitter;
        $this->boobooRunner     = new BooBooRunner([new UncaughtExceptionFormatter(new DefaultErrorFormatter($isDebug))]);

        $this->boobooRunner->treatErrorsAsExceptions(true);
        $this->boobooRunner->register();
    }

    /**
     * @param ServerRequestInterface|null $request
     * @param ResponseInterface|null $response
     */
    public function handle(ServerRequestInterface $request = null, ResponseInterface $response = null)
    {
        $request    = $request  ? : ServerRequestFactory::fromGlobals();
        $response   = $response ? : new DiactorosResponse;

        try {
            $response = $this->routeCollection->dispatch($request, $response);
        } catch (MethodNotAllowedException $e) {
            /** @var \Psr\Http\Message\ResponseInterface $response */
            $response = $response->withStatus(405);
        }

        $this->emitter->emit($response);
    }

    /**
     * @param JsonRpcService $jsonRpcService
     */
    public function addService(JsonRpcService $jsonRpcService)
    {
        $this->routeCollection->post(
            $jsonRpcService->endpoint(),
            function (ServerRequestInterface $httpRequest, ResponseInterface $httpResponse, array $args) use ($jsonRpcService) {
                return $this->buildResponse($jsonRpcService, $httpRequest, $httpResponse);
            }
        );
    }

    /**
     * @param JsonRpcService $jsonRpcService
     * @param ServerRequestInterface $httpRequest
     * @param ResponseInterface $httpResponse
     *
     * @return MessageInterface
     */
    private function buildResponse(JsonRpcService $jsonRpcService,
                                   ServerRequestInterface $httpRequest,
                                   ResponseInterface $httpResponse)
    {
        $jsonRpcResponse = $jsonRpcService->dispatch($httpRequest->getBody()->getContents());

        if ($jsonRpcResponse !== null) {
            $httpResponse->getBody()->write($jsonRpcResponse->toJson());
        }

        return $httpResponse
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($this->determineStatusCode($jsonRpcResponse));
    }

    /**
     * @param AbstractResponse[]|AbstractResponse|null $jsonRpcResponse
     *
     * @return int
     */
    private function determineStatusCode($jsonRpcResponse)
    {
        $statusCode = 200;

        if ($jsonRpcResponse === null) {
            $statusCode = 202;
        } elseif ($jsonRpcResponse instanceof UnsuccessfulResponse) {
            $statusCode = 500;

            if ($jsonRpcResponse->error()->exception() instanceof MethodNotFound) {
                $statusCode = 404;
            }
        }

        return $statusCode;
    }
}
