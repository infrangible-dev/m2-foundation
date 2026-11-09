<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Model;

use Composer\InstalledVersions;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Module\FullModuleList;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Determines the installed versions of the Infrangible packages.
 */
class InstalledPackages
{
    /**
     * @param FullModuleList $fullModuleList
     * @param Reader $moduleReader
     * @param File $file
     * @param Json $json
     * @param LoggerInterface $logging
     */
    public function __construct(
        protected FullModuleList $fullModuleList,
        protected Reader $moduleReader,
        protected File $file,
        protected Json $json,
        protected LoggerInterface $logging
    ) {
    }

    /**
     * Returns the installed Infrangible packages.
     *
     * @return array<string, string> package name => installed version
     */
    public function getVersions(): array
    {
        $versions = $this->getComposerVersions();

        // modules installed in app/code are not known to Composer
        foreach ($this->getModuleVersions() as $packageName => $version) {
            if (! array_key_exists($packageName, $versions)) {
                $versions[ $packageName ] = $version;
            }
        }

        return $versions;
    }

    /**
     * Returns the Infrangible packages installed with Composer.
     *
     * @return array<string, string>
     */
    protected function getComposerVersions(): array
    {
        $versions = [];

        foreach (InstalledVersions::getInstalledPackages() as $packageName) {
            if (str_starts_with($packageName, Packagist::VENDOR . '/')) {
                $version = InstalledVersions::getPrettyVersion($packageName);

                if ($version !== null) {
                    $versions[ $packageName ] = $version;
                }
            }
        }

        return $versions;
    }

    /**
     * Returns the Infrangible modules with a composer.json, for example installed in app/code.
     *
     * @return array<string, string>
     */
    protected function getModuleVersions(): array
    {
        $versions = [];

        foreach ($this->fullModuleList->getNames() as $moduleName) {
            if (! str_starts_with($moduleName, 'Infrangible_')) {
                continue;
            }

            $moduleDir = $this->moduleReader->getModuleDir(
                '',
                $moduleName
            );

            // composer.json is either in the module directory or, with a src directory, one level above
            foreach ([$moduleDir, $moduleDir . '/..'] as $directory) {
                $composerFile = $directory . '/composer.json';

                try {
                    if (! $this->file->isExists($composerFile)) {
                        continue;
                    }

                    $composerData = $this->json->unserialize($this->file->fileGetContents($composerFile));
                } catch (\Exception $exception) {
                    $this->logging->error($exception);

                    continue;
                }

                if (is_array($composerData) && isset($composerData[ 'name' ]) && isset($composerData[ 'version' ])) {
                    $versions[ (string)$composerData[ 'name' ] ] = (string)$composerData[ 'version' ];

                    break;
                }
            }
        }

        return $versions;
    }
}
