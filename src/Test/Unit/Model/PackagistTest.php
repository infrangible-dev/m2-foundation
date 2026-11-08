<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Test\Unit\Model;

use Infrangible\Foundation\Model\Packagist;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests the Packagist client with canned HTTP responses.
 */
class PackagistTest extends TestCase
{
    /** @var array<string, array{int, string}> URL => status and body */
    private array $responses = [];

    /**
     * Returns a client whose HTTP requests are answered from $this->responses.
     *
     * @return Packagist
     */
    private function createPackagist(): Packagist
    {
        /** @var CurlFactory&Stub $curlFactory */
        $curlFactory = $this->createStub(CurlFactory::class);

        $curlFactory->method('create')->willReturnCallback(
            function (): Curl {
                $url = '';

                $curl = $this->createStub(Curl::class);
                $curl->method('get')->willReturnCallback(
                    function (string $requestedUrl) use (&$url): void {
                        $url = $requestedUrl;
                    }
                );
                $curl->method('getStatus')->willReturnCallback(
                    function () use (&$url): int {
                        return $this->responses[ $url ][ 0 ] ?? 404;
                    }
                );
                $curl->method('getBody')->willReturnCallback(
                    function () use (&$url): string {
                        return $this->responses[ $url ][ 1 ] ?? '';
                    }
                );

                return $curl;
            }
        );

        return new Packagist($curlFactory, new Json());
    }

    /**
     * The package names are read from the vendor list.
     *
     * @return void
     */
    public function testGetPackageNames(): void
    {
        $this->responses[ 'https://packagist.org/packages/list.json?vendor=infrangible' ] =
            [200, '{"packageNames":["infrangible/m2-core","infrangible/m2-foundation"]}'];

        $this->assertSame(
            ['infrangible/m2-core', 'infrangible/m2-foundation'],
            $this->createPackagist()->getPackageNames()
        );
    }

    /**
     * A response without package names is an error.
     *
     * @return void
     */
    public function testGetPackageNamesWithInvalidResponse(): void
    {
        $this->responses[ 'https://packagist.org/packages/list.json?vendor=infrangible' ] = [200, '{"other":1}'];

        $this->expectException(RuntimeException::class);

        $this->createPackagist()->getPackageNames();
    }

    /**
     * A failed request is an error.
     *
     * @return void
     */
    public function testGetPackageNamesWithHttpError(): void
    {
        $this->responses[ 'https://packagist.org/packages/list.json?vendor=infrangible' ] = [500, 'error'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('failed with status: 500');

        $this->createPackagist()->getPackageNames();
    }

    /**
     * The first release in the p2 metadata is the latest one.
     *
     * @return void
     */
    public function testGetPackage(): void
    {
        $this->responses[ 'https://repo.packagist.org/p2/infrangible/m2-core.json' ] = [
            200,
            '{"packages":{"infrangible/m2-core":[' .
            '{"name":"infrangible/m2-core","description":"Core","version":"4.0.0",' .
            '"time":"2025-10-03T00:56:16+00:00","type":"magento2-module"},' .
            '{"version":"3.14.0","time":"2025-01-01T00:00:00+00:00"}]}}'
        ];

        $this->assertSame(
            [
                'name'        => 'infrangible/m2-core',
                'description' => 'Core',
                'version'     => '4.0.0',
                'time'        => '2025-10-03T00:56:16+00:00'
            ],
            $this->createPackagist()->getPackage('infrangible/m2-core')
        );
    }

    /**
     * A package without any tagged release is an error.
     *
     * @return void
     */
    public function testGetPackageWithoutRelease(): void
    {
        $this->responses[ 'https://repo.packagist.org/p2/infrangible/m2-new.json' ] =
            [200, '{"packages":{"infrangible/m2-new":[]}}'];

        $this->expectException(RuntimeException::class);

        $this->createPackagist()->getPackage('infrangible/m2-new');
    }

    /**
     * Only feed items published after the given time are returned, each package once.
     *
     * @return void
     */
    public function testGetUpdatedPackageNames(): void
    {
        $this->responses[ 'https://packagist.org/feeds/vendor.infrangible.rss' ] = [
            200,
            '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Feed</title>' .
            '<item><title>infrangible/m2-task (2.4.2)</title><pubDate>Fri, 16 Jan 2026 09:57 +0000</pubDate></item>' .
            '<item><title>infrangible/m2-task (2.4.1)</title><pubDate>Thu, 15 Jan 2026 09:57 +0000</pubDate></item>' .
            '<item><title>infrangible/m2-log (2.0.0)</title><pubDate>Fri, 16 Jan 2026 00:59:00 +0000</pubDate></item>' .
            '<item><title>infrangible/m2-old (1.0.0)</title><pubDate>Mon, 01 Dec 2025 00:00:00 +0000</pubDate></item>' .
            '<item><title>not a release</title><pubDate>Fri, 16 Jan 2026 09:57 +0000</pubDate></item>' .
            '</channel></rss>'
        ];

        $this->assertSame(
            ['infrangible/m2-task', 'infrangible/m2-log'],
            $this->createPackagist()->getUpdatedPackageNames((int)strtotime('2026-01-01T00:00:00+00:00'))
        );
    }

    /**
     * A feed that is no valid XML is an error of Packagist, like a failed request.
     *
     * @return void
     */
    public function testGetUpdatedPackageNamesWithInvalidFeed(): void
    {
        $this->responses[ 'https://packagist.org/feeds/vendor.infrangible.rss' ] = [200, 'Service unavailable'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Packagist returned an invalid feed');

        $this->createPackagist()->getUpdatedPackageNames(0);
    }

    /**
     * An empty feed has no updates.
     *
     * @return void
     */
    public function testGetUpdatedPackageNamesWithEmptyFeed(): void
    {
        $this->responses[ 'https://packagist.org/feeds/vendor.infrangible.rss' ] =
            [200, '<?xml version="1.0"?><rss version="2.0"><channel><title>Feed</title></channel></rss>'];

        $this->assertSame([], $this->createPackagist()->getUpdatedPackageNames(0));
    }
}
