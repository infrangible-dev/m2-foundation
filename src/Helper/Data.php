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
use Pest;
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

    /**
     * @param Arrays          $arrays
     * @param Variables       $variables
     * @param SimpleXml       $simpleXml
     * @param LoggerInterface $logging
     * @param FullModuleList  $fullModuleList
     * @param Reader          $moduleReader
     * @param Json            $json
     * @param FlagManager     $flagManager
     */
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

    /**
     * @return array[]
     */
    public function getLatestItems(): array
    {
        $result = [];

        try {
            $rssClient = new Pest('https://packagist.org');

            $url = 'feeds/vendor.infrangible.rss';

            $feedResult = $this->simpleXml->simpleXmlLoadString($rssClient->get($url));

            $parsedResult = json_decode(json_encode($feedResult), true);

            $items = $this->arrays->getValue($parsedResult, 'channel:item');

            if ($this->arrays->isAssociative($items)) {
                $items = [$items];
            }

            foreach ($items as $item) {
                $title = $this->arrays->getValue($item, 'title');

                if (!$this->variables->isEmpty($title) && preg_match('/(.*?)\s+\((.*?)\)/', $title, $matches)) {
                    $item['name'] = $matches[1];
                    $item['version'] = $matches[2];
                }

                $guid = $this->arrays->getValue($item, 'guid');

                $result[$guid] = $item;
            }
        } catch (Exception $exception) {
            $this->logging->error($exception);
        }

        return $result;
    }

    public function getItems(): array
    {
        $items = [];

        $packageVersions = $this->flagManager->getFlagData('infrangible_foundation_package_versions');
        $packageVersions = $this->variables->isEmpty($packageVersions) ? $this->getLatestItems() : $packageVersions;

        foreach ($packageVersions as $item) {
            $name = $this->arrays->getValue($item, 'name');
            $version = $this->arrays->getValue($item, 'version');

            if ($this->variables->isEmpty($name) || $this->variables->isEmpty($version)) {
                continue;
            }

            if (!array_key_exists($name, $items)) {
                $items[$name] = $item;
            } else {
                if (true === version_compare(
                        $version,
                        $this->arrays->getValue($items, sprintf('%s:version', $name)),
                        '>'
                    )) {

                    $items[$name] = $item;
                }
            }
        }

        $installedInfrangiblePackageVersions = $this->getInstalledInfrangiblePackageVersions();

        foreach ($items as &$item) {
            $name = $this->arrays->getValue($item, 'name');

            $installedVersion = $this->arrays->getValue($installedInfrangiblePackageVersions, $name);
            $version = $this->arrays->getValue($item, 'version');

            $item['installed'] = $installedVersion;
            $item['status'] = $this->variables->isEmpty($installedVersion) ? 'missing' :
                (version_compare($installedVersion, $version) >= 0 ? 'ok' : 'outdated');
        }

        return $items;
    }

    /**
     * @return string[]
     */
    public function getInstalledInfrangiblePackageVersions(): array
    {
        $composerData = [];

        foreach ($this->fullModuleList->getNames() as $moduleName) {
            if (preg_match('/^Infrangible_/', $moduleName)) {
                $composerFile =
                    sprintf('%s/../composer.json', $this->moduleReader->getModuleDir(Dir::MODULE_ETC_DIR, $moduleName));

                $versionConfiguration = [];

                if (file_exists($composerFile)) {
                    $versionConfiguration = $this->json->decode(file_get_contents($composerFile));
                } else {
                    $composerFile = sprintf(
                        '%s/../../composer.json',
                        $this->moduleReader->getModuleDir(Dir::MODULE_ETC_DIR, $moduleName)
                    );

                    if (file_exists($composerFile)) {
                        $versionConfiguration = $this->json->decode(file_get_contents($composerFile));
                    }
                }

                $moduleVersion = $this->arrays->getValue($versionConfiguration, 'version');

                [, $packageName] = explode('_', $moduleName, 2);

                $packageName = lcfirst($packageName);
                $packageName = strtolower(trim(preg_replace('/([A-Z]|[0-9]+)/', '-$1', $packageName), '-'));

                $composerData[sprintf('infrangible/m2-%s', $packageName)] = $moduleVersion;
            }
        }

        return $composerData;
    }
}
