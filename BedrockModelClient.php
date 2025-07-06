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
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Response\ResponseInterface;

/**
 * @author Björn Altmann
 */
interface BedrockModelClient
{
    public function supports(Model $model): bool;

    /**
     * @param array<mixed>|string  $payload
     * @param array<string, mixed> $options
     */
    public function request(Model $model, array|string $payload, array $options = []): InvokeModelResponse;

    public function convert(InvokeModelResponse $bedrockResponse): ResponseInterface;
}
