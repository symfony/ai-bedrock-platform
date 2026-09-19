<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Mantle\Responses;

use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Models served through the AWS Bedrock Mantle OpenAI-compatible Responses endpoint
 * ("https://bedrock-mantle.<region>.api.aws/openai/v1/responses").
 *
 * Unlike the Chat Completions route, the Responses API surfaces the model's reasoning trace,
 * so the catalog advertises the THINKING capability for every entry.
 *
 * @author asrar <aszenz@gmail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        // The Gemma 4 family is what this catalog covers: it is multimodal (text/image input) and
        // exposes a reasoning trace, and it answers on the "/openai/v1/responses" path the factory
        // defaults to. Mantle also serves gpt-oss through the Responses API, but only on
        // "/v1/responses" - registering those here additionally requires passing that $path to the
        // factory, since either path rejects the other family with a 400.
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::INPUT_IMAGE,
            Capability::INPUT_MULTIMODAL,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::THINKING,
        ];

        $defaultModels = [];
        foreach (['google.gemma-4-31b', 'google.gemma-4-26b-a4b', 'google.gemma-4-e2b'] as $model) {
            $defaultModels[$model] = ['class' => ResponsesModel::class, 'capabilities' => $capabilities];
        }

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
