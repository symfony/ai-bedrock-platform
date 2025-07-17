<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Nova;

use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResponse;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Response\RawResponseInterface;
use Symfony\AI\Platform\Response\TextResponse;
use Symfony\AI\Platform\Response\ToolCall;
use Symfony\AI\Platform\Response\ToolCallResponse;
use Symfony\AI\Platform\ResponseConverterInterface;

/**
 * @author Björn Altmann
 */
class NovaResponseConverter implements ResponseConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Nova;
    }

    public function convert(RawResponseInterface|RawBedrockResponse $response, array $options = []): ToolCallResponse|TextResponse
    {
        $data = $response->getRawData();

        if (!isset($data['output']) || [] === $data['output']) {
            throw new RuntimeException('Response does not contain any content');
        }

        if (!isset($data['output']['message']['content'][0]['text'])) {
            throw new RuntimeException('Response content does not contain any text');
        }

        $toolCalls = [];
        foreach ($data['output']['message']['content'] as $content) {
            if (isset($content['toolUse'])) {
                $toolCalls[] = new ToolCall($content['toolUse']['toolUseId'], $content['toolUse']['name'], $content['toolUse']['input']);
            }
        }
        if ([] !== $toolCalls) {
            return new ToolCallResponse(...$toolCalls);
        }

        return new TextResponse($data['output']['message']['content'][0]['text']);
    }
}
