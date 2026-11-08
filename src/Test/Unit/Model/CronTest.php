<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Test\Unit\Model;

use Infrangible\Foundation\Helper\Data;
use Infrangible\Foundation\Model\Cron;
use Infrangible\Foundation\Model\InstalledPackages;
use Infrangible\Foundation\Model\Packagist;
use Magento\AdminNotification\Model\Inbox;
use Magento\AdminNotification\Model\InboxFactory;
use Magento\Framework\FlagManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests which packages the cron job loads and when it adds a notification.
 */
#[AllowMockObjectsWithoutExpectations]
class CronTest extends TestCase
{
    /** time of the simulated earlier run of the cron job */
    private const PREVIOUS_CHECK_TIME = 1000;

    /** @var array<string, mixed> */
    private array $flags = [];

    /** @var array<string, array{name: string, description: string, version: string, time: string}> */
    private array $packagistPackages = [];

    /** @var string[]|null null: the package list cannot be loaded */
    private ?array $packagistPackageNames = [];

    /** @var string[]|null null: the feed cannot be loaded */
    private ?array $feedPackageNames = [];

    /** @var string[] */
    private array $loadedPackageNames = [];

    /** @var string[] titles of the added notifications */
    private array $notices = [];

    /** @var Cron */
    private Cron $cron;

    /** @var Data */
    private Data $helper;

    /** @var LoggerInterface&MockObject */
    private MockObject $logging;

    /**
     * Creates the cron job with a fake Packagist, an in-memory flag storage and a recording notification inbox.
     *
     * @return void
     */
    protected function setUp(): void
    {
        /** @var Packagist&Stub $packagist */
        $packagist = $this->createStub(Packagist::class);
        $packagist->method('getPackageNames')->willReturnCallback(
            function (): array {
                if ($this->packagistPackageNames === null) {
                    throw new RuntimeException('list failed');
                }

                return $this->packagistPackageNames;
            }
        );
        $packagist->method('getUpdatedPackageNames')->willReturnCallback(
            function (): array {
                if ($this->feedPackageNames === null) {
                    throw new RuntimeException('feed failed');
                }

                return $this->feedPackageNames;
            }
        );
        $packagist->method('getPackage')->willReturnCallback(
            function (string $packageName): array {
                $this->loadedPackageNames[] = $packageName;

                if (! isset($this->packagistPackages[ $packageName ])) {
                    throw new RuntimeException('package failed');
                }

                return $this->packagistPackages[ $packageName ];
            }
        );

        /** @var FlagManager&Stub $flagManager */
        $flagManager = $this->createStub(FlagManager::class);
        $flagManager->method('getFlagData')->willReturnCallback(
            function (string $code) {
                return $this->flags[ $code ] ?? null;
            }
        );
        $flagManager->method('saveFlag')->willReturnCallback(
            function (string $code, $value): bool {
                $this->flags[ $code ] = $value;

                return true;
            }
        );

        /** @var Inbox&Stub $inbox */
        $inbox = $this->createStub(Inbox::class);
        $inbox->method('addNotice')->willReturnCallback(
            function (string $title) use ($inbox): Inbox {
                $this->notices[] = $title;

                return $inbox;
            }
        );

        /** @var InboxFactory&Stub $inboxFactory */
        $inboxFactory = $this->createStub(InboxFactory::class);
        $inboxFactory->method('create')->willReturn($inbox);

        $this->logging = $this->createMock(LoggerInterface::class);
        $logging = $this->logging;

        $this->helper = new Data(
            $packagist,
            $this->createStub(InstalledPackages::class),
            $flagManager,
            $logging
        );

        $this->cron = new Cron(
            $packagist,
            $this->helper,
            $flagManager,
            $inboxFactory,
            $logging
        );
    }

    /**
     * Adds a package to the fake Packagist.
     *
     * @param string $name
     * @param string $version
     * @param string $time
     * @return void
     */
    private function publish(string $name, string $version, string $time): void
    {
        $this->packagistPackages[ $name ] = [
            'name'        => $name,
            'description' => $name,
            'version'     => $version,
            'time'        => $time
        ];

        if (is_array($this->packagistPackageNames) && ! in_array($name, $this->packagistPackageNames, true)) {
            $this->packagistPackageNames[] = $name;
        }
    }

    /**
     * Simulates an earlier run: the current Packagist state is cached and was checked at PREVIOUS_CHECK_TIME.
     *
     * @return void
     */
    private function cacheCurrentState(): void
    {
        $this->helper->saveCachedPackages($this->packagistPackages);
        $this->flags[ Cron::FLAG_CHECK_TIME ] = self::PREVIOUS_CHECK_TIME;
        $this->loadedPackageNames = [];
    }

    /**
     * The first run loads all packages and does not add notifications.
     *
     * @return void
     */
    public function testFirstRunLoadsEverythingWithoutNotices(): void
    {
        $this->publish('infrangible/m2-core', '4.0.0', '2025-10-03T00:00:00+00:00');
        $this->publish('infrangible/m2-log', '2.0.0', '2026-01-16T00:00:00+00:00');

        $this->cron->checkUpdate();

        $this->assertSame(
            ['infrangible/m2-core', 'infrangible/m2-log'],
            array_keys($this->helper->getCachedPackages() ?? [])
        );
        $this->assertIsInt($this->flags[ Cron::FLAG_CHECK_TIME ] ?? null);
        $this->assertSame([], $this->notices);
    }

