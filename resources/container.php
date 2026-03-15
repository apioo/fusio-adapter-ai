<?php

use Fusio\Adapter\Ai\Action\AgentCall;
use Fusio\Adapter\Ai\Connection\Agent;
use Fusio\Engine\Adapter\ServiceBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container) {
    $services = ServiceBuilder::build($container);

    $services->set(Agent::class);
    $services->set(AgentCall::class);
};
