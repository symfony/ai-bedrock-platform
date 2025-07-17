<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Meta;

use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResponse;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Response\RawResponseInterface;
use Symfony\AI\Platform\Response\TextResponse;
use Symfony\AI\Platform\ResponseConverterInterface;

/**
 * @author Björn Altmann
 */
class LlamaResponseConverter implements ResponseConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Llama;
    }

    public function convert(RawResponseInterface|RawBedrockResponse $response, array $options = []): TextResponse
    {
        $data = $response->getRawData();

        if (!isset($data['generation'])) {
            throw new RuntimeException('Response does not contain any content');
        }

        return new TextResponse($data['generation']);
    }
}
