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

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use Symfony\AI\Platform\Bridge\Bedrock\Anthropic\ClaudeHandler;
use Symfony\AI\Platform\Bridge\Bedrock\Meta\LlamaModelClient;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\NovaHandler;
use Symfony\AI\Platform\Contract;

/**
 * @author Björn Altmann
 */
final readonly class PlatformFactory
{
    public static function create(
        BedrockRuntimeClient $bedrockRuntimeClient = new BedrockRuntimeClient(),
        ?Contract $contract = null,
    ): Platform {
        if (!class_exists(BedrockRuntimeClient::class)) {
            throw new \RuntimeException('For using the Bedrock platform, the async-aws/bedrock-runtime package is required. Try running "composer require async-aws/bedrock-runtime".');
        }

        $modelClient[] = new ClaudeHandler($bedrockRuntimeClient);
        $modelClient[] = new NovaHandler($bedrockRuntimeClient);
        $modelClient[] = new LlamaModelClient($bedrockRuntimeClient);

        return new Platform($modelClient, $contract);
    }
}
