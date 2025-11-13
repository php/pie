<?php

declare(strict_types=1);

namespace Php\Pie\Util;

use Composer\Util\AuthHelper;
use Webmozart\Assert\Assert;

use function array_key_first;
use function count;
use function is_array;
use function is_string;
use function parse_url;

class PieComposerAuthHelper
{
    public function __construct(private readonly AuthHelper $authHelper)
    {
    }

    /** @return non-empty-string|null */
    public function authHeader(string $baseUrl, string $url): string|null
    {
        $urlParts = parse_url($baseUrl);
        Assert::isArray($urlParts);
        Assert::keyExists($urlParts, 'host');
        Assert::stringNotEmpty($urlParts['host']);

        $authOptions = $this->authHelper->addAuthenticationOptions([], $urlParts['host'], $url);

        Assert::keyExists($authOptions, 'http');
        Assert::isArray($authOptions['http']);
        Assert::keyExists($authOptions['http'], 'header');

        $authHeaders = $authOptions['http']['header'];

        if (! is_array($authHeaders) || count($authHeaders) !== 1) {
            return null;
        }

        $authHeader = $authHeaders[array_key_first($authHeaders)];

        return is_string($authHeader) && $authHeader !== '' ? $authHeader : null;
    }
}
