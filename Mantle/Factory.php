<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Mantle;

use AsyncAws\Core\Credentials\CredentialProvider;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\AnthropicContract;
use Symfony\AI\Platform\Bridge\Anthropic\ResultConverter as AnthropicResultConverter;
use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Messages\ModelCatalog as MessagesModelCatalog;
use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Messages\ModelClient as MessagesModelClient;
use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Responses\ModelCatalog as ResponsesModelCatalog;
use Symfony\AI\Platform\Bridge\Generic\Completions\ResultConverter as CompletionsResultConverter;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\OpenResponses\Contract\OpenResponsesContract;
use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Bridge\OpenResponses\ResultConverter as ResponsesResultConverter;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Bridge for the AWS Bedrock "Mantle" endpoint.
 *
 * Unlike the InvokeModel API exposed by {@see \Symfony\AI\Platform\Bridge\Bedrock\Factory}, Mantle
 * speaks plain wire protocols, and serves each of them on its own route of the same host. The
 * `$api` argument selects one:
 *
 *  * "completions" - the OpenAI Chat Completions protocol, via the symfony/ai-generic-platform bridge
 *  * "responses"   - the OpenAI Responses protocol, via the symfony/ai-open-responses-platform bridge
 *  * "messages"    - the Anthropic Messages protocol, via the symfony/ai-anthropic-platform bridge
 *
 * A provider serves exactly one route, with the model catalog of that route. Combine several of
 * them in a single {@see Platform} to reach models of more than one route.
 *
 * Every route derives its base URL from the AWS region ("https://bedrock-mantle.<region>.api.aws")
 * and authenticates with a Bedrock API key sent as a bearer token, or, when no API key is given,
 * with AWS SigV4 signing using the standard credential chain.
 *
 * @see https://docs.aws.amazon.com/bedrock/latest/userguide/bedrock-mantle.html
 *
 * @author asrar <aszenz@gmail.com>
 */
final class Factory
{
    private const APIS = ['completions', 'responses', 'messages'];

