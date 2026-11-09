<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Model;

use Exception;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use RuntimeException;
use SimpleXMLElement;

/**
 * Client for the public Packagist APIs used to list the Infrangible packages.
 */
class Packagist
{
    public const VENDOR = 'infrangible';

    public const LIST_URL = 'https://packagist.org/packages/list.json?vendor=%s';

    public const PACKAGE_URL = 'https://repo.packagist.org/p2/%s.json';

    public const FEED_URL = 'https://packagist.org/feeds/vendor.%s.rss';

    public const TIMEOUT = 10;

    /**
     * @param CurlFactory $curlFactory
     * @param Json $json
     */
    public function __construct(
        protected CurlFactory $curlFactory,
        protected Json $json
    ) {
    }

    /**
     * Returns the names of all packages of the vendor.
     *
     * @return string[]
     * @throws RuntimeException
     */
    public function getPackageNames(): array
    {
        $result = $this->json->unserialize(
            $this->request(
                sprintf(
                    static::LIST_URL,
                    static::VENDOR
                )
            )
        );

        $packageNames = is_array($result) && isset($result[ 'packageNames' ]) ? $result[ 'packageNames' ] : null;

        if (! is_array($packageNames)) {
            throw new RuntimeException('Packagist returned no package names');
        }

        return array_values(array_filter($packageNames, 'is_string'));
    }

    /**
     * Returns name, description, version and time of the latest tagged release of the package.
     *
     * @param string $packageName
     * @return array{name: string, description: string, version: string, time: string}
     * @throws RuntimeException
     */
    public function getPackage(string $packageName): array
    {
        $result = $this->json->unserialize(
            $this->request(
                sprintf(
                    static::PACKAGE_URL,
                    $packageName
                )
            )
        );

        // the p2 metadata lists the tagged releases, newest first
        $latestVersion = is_array($result) && isset($result[ 'packages' ][ $packageName ][ 0 ]) ?
            $result[ 'packages' ][ $packageName ][ 0 ] : null;

        if (! is_array($latestVersion)) {
            throw new RuntimeException(
                sprintf(
                    'Packagist returned no release of package: %s',
                    $packageName
                )
            );
        }

        return [
            'name'        => (string)($latestVersion[ 'name' ] ?? $packageName),
            'description' => (string)($latestVersion[ 'description' ] ?? ''),
            'version'     => (string)($latestVersion[ 'version' ] ?? ''),
            'time'        => (string)($latestVersion[ 'time' ] ?? '')
        ];
    }

    /**
     * Returns the names of the packages released after the given time according to the vendor feed.
     *
     * @param int $sinceTime
     * @return string[]
     * @throws RuntimeException
     */
    public function getUpdatedPackageNames(int $sinceTime): array
    {
        $content = $this->request(
            sprintf(
                static::FEED_URL,
                static::VENDOR
            )
        );

        try {
            $feed = new SimpleXMLElement(
                $content,
                LIBXML_NONET
            );
        } catch (Exception $exception) {
            throw new RuntimeException(
                'Packagist returned an invalid feed',
                0,
                $exception
            );
        }

        $packageNames = [];

        foreach ($feed->channel->item as $item) {
            // titles look like: "infrangible/m2-core (4.0.0)"
            if (preg_match(
                '/^\s*(\S+)\s+\(.*?\)/',
                (string)$item->title,
                $matches
            )) {
                $pubDate = strtotime(trim((string)$item->pubDate));

                if ($pubDate !== false && $pubDate > $sinceTime) {
                    $packageNames[] = $matches[ 1 ];
                }
            }
        }

        return array_values(array_unique($packageNames));
    }

    /**
     * Returns the body of a successful GET request.
     *
     * @param string $url
     * @return string
     * @throws RuntimeException
     */
    protected function request(string $url): string
    {
        $curl = $this->curlFactory->create();

        $curl->setTimeout(static::TIMEOUT);
        $curl->get($url);

        $status = $curl->getStatus();

        if ($status !== 200) {
            throw new RuntimeException(
                sprintf(
                    'Request to %s failed with status: %d',
                    $url,
                    $status
                )
            );
        }

        return $curl->getBody();
    }
}
