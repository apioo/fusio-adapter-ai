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

namespace Fusio\Adapter\Ai\Connection;

use Fusio\Engine\Agent\ToolsInterface;
use Fusio\Engine\Connection\PingableInterface;
use Fusio\Engine\ConnectionAbstract;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Symfony\AI\Agent\Agent as SymfonyAgent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\ExceptionInterface;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Platform\Bridge\Anthropic;
use Symfony\AI\Platform\Bridge\Gemini;
use Symfony\AI\Platform\Bridge\Ollama;
use Symfony\AI\Platform\Bridge\OpenAi;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;

/**
 * Agent
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class Agent extends ConnectionAbstract implements PingableInterface
{
    public function __construct(private ToolsInterface $tools)
    {
    }

    public function getName(): string
    {
        return 'Agent';
    }

    public function getConnection(ParametersInterface $config): AgentInterface
    {
        $apiKey = $config->get('api_key');
        $url = $config->get('url');

        $model = $config->get('model');
        if (empty($model)) {
            throw new ConfigurationException('Provided no model');
        }

        $platform = $config->get('platform');
        $needsApiKey = !in_array($platform, ['ollama'], true);
        if ($needsApiKey && empty($apiKey)) {
            throw new ConfigurationException('Provided no api key');
        }

        if (!$needsApiKey && empty($url)) {
            throw new ConfigurationException('Provided no url');
        }

        $platform = match ($config->get('platform')) {
            'anthropic' => Anthropic\PlatformFactory::create(apiKey: $apiKey),
            'gemini' => Gemini\PlatformFactory::create(apiKey: $apiKey),
            'ollama' => Ollama\PlatformFactory::create(hostUrl: $url),
            default => OpenAi\PlatformFactory::create(apiKey: $apiKey),
        };

        $toolbox = $this->tools->resolve();
        if ($toolbox instanceof ToolboxInterface) {
            $toolProcessor = new AgentProcessor($toolbox);

            return new SymfonyAgent($platform, $model, inputProcessors: [$toolProcessor], outputProcessors: [$toolProcessor]);
        } else {
            return new SymfonyAgent($platform, $model);
        }
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $types = [
            'anthropic' => 'Anthropic',
            'gemini'    => 'Gemini',
            'ollama'    => 'Ollama',
            'chatgpt'   => 'ChatGPT',
        ];

        $models = [];
        foreach ($types as $type => $name) {
            $modelCatalog = $this->getModelCatalog($type);

            foreach ($modelCatalog->getModels() as $modelName => $config) {
                $models[$modelName] = $name . ' - ' . $modelName;
            }
        }

        $builder->add($elementFactory->newSelect('type', 'Type', $types, 'The agent type'));
        $builder->add($elementFactory->newSelect('model', 'Model', $models, 'The selected model'));
        $builder->add($elementFactory->newInput('api_key', 'Password', 'password', 'The API key'));
        $builder->add($elementFactory->newInput('url', 'Url', 'text', 'For Ollama provide an url of the host i.e. http://localhost:11434'));
    }

    public function ping(mixed $connection): bool
    {
        if ($connection instanceof AgentInterface) {
            $messages = new MessageBag(
                Message::forSystem('You are invoked through the AI integration of the Fusio API platform.'),
                Message::ofUser('This is just a test message to check whether the integration works, can you respond with "ok" in case everything works?'),
            );

            try {
                $connection->call($messages);
                return true;
            } catch (ExceptionInterface) {
                return false;
            }
        } else {
            return false;
        }
    }

    private function getModelCatalog(string $type): ModelCatalogInterface
    {
        return match ($type) {
            'anthropic' => new Anthropic\ModelCatalog(),
            'gemini' => new Gemini\ModelCatalog(),
            'ollama' => new Ollama\ModelCatalog(),
            default => new OpenAi\ModelCatalog(),
        };
    }
}
