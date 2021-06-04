<?php

namespace App\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

class AlgorithmStrategyCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        // Find the definition of our context service
        $contextDefinition = $container->findDefinition('algorithmContext');

        // Find the definitions of all the strategy services
        $strategyServiceIds = array_keys(
            $container->findTaggedServiceIds('algorithmStrategy')
        );

        // Add an addStrategy call on the context for each strategy
        foreach ($strategyServiceIds as $strategyServiceId) {
            $ref = new Reference($strategyServiceId, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
            $contextDefinition->setPublic(true);
            $contextDefinition->addMethodCall(
                'addStrategy',
                [$ref]
            );
        }
    }
}