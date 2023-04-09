<?php

namespace Sicet7\Server;

use DI\Definition\FactoryDefinition;
use DI\Definition\ObjectDefinition;
use DI\Definition\Reference;
use DI\Definition\Source\MutableDefinitionSource;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sicet7\Container\Base\Interfaces\PluginInterface;
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
     * @param MutableDefinitionSource $source
     * @return void
     */
    public function register(MutableDefinitionSource $source): void
    {
        $source->addDefinition(new ObjectDefinition(
            WorkerParams::class,
            WorkerParams::class
        ));
        $source->addDefinition(new FactoryDefinition(
            Environment::class,
            function (): Environment
            {
                return Environment::fromGlobals();
            }
        ));
        $source->addDefinition($this->makeReference(
            EnvironmentInterface::class,
            Environment::class
        ));
        $source->addDefinition(new FactoryDefinition(
            RelayInterface::class,
            function (
                EnvironmentInterface $environment
            ): RelayInterface {
                return Relay::create($environment->getRelayAddress());
            }
        ));
        $source->addDefinition(new FactoryDefinition(
            RPCInterface::class,
            function (
                EnvironmentInterface $environment
            ): RPCInterface {
                return RPC::create($environment->getRPCAddress());
            }
        ));
        $source->addDefinition(new FactoryDefinition(
            RoadRunnerWorker::class,
            function (
                RelayInterface $relay,
                WorkerParams $workerParams
            ): RoadRunnerWorker {
                return new RoadRunnerWorker($relay, $workerParams->interceptSideEffects);
            }
        ));
        $PSR7WorkerObjectDefinition = new ObjectDefinition(PSR7Worker::class, PSR7Worker::class);
        $PSR7WorkerObjectDefinition->setConstructorInjection(ObjectDefinition\MethodInjection::constructor([
            new Reference(RoadRunnerWorker::class),
            new Reference(ServerRequestFactoryInterface::class),
            new Reference(StreamFactoryInterface::class),
            new Reference(UploadedFileFactoryInterface::class)
        ]));
        $source->addDefinition($PSR7WorkerObjectDefinition);
        $source->addDefinition($this->makeReference(PSR7WorkerInterface::class, PSR7Worker::class));
        $source->addDefinition(new FactoryDefinition(
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
        ));
    }

    /**
     * @param string $name
     * @param string $target
     * @return Reference
     */
    private function makeReference(string $name, string $target): Reference
    {
        $ref = new Reference($target);
        $ref->setName($name);
        return $ref;
    }
}