<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract;

use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Model;

use function Symfony\Component\String\u;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCallMessageNormalizer extends ModelContractNormalizer
{
    /**
     * @param ToolCallMessage $data
     *
     * @return array{
     *     role: 'user',
     *     content: array<array{
     *         toolResult: array{
     *             toolUseId: string,
     *             content: list<array{json: string}|array{text: string}|array{image: array{format: string, source: array{bytes: string}}}>,
     *         }
     *     }>
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $parts = $data->getContent();
        $content = $this->isTextOnly($parts)
            ? [['json' => $data->asText() ?? '']]
            : array_map($this->normalizeContentPart(...), $parts);

        return [
            'role' => 'user',
            'content' => [
                [
                    'toolResult' => [
                        'toolUseId' => $data->getToolCall()->getId(),
                        'content' => $content,
                    ],
                ],
            ],
        ];
    }

    protected function supportedDataClass(): string
    {
        return ToolCallMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Nova;
    }

    /**
     * @return array{text: string}|array{image: array{format: string, source: array{bytes: string}}}
     */
    private function normalizeContentPart(ContentInterface $part): array
    {
        if ($part instanceof Text) {
            return ['text' => $part->getText()];
        }

        if ($part instanceof Image) {
            return [
                'image' => [
                    'format' => u($part->getFormat())->replace('image/', '')->replace('jpg', 'jpeg')->toString(),
                    'source' => ['bytes' => $part->asBase64()],
                ],
            ];
        }

        throw new RuntimeException(\sprintf('Unsupported tool result content part of type "%s".', get_debug_type($part)));
    }

    /**
     * @param ContentInterface[] $parts
     */
    private function isTextOnly(array $parts): bool
    {
        foreach ($parts as $part) {
            if (!$part instanceof Text) {
                return false;
            }
        }

        return true;
    }
}
