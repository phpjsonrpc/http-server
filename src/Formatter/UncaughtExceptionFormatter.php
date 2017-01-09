<?php

namespace PhpJsonRpc\HttpServer\Formatter;

use PhpJsonRpc\Server\Response\Error;
use League\BooBoo\Formatter\AbstractFormatter;
use PhpJsonRpc\Server\Response\UnsuccessfulResponse;
use PhpJsonRpc\Server\Response\Contract\ErrorFormatter;
use PhpJsonRpc\Server\Response\ErrorFormatter\DefaultErrorFormatter;

final class UncaughtExceptionFormatter extends AbstractFormatter
{
    /**
     * @var ErrorFormatter
     */
    private $errorFormatter;

    /**
     * @param ErrorFormatter|null $errorFormatter
     */
    public function __construct(ErrorFormatter $errorFormatter = null)
    {
        $this->errorFormatter = $errorFormatter ? $errorFormatter : new DefaultErrorFormatter;
    }

    /**
     * @param \Exception $exception
     *
     * @return string
     */
    public function format(\Exception $exception)
    {
        header('Content-Type: application/json', true);

        if ($exception instanceof \ErrorException) {
            $exception = new \ErrorException(
                $this->determineSeverityTextValue($exception->getSeverity()) . ': ' .$exception->getMessage(),
                $exception->getCode(),
                $exception->getSeverity(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getPrevious());
        }

        $data = new UnsuccessfulResponse(null, new Error($exception, $this->errorFormatter));

        return json_encode($data);
    }
}
