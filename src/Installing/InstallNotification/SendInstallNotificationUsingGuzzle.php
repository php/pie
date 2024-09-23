<?php

declare(strict_types=1);

namespace Php\Pie\Installing\InstallNotification;

use Composer\Composer;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Php\Pie\Downloading\DownloadedPackage;
use Php\Pie\Platform\TargetPlatform;

use function array_key_exists;
use function function_exists;
use function is_array;
use function json_decode;
use function json_encode;
use function php_uname;
use function sprintf;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class SendInstallNotificationUsingGuzzle implements InstallNotification
{
    /** @psalm-suppress PossiblyUnusedMethod no direct reference; used in service locator */
    public function __construct(private readonly ClientInterface $client)
    {
    }

    public function send(
        TargetPlatform $targetPlatform,
        DownloadedPackage $package,
    ): void {
        if ($package->package->notificationUrl === null) {
            return;
        }

        $notificationRequest = new Request(
            'POST',
            $package->package->notificationUrl,
            [
                'Content-Type' => 'application/json',
                /**
                 * User agent format is important! If it isn't right, Packagist
                 * silently discards the payload.
                 *
                 * @link https://github.com/composer/packagist/blob/fb75c17d75bc032cc88b997275d40077511d0cd9/src/Controller/ApiController.php#L296
                 * @link https://github.com/composer/packagist/blob/fb75c17d75bc032cc88b997275d40077511d0cd9/src/Util/UserAgentParser.php#L28-L38
                 */
                'User-Agent' => sprintf(
                    'Composer/%s (%s; %s; %s; %s)',
                    Composer::getVersion(),
                    function_exists('php_uname') ? php_uname('s') : 'Unknown',
                    function_exists('php_uname') ? php_uname('r') : 'Unknown',
                    'PHP ' . $targetPlatform->phpBinaryPath->version(),
                    'cURL ' . TargetPlatform::getCurlVersion(),
                ),
            ],
            /**
             * @link https://github.com/composer/packagist/blob/main/src/Controller/ApiController.php#L248
             * @see \Composer\Installer\InstallationManager::notifyInstalls()
             */
            json_encode([
                'downloads' => [
                    [
                        'name' => $package->package->name,
                        'version' => $package->package->notificationVersion,
                        'downloaded' => false,
                    ],
                ],
            ]),
        );

        $notificationResponse = $this->client->send(
            $notificationRequest,
            [RequestOptions::HTTP_ERRORS => false],
        );

        /** @var mixed $responseBody */
        $responseBody = json_decode($notificationResponse->getBody()->__toString(), true);

        if (
            ! is_array($responseBody)
            || ! array_key_exists('status', $responseBody)
            || $responseBody['status'] !== 'success'
        ) {
            throw FailedToSendInstallNotification::fromFailedResponse(
                $package,
                $notificationRequest,
                $notificationResponse,
            );
        }
    }
}
