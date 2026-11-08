<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Model;

use FeWeDev\Base\Variables;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\State;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Collects information about the installation that is sent with a contact request.
 */
class SystemInformation
{
    /** @var ProductMetadataInterface */
    protected ProductMetadataInterface $productMetadata;

    /** @var State */
    protected State $appState;

    /** @var StoreManagerInterface */
    protected StoreManagerInterface $storeManager;

    /** @var ScopeConfigInterface */
    protected ScopeConfigInterface $scopeConfig;

    /** @var InstalledPackages */
    protected InstalledPackages $installedPackages;

    /** @var Variables */
    protected Variables $variables;

    /**
     * @param ProductMetadataInterface $productMetadata
     * @param State $appState
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param InstalledPackages $installedPackages
     * @param Variables $variables
     */
    public function __construct(
        ProductMetadataInterface $productMetadata,
        State $appState,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        InstalledPackages $installedPackages,
        Variables $variables
    ) {
        $this->productMetadata = $productMetadata;
        $this->appState = $appState;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->installedPackages = $installedPackages;
        $this->variables = $variables;
    }

    /**
     * Returns the system information as plain text.
     *
     * @return string
     */
    public function getText(): string
    {
        $lines = [
            sprintf(
                'Magento: %s %s %s',
                $this->productMetadata->getName(),
                $this->productMetadata->getEdition(),
                $this->productMetadata->getVersion()
            ),
            sprintf(
                'Mode: %s',
                $this->appState->getMode()
            ),
            sprintf(
                'PHP: %s',
                PHP_VERSION
            ),
            '',
            'Infrangible modules:'
        ];

        $installedVersions = $this->installedPackages->getVersions();
        ksort($installedVersions);

        foreach ($installedVersions as $packageName => $version) {
            $lines[] = sprintf(
                '- %s %s',
                $packageName,
                $version
            );
        }

        if (count($installedVersions) === 0) {
            $lines[] = '- none';
        }

        foreach ($this->storeManager->getWebsites() as $website) {
            $lines[] = '';
            $lines[] = sprintf(
                'Website: %s (%s)',
                $website->getName(),
                $website->getCode()
            );

            foreach ($this->storeManager->getGroups() as $group) {
                // Magento declares the IDs as int, but returns them as strings from the database
                if ($this->variables->intValue($group->getWebsiteId()) !==
                    $this->variables->intValue($website->getId())) {
                    continue;
                }

                $lines[] = sprintf(
                    '  Store: %s (%s)',
                    $group->getName(),
                    $group->getCode()
                );

                foreach ($this->storeManager->getStores() as $store) {
                    if ($this->variables->intValue($store->getStoreGroupId()) ===
                        $this->variables->intValue($group->getId())) {
                        array_push(
                            $lines,
                            ...$this->getStoreLines($store)
                        );
                    }
                }
            }
        }

        return implode(
            "\n",
            $lines
        );
    }

    /**
     * Returns the lines describing a store view.
     *
     * @param StoreInterface $store
     * @return string[]
     */
    protected function getStoreLines(StoreInterface $store): array
    {
        $storeId = $this->variables->intValue($store->getId());

        $lines = [
            sprintf(
                '    Store view: %s (%s, ID %d)%s',
                $store->getName(),
                $store->getCode(),
                $storeId,
                $store->getIsActive() ? '' : ', inactive'
            ),
            sprintf(
                '      Language: %s',
                $this->getStoreConfig('general/locale/code', $storeId)
            ),
            sprintf(
                '      Time zone: %s',
                $this->getStoreConfig('general/locale/timezone', $storeId)
            )
        ];

        if ($store instanceof Store) {
            $lines[] = sprintf(
                '      URL: %s',
                $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, false)
            );
            $lines[] = sprintf(
                '      Secure URL: %s',
                $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true)
            );
            $lines[] = sprintf(
                '      Currencies: base %s, default %s, allowed %s',
                $store->getBaseCurrencyCode(),
                $store->getDefaultCurrencyCode(),
                implode(', ', $store->getAvailableCurrencyCodes(true))
            );
        }

        return $lines;
    }

    /**
     * Returns a configuration value of the store view.
     *
     * @param string $path
     * @param int $storeId
     * @return string
     */
    protected function getStoreConfig(string $path, int $storeId): string
    {
        $value = $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return is_scalar($value) ? (string)$value : '';
    }
}
