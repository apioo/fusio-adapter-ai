<?php
/*
 * Fusio - Self-Hosted API Management for Builders.
 * For the current version and information visit <https://www.fusio-project.org/>
 *
 * Copyright (c) Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Fusio\Adapter\Ai\Action;

use Fusio\Engine\Action\RuntimeInterface;
use Fusio\Engine\ActionAbstract;
use Fusio\Engine\Agent\SenderInterface;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use Fusio\Model\Common\AgentInput;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\Exception\BadRequestException;

/**
 * AgentCall
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class AgentCall extends ActionAbstract
{
    public function __construct(private SenderInterface $sender, RuntimeInterface $runtime)
    {
        parent::__construct($runtime);
    }

    public function getName(): string
    {
        return 'Agent-Call';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $agent = (int) $configuration->get('agent');
        if (empty($agent)) {
            throw new ConfigurationException('No agent provided');
        }

        $payload = $request->getPayload();
        if (!$payload instanceof AgentInput) {
            throw new BadRequestException('Provided an invalid payload');
        }

        $output = $this->sender->send($agent, $payload, $context);

        return $this->response->build(200, [], $output);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newAgent('agent', 'Agent', 'The agent which should be invoked'));
    }
}
