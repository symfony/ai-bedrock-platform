<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock;

use AsyncAws\BedrockRuntime\Result\InvokeModelResponse;
use Symfony\AI\Platform\Response\RawResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class RawBedrockResponse implements RawResponseInterface
{
    public function __construct(
        private InvokeModelResponse $invokeModelResponse,
    ) {
    }

    public function getRawData(): array
    {
        return json_decode($this->invokeModelResponse->getBody(), true, 512, \JSON_THROW_ON_ERROR);
    }

    public function getRawObject(): InvokeModelResponse
    {
        return $this->invokeModelResponse;
    }
}