    /**
     * If the first run cannot load the package list, nothing is stored.
     *
     * @return void
     */
    public function testFirstRunWithoutPackageList(): void
    {
        $this->packagistPackageNames = null;

        $this->cron->checkUpdate();

        $this->assertNull($this->helper->getCachedPackages());
        $this->assertArrayNotHasKey(Cron::FLAG_CHECK_TIME, $this->flags);
    }

    /**
     * Without changes on Packagist no package is loaded and no notification added.
     *
     * @return void
     */
    public function testNothingChanged(): void
    {
        $this->publish('infrangible/m2-core', '4.0.0', '2025-10-03T00:00:00+00:00');
        $this->cacheCurrentState();

        $this->cron->checkUpdate();

        $this->assertSame([], $this->loadedPackageNames);
        $this->assertSame([], $this->notices);
        $this->assertGreaterThan(self::PREVIOUS_CHECK_TIME, $this->flags[ Cron::FLAG_CHECK_TIME ]);
    }

    /**
     * A package that appears in the package list is loaded and announced.
     *
     * @return void
     */
    public function testNewPackage(): void
    {
        $this->publish('infrangible/m2-core', '4.0.0', '2025-10-03T00:00:00+00:00');
        $this->cacheCurrentState();
        $this->publish('infrangible/m2-new', '1.0.0', '2026-11-01T00:00:00+00:00');

        $this->cron->checkUpdate();

        $this->assertSame(['infrangible/m2-new'], $this->loadedPackageNames);
        $this->assertSame(['New Infrangible Solution: infrangible/m2-new'], $this->notices);
        $this->assertArrayHasKey('infrangible/m2-new', $this->helper->getCachedPackages() ?? []);
    }

    /**
     * A package that is no longer listed is removed from the cache.
     *
     * @return void
     */
    public function testRemovedPackage(): void
    {
        $this->publish('infrangible/m2-core', '4.0.0', '2025-10-03T00:00:00+00:00');
        $this->publish('infrangible/m2-gone', '1.0.0', '2024-01-01T00:00:00+00:00');
        $this->cacheCurrentState();
        $this->packagistPackageNames = ['infrangible/m2-core'];

        $this->cron->checkUpdate();

        $this->assertSame(['infrangible/m2-core'], array_keys($this->helper->getCachedPackages() ?? []));
        $this->assertSame([], $this->notices);
    }

    /**
     * A package in the feed with a newer release is announced, one with an unchanged release is not.
     *
     * @return void
     */
    public function testUpdatedPackages(): void
    {
        $this->publish('infrangible/m2-task', '2.4.1', '2025-12-01T00:00:00+00:00');
        $this->publish('infrangible/m2-log', '2.0.0', '2026-01-16T00:00:00+00:00');
        $this->cacheCurrentState();
        $this->publish('infrangible/m2-task', '2.4.2', '2026-01-16T09:57:00+00:00');
        $this->feedPackageNames = ['infrangible/m2-task', 'infrangible/m2-log'];

        $this->cron->checkUpdate();

        $this->assertSame(['infrangible/m2-task', 'infrangible/m2-log'], $this->loadedPackageNames);
        $this->assertSame(['New Infrangible Solution: infrangible/m2-task'], $this->notices);
        $this->assertSame('2.4.2', ($this->helper->getCachedPackages() ?? [])[ 'infrangible/m2-task' ][ 'version' ]);
    }

    /**
     * A package that cannot be loaded is skipped without stopping the other packages.
     *
     * @return void
     */
    public function testFailingPackageDoesNotStopTheRun(): void
    {
        $this->publish('infrangible/m2-core', '4.0.0', '2025-10-03T00:00:00+00:00');
        $this->cacheCurrentState();
        $this->packagistPackageNames[] = 'infrangible/m2-broken';
        $this->publish('infrangible/m2-new', '1.0.0', '2026-11-01T00:00:00+00:00');

        $this->cron->checkUpdate();

        $this->assertSame(['New Infrangible Solution: infrangible/m2-new'], $this->notices);
        $this->assertSame(
            ['infrangible/m2-core', 'infrangible/m2-new'],
            array_keys($this->helper->getCachedPackages() ?? [])
        );
    }

    /**
     * If the package list cannot be loaded, the feed is still checked and the cached packages are kept.
     *
     * @return void
     */
    public function testFeedIsCheckedWithoutPackageList(): void
    {
        $this->publish('infrangible/m2-task', '2.4.1', '2025-12-01T00:00:00+00:00');
        $this->cacheCurrentState();
        $this->publish('infrangible/m2-task', '2.4.2', '2026-01-16T09:57:00+00:00');
        $this->packagistPackageNames = null;
        $this->feedPackageNames = ['infrangible/m2-task'];

        $this->cron->checkUpdate();

        $this->assertSame(['New Infrangible Solution: infrangible/m2-task'], $this->notices);
        $this->assertSame(['infrangible/m2-task'], array_keys($this->helper->getCachedPackages() ?? []));
    }

    /**
     * If the feed cannot be loaded, new packages from the package list are still loaded and the run is completed.
     *
     * @return void
     */
    public function testPackageListIsCheckedWithoutFeed(): void
    {
        $this->publish('infrangible/m2-core', '4.0.0', '2025-10-03T00:00:00+00:00');
        $this->cacheCurrentState();
        $this->publish('infrangible/m2-new', '1.0.0', '2026-11-01T00:00:00+00:00');
        $this->feedPackageNames = null;
        $this->logging->expects($this->once())->method('error');

        $this->cron->checkUpdate();

        $this->assertSame(['New Infrangible Solution: infrangible/m2-new'], $this->notices);
        $this->assertGreaterThan(self::PREVIOUS_CHECK_TIME, $this->flags[ Cron::FLAG_CHECK_TIME ]);
    }
}
