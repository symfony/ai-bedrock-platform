<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Anthropic;

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
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
final readonly class ClaudeResponseConverter implements ResponseConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Claude;
    }

    public function convert(RawResponseInterface|RawBedrockResponse $response, array $options = []): ToolCallResponse|TextResponse
    {
        $data = $response->getRawData();

        if (!isset($data['content']) || [] === $data['content']) {
            throw new RuntimeException('Response does not contain any content');
        }

        if (!isset($data['content'][0]['text']) && !isset($data['content'][0]['type'])) {
            throw new RuntimeException('Response content does not contain any text or type');
        }

        $toolCalls = [];
        foreach ($data['content'] as $content) {
            if ('tool_use' === $content['type']) {
                $toolCalls[] = new ToolCall($content['id'], $content['name'], $content['input']);
            }
        }
        if ([] !== $toolCalls) {
            return new ToolCallResponse(...$toolCalls);
        }

        return new TextResponse($data['content'][0]['text']);
    }
}
