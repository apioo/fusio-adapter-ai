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
use Fusio\Model\Agent\Input;
use Fusio\Model\Agent\ItemObject;
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

        if ($context->getUser()->isAnonymous()) {
            throw new BadRequestException('An LLM agent can only be invoked by an authenticated user');
        }

        $structuredOutput = (bool) $configuration->get('structured_output');

        $payload = $request->getPayload();
        if (!$payload instanceof Input) {
            throw new BadRequestException('Provided an invalid payload');
        }

        $output = $this->sender->send($agent, $payload, $context);

        if ($structuredOutput) {
            $item = $output->getItem();
            if ($item instanceof ItemObject) {
                $output = $item->getPayload();
            }
        }

        return $this->response->build(200, [], $output);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newAgent('agent', 'Agent', 'The agent which should be invoked'));
        $builder->add($elementFactory->newCheckbox('structured_output', 'Structured output', 'Indicates whether the structured output of the agent should be returned directly'));
    }
}
