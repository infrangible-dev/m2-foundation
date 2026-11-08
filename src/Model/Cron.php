<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Model;

use Exception;
use Infrangible\Foundation\Helper\Data;
use Magento\AdminNotification\Model\InboxFactory;
use Magento\Framework\FlagManager;
use Psr\Log\LoggerInterface;

/**
 * Keeps the cached package list current and adds an admin notification for new packages and new releases.
 */
class Cron
{
    public const FLAG_CHECK_TIME = 'infrangible_foundation_check_time';

    /** @var Packagist */
    protected Packagist $packagist;

    /** @var Data */
    protected Data $helper;

    /** @var FlagManager */
    protected FlagManager $flagManager;

    /** @var InboxFactory */
    protected InboxFactory $inboxFactory;

    /** @var LoggerInterface */
    protected LoggerInterface $logging;

    /**
     * @param Packagist $packagist
     * @param Data $helper
     * @param FlagManager $flagManager
     * @param InboxFactory $inboxFactory
     * @param LoggerInterface $logging
     */
    public function __construct(
        Packagist $packagist,
        Data $helper,
        FlagManager $flagManager,
        InboxFactory $inboxFactory,
        LoggerInterface $logging
    ) {
        $this->packagist = $packagist;
        $this->helper = $helper;
        $this->flagManager = $flagManager;
        $this->inboxFactory = $inboxFactory;
        $this->logging = $logging;
    }

    /**
     * Updates the cached packages and adds a notification for every new package or release.
     *
     * @return void
     */
    public function checkUpdate(): void
    {
        $cachedPackages = $this->helper->getCachedPackages();

        // first run: load everything, nothing to notify about
        if ($cachedPackages === null) {
            try {
                $this->helper->refreshPackages();
            } catch (Exception $exception) {
                $this->logging->error($exception);

                return;
            }

            $this->flagManager->saveFlag(
                static::FLAG_CHECK_TIME,
                time()
            );

            return;
        }

        $checkTime = time();
        $lastCheckTime = (int)($this->flagManager->getFlagData(static::FLAG_CHECK_TIME) ?? $checkTime);

        $packages = $cachedPackages;
        $packageNamesToLoad = [];

        // packages that were added to or removed from Packagist
        try {
            $packageNames = $this->packagist->getPackageNames();

            $packageNamesToLoad = array_diff(
                $packageNames,
                array_keys($packages)
            );

            $packages = array_intersect_key(
                $packages,
                array_flip($packageNames)
            );
        } catch (Exception $exception) {
            $this->logging->error($exception);
        }

        // packages with a new release since the last check
        try {
            $packageNamesToLoad = array_merge(
                $packageNamesToLoad,
                $this->packagist->getUpdatedPackageNames($lastCheckTime)
            );
        } catch (Exception $exception) {
            $this->logging->error($exception);
        }

        foreach (array_unique($packageNamesToLoad) as $packageName) {
            try {
                $package = $this->packagist->getPackage($packageName);
            } catch (Exception $exception) {
                $this->logging->error($exception);

                continue;
            }

            $previousTime = isset($cachedPackages[ $packageName ]) ?
                (strtotime($cachedPackages[ $packageName ][ 'time' ]) ?: 0) : 0;

            if ((strtotime($package[ 'time' ]) ?: 0) > $previousTime) {
                $this->addNotice($package);
            }

            $packages[ $packageName ] = $package;
        }

        $this->helper->saveCachedPackages($packages);

        $this->flagManager->saveFlag(
            static::FLAG_CHECK_TIME,
            $checkTime
        );
    }

    /**
     * Adds an admin notification about the package release.
     *
     * @param array $package name, description, version and time of the release
     * @phpstan-param array{name: string, description: string, version: string, time: string} $package
     * @return void
     */
    protected function addNotice(array $package): void
    {
        $this->inboxFactory->create()->addNotice(
            sprintf(
                '%s: %s',
                __('New Infrangible Solution'),
                $package[ 'name' ]
            ),
            sprintf(
                '%s: %s',
                __('New Version'),
                $package[ 'version' ]
            ),
            sprintf(
                'https://packagist.org/packages/%s',
                $package[ 'name' ]
            ),
            false
        );
    }
}
