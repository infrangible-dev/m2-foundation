<?php

declare(strict_types=1);

namespace Infrangible\Foundation\Helper;

use Exception;
use FeWeDev\Base\Arrays;
use FeWeDev\Base\Json;
use FeWeDev\Base\Variables;
use FeWeDev\Xml\SimpleXml;
use Magento\Framework\FlagManager;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Module\FullModuleList;
use Psr\Log\LoggerInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Data
{
    /** @var Arrays */
    protected $arrays;

    /** @var Variables */
    protected $variables;

    /** @var SimpleXml */
    protected $simpleXml;

    /** @var LoggerInterface */
    protected $logging;

    /** @var FullModuleList */
    protected $fullModuleList;

    /** @var Reader */
    protected $moduleReader;

    /** @var Json */
    protected $json;

    /** @var FlagManager */
    protected $flagManager;

    public function __construct(
        Arrays $arrays,
        Variables $variables,
        SimpleXml $simpleXml,
        LoggerInterface $logging,
        FullModuleList $fullModuleList,
        Reader $moduleReader,
        Json $json,
        FlagManager $flagManager
    ) {
        $this->arrays = $arrays;
        $this->variables = $variables;
        $this->simpleXml = $simpleXml;
        $this->logging = $logging;
        $this->fullModuleList = $fullModuleList;
        $this->moduleReader = $moduleReader;
        $this->json = $json;
        $this->flagManager = $flagManager;
    }

    public function getItems(): array
    {
        $items = $this->getAvailablePackages();

        $installedInfrangiblePackageVersions = $this->getInstalledInfrangiblePackageVersions();

        foreach ($items as &$item) {
            $name = $this->arrays->getValue(
                $item,
                'name'
            );

            $installedVersion = $this->arrays->getValue(
                $installedInfrangiblePackageVersions,
                $name
            );
            $version = $this->arrays->getValue(
                $item,
                'version'
            );

            $item[ 'installed' ] = $installedVersion;
            $item[ 'status' ] = $this->variables->isEmpty($installedVersion) ? 'missing' :
                ($installedVersion === 'dev' || version_compare(
                    $installedVersion,
                    $version
                ) >= 0 ? 'ok' : 'outdated');
        }

        usort(
            $items,
            function (array $package1, array $package2) {
                $status1 = $this->arrays->getValue(
                    $package1,
                    'status'
                );
                $status2 = $this->arrays->getValue(
                    $package2,
                    'status'
                );

                if ($status1 === 'missing' && $status2 !== 'missing') {
                    return 1;
                }
                if ($status1 !== 'missing' && $status2 === 'missing') {
                    return -1;
                }

                if ($status1 === 'outdated' && $status2 !== 'outdated') {
                    return -1;
                }
                if ($status1 !== 'outdated' && $status2 === 'outdated') {
                    return 1;
                }

                $time1 = strtotime(
                    $this->arrays->getValue(
                        $package1,
                        'time'
                    )
                );
                $time2 = strtotime(
                    $this->arrays->getValue(
                        $package2,
                        'time'
                    )
                );

                return $time2 <=> $time1;
            }
        );

        return $items;
    }

    public function getAvailablePackages(): array
    {
        $availablePackages = $this->flagManager->getFlagData('infrangible_foundation_available_packages');

        if ($availablePackages !== null) {
            return $availablePackages;
        }

        $apiClient = new \PestJSON('https://packagist.org');

        $availablePackages = [];

        try {
            /** @var array $apiResult */
            $apiResult = $apiClient->get('packages/list.json?vendor=infrangible');

            $packageNames = $this->arrays->getValue(
                $apiResult,
                'packageNames'
            );

            foreach ($packageNames as $packageName) {
                $availablePackages[ $packageName ] = $this->getAvailablePackage($packageName);
            }
        } catch (Exception $exception) {
            $this->logging->error($exception);
        }

        ksort($availablePackages);

        $this->flagManager->saveFlag(
            'infrangible_foundation_available_packages',
            $availablePackages
        );

        return $availablePackages;
    }

    public function getAvailablePackage(string $packageName): array
    {
        $apiClient = new \PestJSON('https://packagist.org');

        /** @var array $apiResult */
        $apiResult = $apiClient->get(
            sprintf(
                'packages/%s.json',
                $packageName
            )
        );

        $versions = $this->arrays->getValue(
            $apiResult,
            'package:versions'
        );

        usort(
            $versions,
            function (array $version1, array $version2) {
                return version_compare(
                    $version1[ 'version' ],
                    $version2[ 'version' ]
                );
            }
        );

        $version = end($versions);

        return [
            'name'        => $this->arrays->getValue(
                $version,
                'name'
            ),
            'description' => $this->arrays->getValue(
                $version,
                'description'
            ),
            'version'     => $this->arrays->getValue(
                $version,
                'version'
            ),
            'time'        => $this->arrays->getValue(
                $version,
                'time'
            )
        ];
    }

    /**
     * @return string[]
     */
    public function getInstalledInfrangiblePackageVersions(): array
    {
        $composerData = [];

        foreach ($this->fullModuleList->getNames() as $moduleName) {
            if (preg_match(
                '/^Infrangible_/',
                $moduleName
            )) {
                $moduleDir = $this->moduleReader->getModuleDir(
                    Dir::MODULE_ETC_DIR,
                    $moduleName
                );

                $composerFile = sprintf(
                    '%s/../composer.json',
                    $moduleDir
                );

                $versionConfiguration = [];

                if (file_exists($composerFile)) {
                    $versionConfiguration = $this->json->decode(file_get_contents($composerFile));
                } else {
                    $composerFile = sprintf(
                        '%s/../../composer.json',
                        $moduleDir
                    );

                    if (file_exists($composerFile)) {
                        $versionConfiguration = $this->json->decode(file_get_contents($composerFile));
                    } else {
                        if (strpos(
                                $moduleDir,
                                'app/code'
                            ) !== false) {
                            $versionConfiguration[ 'version' ] = 'dev';
                        }
                    }
                }

                $moduleVersion = $this->arrays->getValue(
                    $versionConfiguration,
                    'version'
                );

                [, $packageName] = explode(
                    '_',
                    $moduleName,
                    2
                );

                $packageName = lcfirst($packageName);
                $packageName = strtolower(
                    trim(
                        preg_replace(
                            '/([A-Z]|[0-9]+)/',
                            '-$1',
                            $packageName
                        ),
                        '-'
                    )
                );

                $composerData[ sprintf(
                    'infrangible/m2-%s',
                    $packageName
                ) ] = $moduleVersion;
            }
        }

        return $composerData;
    }

    /**
     * @return string[]
     */
    public function getUpdatedPackages(int $lastCheckTime): array
    {
        $result = [];

        try {
            $rssClient = new \Pest('https://packagist.org');

            $url = 'feeds/vendor.infrangible.rss';

            $feedResult = $this->simpleXml->simpleXmlLoadString($rssClient->get($url));

            $parsedResult = json_decode(
                json_encode($feedResult),
                true
            );

            $items = $this->arrays->getValue(
                $parsedResult,
                'channel:item'
            );

            if ($this->arrays->isAssociative($items)) {
                $items = [$items];
            }

            foreach ($items as $item) {
                $title = $this->arrays->getValue(
                    $item,
                    'title'
                );

                if (! $this->variables->isEmpty($title) && preg_match(
                        '/(.*?)\s+\((.*?)\)/',
                        $title,
                        $matches
                    )) {

                    $pubDate = strtotime(
                        trim(
                            $this->arrays->getValue(
                                $item,
                                'pubDate'
                            )
                        )
                    );

                    if ($pubDate > $lastCheckTime) {
                        $result[] = $matches[ 1 ];
                    }
                }
            }
        } catch (Exception $exception) {
            $this->logging->error($exception);
        }

        return $result;
    }
}
