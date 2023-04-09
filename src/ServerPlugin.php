<?php

namespace Sicet7\Server;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sicet7\Plugin\Container\Interfaces\PluginInterface;
use Sicet7\Plugin\Container\MutableDefinitionSourceHelper;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use Spiral\RoadRunner\Http\PSR7Worker;

final class ServerPlugin implements PluginInterface
{
    /**
     * @param MutableDefinitionSourceHelper $source
     * @return void
     */
    public function register(MutableDefinitionSourceHelper $source): void
    {
        $source->object(WorkerParams::class, WorkerParams::class);
        $source->factory(
            Environment::class,
            function (): Environment
            {
                return Environment::fromGlobals();
            }
        );
        $source->reference(EnvironmentInterface::class, Environment::class);
        $source->factory(
            RelayInterface::class,
            function (
                EnvironmentInterface $environment
            ): RelayInterface {
                return Relay::create($environment->getRelayAddress());
            }
        );
        $source->factory(
            RPCInterface::class,
            function (
                EnvironmentInterface $environment
            ): RPCInterface {
                return RPC::create($environment->getRPCAddress());
            }
        );
        $source->factory(
            RoadRunnerWorker::class,
            function (
                RelayInterface $relay,
                WorkerParams $workerParams
            ): RoadRunnerWorker {
                return new RoadRunnerWorker($relay, $workerParams->interceptSideEffects);
            }
        );
        $source->autowire(PSR7Worker::class, PSR7Worker::class);
        $source->reference(PSR7WorkerInterface::class, PSR7Worker::class);
        $source->factory(
            HttpWorker::class,
            function(
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
            }
        );
    }
}