<?php

namespace Sicet7\Server;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Sicet7\Server\Events\BadRequest;
use Sicet7\Server\Events\PostDispatch;
use Sicet7\Server\Events\PreDispatch;
use Sicet7\Server\Events\TerminateWorker;
use Sicet7\Server\Events\UnhandledException;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;

final readonly class HttpWorker
{
    public function __construct(
        private RequestHandlerInterface $requestHandler,
        private PSR7WorkerInterface $PSR7Worker,
        private ResponseFactoryInterface $responseFactory,
        private ?LoggerInterface $logger = null,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    public function run(): void
    {
        do {
            try {
                $request = $this->PSR7Worker->waitRequest();

                if (!($request instanceof ServerRequestInterface)) {
                    $this->log(
                        LogLevel::INFO,
                        'Termination request received'
                    );
                    $this->eventDispatcher?->dispatch(new TerminateWorker());
                    break;
                }

            } catch (\Throwable $throwable) {
                $this->log(
                    LogLevel::NOTICE,
                    'Malformed request received!',
                    $throwable
                );
                try {
                    $this->eventDispatcher?->dispatch(new BadRequest($throwable));
                    $this->PSR7Worker->respond(
                        $this->responseFactory->createResponse(400, 'Bad Request')
                    );
                } catch (\Throwable $badRequestException) {
                    $this->log(
                        LogLevel::ERROR,
                        'Failed to deliver bad request response, terminating worker.',
                        $badRequestException
                    );
                    break;
                }
                continue;
            }

            try {
                $this->eventDispatcher?->dispatch(new PreDispatch($request));
                $response = $this->requestHandler->handle($request);
                $this->eventDispatcher?->dispatch(new PostDispatch($response));
                $this->PSR7Worker->respond($response);
            } catch (\Throwable $throwable) {
                try {
                    $this->log(
                        LogLevel::ERROR,
                        'Request handler threw unhandled exception!',
                        $throwable
                    );
                    $this->eventDispatcher?->dispatch(new UnhandledException($throwable));
                    $this->PSR7Worker->respond(
                        $this->responseFactory->createResponse(500, 'Internal Server Error')
                    );
                } catch (\Throwable $internalServerError) {
                    $this->log(
                        LogLevel::ERROR,
                        'Failed to deliver internal server error response, terminating worker.',
                        $internalServerError
                    );
                    break;
                }
            }

        } while(true);
    }

    /**
     * @param string $level
     * @param string $msg
     * @param \Throwable|null $throwable
     * @return void
     */
    private function log(string $level, string $msg, ?\Throwable $throwable = null): void
    {
        if ($this->logger === null) {
            return;
        }
        $context = [];
        if ($throwable !== null) {
            $context['throwable'] = $this->throwableToArray($throwable);
        }
        $this->logger->log($level, $msg, $context);
    }

    /**
     * @param \Throwable $throwable
     * @param int $levels
     * @return array
     */
    private function throwableToArray(\Throwable $throwable, int $levels = 3): array
    {
        $output = [
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => $throwable->getTrace(),
        ];
        if ($throwable->getPrevious() instanceof \Throwable && $levels > 0) {
            $output['previous'] = $this->throwableToArray($throwable->getPrevious(), $levels - 1);
        }
        return $output;
    }
}