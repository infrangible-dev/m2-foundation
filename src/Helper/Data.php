<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Helper;

use Exception;
use Infrangible\Foundation\Model\InstalledPackages;
use Infrangible\Foundation\Model\Packagist;
use Magento\Framework\FlagManager;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Caches the Infrangible packages available on Packagist and compares them with the installed packages.
 */
class Data
{
    public const FLAG_AVAILABLE_PACKAGES = 'infrangible_foundation_available_packages';

    public const STATUS_OK = 'ok';

    public const STATUS_OUTDATED = 'outdated';

    public const STATUS_MISSING = 'missing';

    /** order of the statuses in the list: outdated packages first, missing packages last */
    public const STATUS_ORDER = [self::STATUS_OUTDATED => 0, self::STATUS_OK => 1, self::STATUS_MISSING => 2];

    /**
     * @param Packagist $packagist
     * @param InstalledPackages $installedPackages
     * @param FlagManager $flagManager
     * @param LoggerInterface $logging
     */
    public function __construct(
        protected Packagist $packagist,
        protected InstalledPackages $installedPackages,
        protected FlagManager $flagManager,
        protected LoggerInterface $logging
    ) {
    }

    /**
     * Returns the cached packages with installed version and status.
     *
     * Outdated packages come first and missing packages last, each group sorted by release time, newest first.
     *
     * @return array<int, array{name: string, description: string, version: string, time: string, installed: ?string,
     *     status: string}>
     */
    public function getItems(): array
    {
        $installedVersions = $this->installedPackages->getVersions();

        $items = [];

        foreach ($this->getCachedPackages() ?? [] as $packageName => $package) {
            $installedVersion = $installedVersions[ $packageName ] ?? null;

            $package[ 'installed' ] = $installedVersion;
            $package[ 'status' ] = $this->getStatus(
                $installedVersion,
                $package[ 'version' ]
            );

            $items[] = $package;
        }

        usort(
            $items,
            fn(array $item1, array $item2): int =>
                [static::STATUS_ORDER[ $item1[ 'status' ] ], strtotime($item2[ 'time' ]) ?: 0] <=>
                [static::STATUS_ORDER[ $item2[ 'status' ] ], strtotime($item1[ 'time' ]) ?: 0]
        );

        return $items;
    }

    /**
     * Compares the installed with the available version.
     *
     * @param string|null $installedVersion
     * @param string $availableVersion
     * @return string one of the STATUS_* constants
     */
    public function getStatus(?string $installedVersion, string $availableVersion): string
    {
        if ($installedVersion === null || $installedVersion === '') {
            return static::STATUS_MISSING;
        }

        // development installs, for example "dev-master" from a VCS or path repository
        if ($installedVersion === 'dev' || str_starts_with($installedVersion, 'dev-')) {
            return static::STATUS_OK;
        }

        return version_compare(
            ltrim($installedVersion, 'v'),
            ltrim($availableVersion, 'v'),
            '>='
        ) ? static::STATUS_OK : static::STATUS_OUTDATED;
    }

    /**
     * Returns the cached packages or null if they have not been loaded yet.
     *
     * @return array<string, array{name: string, description: string, version: string, time: string}>|null
     */
    public function getCachedPackages(): ?array
    {
        $packages = $this->flagManager->getFlagData(static::FLAG_AVAILABLE_PACKAGES);

        return is_array($packages) ? $packages : null;
    }

    /**
     * Stores the packages in the cache.
     *
     * @param array $packages package name => name, description, version and time of the latest release
     * @phpstan-param array<string, array{name: string, description: string, version: string, time: string}> $packages
     * @return void
     */
    public function saveCachedPackages(array $packages): void
    {
        ksort($packages);

        $this->flagManager->saveFlag(
            static::FLAG_AVAILABLE_PACKAGES,
            $packages
        );
    }

    /**
     * Loads all packages from Packagist and caches them.
     *
     * A package that cannot be loaded keeps its cached data.
     *
     * @return array<string, array{name: string, description: string, version: string, time: string}>
     * @throws RuntimeException if the package list cannot be loaded
     */
    public function refreshPackages(): array
    {
        $cachedPackages = $this->getCachedPackages() ?? [];

        $packages = [];

        foreach ($this->packagist->getPackageNames() as $packageName) {
            try {
                $packages[ $packageName ] = $this->packagist->getPackage($packageName);
            } catch (Exception $exception) {
                $this->logging->error($exception);

                if (array_key_exists($packageName, $cachedPackages)) {
                    $packages[ $packageName ] = $cachedPackages[ $packageName ];
                }
            }
        }

        ksort($packages);

        $this->saveCachedPackages($packages);

        return $packages;
    }
}
