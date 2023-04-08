<?php

namespace Sicet7\Server;

use DI\ContainerBuilder as DIContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use function DI\create;
use function DI\get;

final class ContainerBuilder
{
    /**
     * @return array
     */
    public static function getDefinitions(): array
    {
        return [
            WorkerParams::class => create(WorkerParams::class),
            Environment::class => function (): Environment
            {
                return Environment::fromGlobals();
            },
            EnvironmentInterface::class => get(Environment::class),
            RelayInterface::class => function (
                EnvironmentInterface $environment
            ): RelayInterface {
                return Relay::create($environment->getRelayAddress());
            },
            RPCInterface::class => function (
                EnvironmentInterface $environment
            ): RPCInterface {
                return RPC::create($environment->getRPCAddress());
            },
            RoadRunnerWorker::class => function (
                RelayInterface $relay,
                WorkerParams $workerParams
            ): RoadRunnerWorker {
                return new RoadRunnerWorker($relay, $workerParams->interceptSideEffects);
            },
            PSR7Worker::class => create(PSR7Worker::class)
                ->constructor(
                    get(RoadRunnerWorker::class),
                    get(ServerRequestFactoryInterface::class),
                    get(StreamFactoryInterface::class),
                    get(UploadedFileFactoryInterface::class),
                ),
            PSR7WorkerInterface::class => get(PSR7Worker::class),
            HttpWorker::class => function(
                RequestHandlerInterface $requestHandler,
                PSR7WorkerInterface $PSR7Worker,
                ResponseFactoryInterface $responseFactory,
                ContainerInterface $container
            ): HttpWorker {
                $logger = null;
                $eventDispatcher = null;
                if ($container->has(LoggerInterface::class)) {
                    $logger = $container->get(LoggerInterface::class);
                }
                if ($container->has(EventDispatcherInterface::class)) {
                    $eventDispatcher = $container->get(EventDispatcherInterface::class);
                }
                return new HttpWorker(
                    $requestHandler,
                    $PSR7Worker,
                    $responseFactory,
                    $logger,
                    $eventDispatcher
                );
            },
        ];
    }

    /**
     * @param ContainerInterface|null $parentContainer
     * @return ContainerInterface
     * @throws \Exception
     */
    public static function build(ContainerInterface $parentContainer = null): ContainerInterface
    {
        $builder = new DIContainerBuilder();
        $builder->addDefinitions(self::getDefinitions());
        $builder->useAttributes(false);
        $builder->useAutowiring(false);
        if ($parentContainer instanceof ContainerInterface) {
            $builder->wrapContainer($parentContainer);
        }
        return $builder->build();
    }
}