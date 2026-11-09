<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Test\Unit\Helper;

use Infrangible\Foundation\Helper\Data;
use Infrangible\Foundation\Model\InstalledPackages;
use Infrangible\Foundation\Model\Packagist;
use Magento\Framework\FlagManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests the package cache, the status calculation and the sorting of the package list.
 */
#[AllowMockObjectsWithoutExpectations]
class DataTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $flags = [];

    /** @var Packagist&Stub */
    private Stub $packagist;

    /** @var InstalledPackages&Stub */
    private Stub $installedPackages;

    /** @var LoggerInterface&MockObject */
    private MockObject $logging;

    /** @var Data */
    private Data $helper;

    /**
     * Creates the helper with an in-memory flag storage.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->packagist = $this->createStub(Packagist::class);
        $this->installedPackages = $this->createStub(InstalledPackages::class);
        $this->logging = $this->createMock(LoggerInterface::class);

        $this->helper = new Data(
            $this->packagist,
            $this->installedPackages,
            $this->createFlagManager(),
            $this->logging
        );
    }

    /**
     * Returns a flag manager that keeps the flags in $this->flags.
     *
     * @return FlagManager&Stub
     */
    private function createFlagManager(): FlagManager
    {
        $flagManager = $this->createStub(FlagManager::class);

        $flagManager->method('getFlagData')->willReturnCallback(
            fn(string $code) => $this->flags[ $code ] ?? null
        );
        $flagManager->method('saveFlag')->willReturnCallback(
            function (string $code, $value): bool {
                $this->flags[ $code ] = $value;

                return true;
            }
        );

        return $flagManager;
    }

    /**
     * Returns package data as delivered by Packagist.
     *
     * @param string $name
     * @param string $version
     * @param string $time
     * @return array{name: string, description: string, version: string, time: string}
     */
    private static function package(string $name, string $version, string $time): array
    {
        return ['name' => $name, 'description' => $name, 'version' => $version, 'time' => $time];
    }

    /**
     * Provides installed version, available version and expected status.
     *
     * @return array<string, array{?string, string, string}>
     */
    public static function statusProvider(): array
    {
        return [
            'not installed'      => [null, '1.0.0', Data::STATUS_MISSING],
            'empty version'      => ['', '1.0.0', Data::STATUS_MISSING],
            'same version'       => ['1.2.0', '1.2.0', Data::STATUS_OK],
            'newer installed'    => ['1.10.0', '1.9.0', Data::STATUS_OK],
            'older installed'    => ['1.1.9', '1.2.0', Data::STATUS_OUTDATED],
            'v prefix'           => ['v1.2.0', '1.2.0', Data::STATUS_OK],
            'dev branch'         => ['dev-master', '2.0.0', Data::STATUS_OK],
            'dev'                => ['dev', '2.0.0', Data::STATUS_OK],
            'older major'        => ['2.9.9', '3.0.0', Data::STATUS_OUTDATED]
        ];
    }

    /**
     * @dataProvider statusProvider
     *
     * @param string|null $installedVersion
     * @param string $availableVersion
     * @param string $expectedStatus
     * @return void
     */
    #[DataProvider('statusProvider')]
    public function testGetStatus(?string $installedVersion, string $availableVersion, string $expectedStatus): void
    {
        $this->assertSame($expectedStatus, $this->helper->getStatus($installedVersion, $availableVersion));
    }

    /**
     * Without a cache there are no packages and no items.
     *
     * @return void
     */
    public function testNothingCached(): void
    {
        $this->assertNull($this->helper->getCachedPackages());
        $this->assertSame([], $this->helper->getItems());
    }

    /**
     * A cache that does not contain an array counts as not loaded.
     *
     * @return void
     */
    public function testInvalidCache(): void
    {
        $this->flags[ Data::FLAG_AVAILABLE_PACKAGES ] = 'invalid';

        $this->assertNull($this->helper->getCachedPackages());
    }

    /**
     * The cache is stored sorted by package name.
     *
     * @return void
     */
    public function testSaveCachedPackagesSortsByName(): void
    {
        $this->helper->saveCachedPackages(
            [
                'infrangible/m2-b' => self::package('infrangible/m2-b', '1.0.0', '2025-01-01'),
                'infrangible/m2-a' => self::package('infrangible/m2-a', '1.0.0', '2025-01-01')
            ]
        );

        $this->assertSame(
            ['infrangible/m2-a', 'infrangible/m2-b'],
            array_keys($this->helper->getCachedPackages() ?? [])
        );
    }

    /**
     * Items get the installed version and status; outdated come first, missing last, newest first within a group.
     *
     * @return void
     */
    public function testGetItems(): void
    {
        $this->helper->saveCachedPackages(
            [
                'infrangible/m2-missing-old' => self::package('infrangible/m2-missing-old', '1.0.0', '2024-01-01'),
                'infrangible/m2-missing-new' => self::package('infrangible/m2-missing-new', '1.0.0', '2026-01-01'),
                'infrangible/m2-ok'          => self::package('infrangible/m2-ok', '2.0.0', '2025-01-01'),
                'infrangible/m2-outdated'    => self::package('infrangible/m2-outdated', '3.0.0', '2023-01-01')
            ]
        );

        $this->installedPackages->method('getVersions')->willReturn(
            ['infrangible/m2-ok' => '2.0.0', 'infrangible/m2-outdated' => '2.5.0']
        );

        $items = $this->helper->getItems();

        $this->assertSame(
            [
                'infrangible/m2-outdated',
                'infrangible/m2-ok',
                'infrangible/m2-missing-new',
                'infrangible/m2-missing-old'
            ],
            array_column($items, 'name')
        );
        $this->assertSame(
            [Data::STATUS_OUTDATED, Data::STATUS_OK, Data::STATUS_MISSING, Data::STATUS_MISSING],
            array_column($items, 'status')
        );
        $this->assertSame(['2.5.0', '2.0.0', null, null], array_column($items, 'installed'));
    }

    /**
     * Refreshing loads every package; a package that fails keeps its cached data, an uncached one is skipped.
     *
     * @return void
     */
    public function testRefreshPackages(): void
    {
        $this->helper->saveCachedPackages(
            ['infrangible/m2-failing' => self::package('infrangible/m2-failing', '1.0.0', '2024-01-01')]
        );

        $this->packagist->method('getPackageNames')->willReturn(
            ['infrangible/m2-ok', 'infrangible/m2-failing', 'infrangible/m2-failing-new']
        );
        $this->packagist->method('getPackage')->willReturnCallback(
            function (string $packageName): array {
                if ($packageName === 'infrangible/m2-ok') {
                    return self::package($packageName, '2.0.0', '2025-01-01');
                }

                throw new RuntimeException('failed');
            }
        );
        $this->logging->expects($this->exactly(2))->method('error');

        $packages = $this->helper->refreshPackages();

        $this->assertSame(['infrangible/m2-failing', 'infrangible/m2-ok'], array_keys($packages));
        $this->assertSame('1.0.0', $packages[ 'infrangible/m2-failing' ][ 'version' ]);
        $this->assertSame($packages, $this->helper->getCachedPackages());
    }

    /**
     * If the package list cannot be loaded, the error is passed on and the cache stays untouched.
     *
     * @return void
     */
    public function testRefreshPackagesWithoutPackageList(): void
    {
        $this->packagist->method('getPackageNames')->willThrowException(new RuntimeException('failed'));

        try {
            $this->helper->refreshPackages();

            $this->fail('Exception expected');
        } catch (RuntimeException $exception) {
            $this->assertSame('failed', $exception->getMessage());
        }

        $this->assertNull($this->helper->getCachedPackages());
    }
}