    /**
     * @param 'completions'|'responses'|'messages' $api
     * @param 'none'|'short'|'long'|null           $cacheRetention only supported by the "messages" API
     * @param string|null                          $workspace      only supported by the "messages" API
     * @param string|null                          $path           overrides the request path, not supported by the "messages" API
     * @param non-empty-string|null                $name
     */
    public static function createProvider(
        #[\SensitiveParameter] ?string $apiKey = null,
        string $region = 'us-west-2',
        string $api = 'completions',
        ?CredentialProvider $credentialProvider = null,
        ?HttpClientInterface $httpClient = null,
        ?ModelCatalogInterface $modelCatalog = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?string $cacheRetention = null,
        ?string $workspace = null,
        ?string $path = null,
        ?string $name = null,
    ): ProviderInterface {
        if (!\in_array($api, self::APIS, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid Bedrock Mantle API "%s". Supported values are "%s".', $api, implode('", "', self::APIS)));
        }

        if ('' === $apiKey) {
            throw new InvalidArgumentException('The Bedrock API key must not be empty.');
        }

        if ('' === $region) {
            throw new InvalidArgumentException('The region must not be empty.');
        }

        if ('messages' !== $api && (null !== $cacheRetention || null !== $workspace)) {
            throw new InvalidArgumentException('The "cacheRetention" and "workspace" arguments are only supported by the Bedrock Mantle Messages API.');
        }

        if ('messages' === $api && null !== $path) {
            throw new InvalidArgumentException('The "path" argument is not supported by the Bedrock Mantle Messages API, which is served on a single path.');
        }

        if ('' === $workspace) {
            throw new InvalidArgumentException('The Bedrock Mantle workspace must not be empty.');
        }

        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $baseUrl = \sprintf('https://bedrock-mantle.%s.api.aws', $region);

        [$modelClient, $resultConverter, $defaultCatalog, $defaultContract, $defaultName] = match ($api) {
            'responses' => self::createResponsesRoute($httpClient, $baseUrl, $region, $apiKey, $credentialProvider, $path),
            'messages' => self::createMessagesRoute($httpClient, $baseUrl, $region, $apiKey, $credentialProvider, $cacheRetention, $workspace),
            'completions' => self::createCompletionsRoute($httpClient, $baseUrl, $region, $apiKey, $credentialProvider, $path),
        };

        return new Provider(
            $name ?? $defaultName,
            [$modelClient],
            [$resultConverter],
            $modelCatalog ?? $defaultCatalog,
            $contract ?? $defaultContract,
            $eventDispatcher,
        );
    }

    /**
     * @param 'completions'|'responses'|'messages' $api
     * @param 'none'|'short'|'long'|null           $cacheRetention only supported by the "messages" API
     * @param string|null                          $workspace      only supported by the "messages" API
     * @param string|null                          $path           overrides the request path, not supported by the "messages" API
     * @param non-empty-string|null                $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] ?string $apiKey = null,
        string $region = 'us-west-2',
        string $api = 'completions',
        ?CredentialProvider $credentialProvider = null,
        ?HttpClientInterface $httpClient = null,
        ?ModelCatalogInterface $modelCatalog = null,
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?string $cacheRetention = null,
        ?string $workspace = null,
        ?string $path = null,
        ?string $name = null,
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $region, $api, $credentialProvider, $httpClient, $modelCatalog, $contract, $eventDispatcher, $cacheRetention, $workspace, $path, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }

    /**
     * @return array{ModelClientInterface, ResultConverterInterface, ModelCatalogInterface, Contract|null, non-empty-string}
     */
    private static function createCompletionsRoute(
        EventSourceHttpClient $httpClient,
        string $baseUrl,
        string $region,
        #[\SensitiveParameter] ?string $apiKey,
        ?CredentialProvider $credentialProvider,
        ?string $path,
    ): array {
        if (!class_exists(CompletionsModel::class) || !class_exists(CompletionsResultConverter::class)) {
            throw new RuntimeException('For using the Bedrock Mantle Chat Completions API, the symfony/ai-generic-platform package is required. Try running "composer require symfony/ai-generic-platform".');
        }

        return [
            new ModelClient($httpClient, $baseUrl, $region, $apiKey, $credentialProvider, $path ?? '/v1/chat/completions'),
            new CompletionsResultConverter(),
            new ModelCatalog(),
            null,
            'bedrock-mantle',
        ];
    }

    /**
     * @return array{ModelClientInterface, ResultConverterInterface, ModelCatalogInterface, Contract, non-empty-string}
     */
    private static function createResponsesRoute(
        EventSourceHttpClient $httpClient,
        string $baseUrl,
        string $region,
        #[\SensitiveParameter] ?string $apiKey,
        ?CredentialProvider $credentialProvider,
        ?string $path,
    ): array {
        if (!class_exists(OpenResponsesContract::class) || !class_exists(ResponsesModel::class) || !class_exists(ResponsesResultConverter::class)) {
            throw new RuntimeException('For using the Bedrock Mantle Responses API, the symfony/ai-open-responses-platform package is required. Try running "composer require symfony/ai-open-responses-platform".');
        }

        return [
            new ModelClient($httpClient, $baseUrl, $region, $apiKey, $credentialProvider, $path ?? '/openai/v1/responses', ResponsesModel::class),
            new ResponsesResultConverter(),
            new ResponsesModelCatalog(),
            OpenResponsesContract::create(),
            'bedrock-mantle-responses',
        ];
    }

    /**
     * @param 'none'|'short'|'long'|null $cacheRetention
     *
     * @return array{ModelClientInterface, ResultConverterInterface, ModelCatalogInterface, Contract, non-empty-string}
     */
    private static function createMessagesRoute(
        EventSourceHttpClient $httpClient,
        string $baseUrl,
        string $region,
        #[\SensitiveParameter] ?string $apiKey,
        ?CredentialProvider $credentialProvider,
        ?string $cacheRetention,
        ?string $workspace,
    ): array {
        return [
            new MessagesModelClient($httpClient, $baseUrl, $region, $apiKey, $credentialProvider, $cacheRetention ?? 'short', $workspace),
            new AnthropicResultConverter(),
            new MessagesModelCatalog(),
            AnthropicContract::create(),
            'bedrock-mantle-messages',
        ];
    }
}
