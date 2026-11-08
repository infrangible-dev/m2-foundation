<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Test\Unit\Model;

use FeWeDev\Base\Variables;
use Infrangible\Foundation\Model\InstalledPackages;
use Infrangible\Foundation\Model\SystemInformation;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\State;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the system information that is sent with a contact request.
 */
class SystemInformationTest extends TestCase
{
    /** configuration of the store views, by store ID */
    private const STORE_CONFIG = [
        1 => ['general/locale/code' => 'de_DE', 'general/locale/timezone' => 'Europe/Berlin'],
        2 => ['general/locale/code' => 'en_US', 'general/locale/timezone' => 'Europe/Berlin'],
        3 => ['general/locale/code' => 'de_CH', 'general/locale/timezone' => 'Europe/Zurich'],
        4 => ['general/locale/code' => null, 'general/locale/timezone' => null]
    ];

    /**
     * Returns a store view mock.
     *
     * @param int $id
     * @param string $code
     * @param int $groupId
     * @param bool $active
     * @param string[] $currencies base, default and allowed currencies
     * @return Store
     */
    private function createStore(int $id, string $code, int $groupId, bool $active, array $currencies): Store
    {
        $store = $this->createMock(Store::class);
        // Magento returns the IDs from the database as strings, although they are declared as int
        $store->method('getId')->willReturn((string)$id);
        $store->method('getCode')->willReturn($code);
        $store->method('getName')->willReturn(ucfirst($code) . ' Store View');
        $store->method('getStoreGroupId')->willReturn((string)$groupId);
        $store->method('getIsActive')->willReturn($active ? 1 : 0);
        $store->method('getBaseUrl')->willReturnCallback(
            function (string $type, bool $secure) use ($code): string {
                $this->assertSame(UrlInterface::URL_TYPE_WEB, $type);

                return sprintf(
                    '%s://%s.example.com/',
                    $secure ? 'https' : 'http',
                    $code
                );
            }
        );
        $store->method('getBaseCurrencyCode')->willReturn($currencies[ 0 ]);
        $store->method('getDefaultCurrencyCode')->willReturn($currencies[ 1 ]);
        $store->method('getAvailableCurrencyCodes')->with(true)->willReturn(array_slice($currencies, 2));

        return $store;
    }

    /**
     * Returns the service for an installation with two websites, one of them with two store views.
     *
     * @param array<string, string> $installedVersions
     * @return SystemInformation
     */
    private function createSystemInformation(array $installedVersions): SystemInformation
    {
        $productMetadata = $this->createStub(ProductMetadataInterface::class);
        $productMetadata->method('getName')->willReturn('Magento');
        $productMetadata->method('getEdition')->willReturn('Community');
        $productMetadata->method('getVersion')->willReturn('2.4.8-p3');

        $appState = $this->createStub(State::class);
        $appState->method('getMode')->willReturn(State::MODE_PRODUCTION);

        $websites = [];
        foreach ([[1, 'base', 'Main Website'], [2, 'b2b', 'B2B Website']] as [$id, $code, $name]) {
            $website = $this->createStub(WebsiteInterface::class);
            $website->method('getId')->willReturn($id);
            $website->method('getCode')->willReturn($code);
            $website->method('getName')->willReturn($name);
            $websites[] = $website;
        }

        $groups = [];
        foreach ([[1, 1, 'main', 'Main Store'], [2, 2, 'b2b', 'B2B Store']] as [$id, $websiteId, $code, $name]) {
            $group = $this->createStub(GroupInterface::class);
            $group->method('getId')->willReturn($id);
            $group->method('getWebsiteId')->willReturn((string)$websiteId);
            $group->method('getCode')->willReturn($code);
            $group->method('getName')->willReturn($name);
            $groups[] = $group;
        }

        // a store view that only implements the interface gets no URLs and currencies
        $interfaceStore = $this->createStub(StoreInterface::class);
        $interfaceStore->method('getId')->willReturn(4);
        $interfaceStore->method('getCode')->willReturn('other');
        $interfaceStore->method('getName')->willReturn('Other Store View');
        $interfaceStore->method('getStoreGroupId')->willReturn(2);
        $interfaceStore->method('getIsActive')->willReturn(1);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getWebsites')->willReturn($websites);
        $storeManager->method('getGroups')->willReturn($groups);
        $storeManager->method('getStores')->willReturn(
            [
                $this->createStore(1, 'de', 1, true, ['EUR', 'EUR', 'EUR', 'CHF']),
                $this->createStore(2, 'en', 1, false, ['EUR', 'USD', 'EUR', 'USD']),
                $this->createStore(3, 'b2b', 2, true, ['EUR', 'EUR', 'EUR']),
                $interfaceStore
            ]
        );

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function (string $path, string $scope, int $storeId) {
                $this->assertSame(ScopeInterface::SCOPE_STORE, $scope);

                return self::STORE_CONFIG[ $storeId ][ $path ];
            }
        );

        $installedPackages = $this->createStub(InstalledPackages::class);
        $installedPackages->method('getVersions')->willReturn($installedVersions);

        return new SystemInformation(
            $productMetadata,
            $appState,
            $storeManager,
            $scopeConfig,
            $installedPackages,
            new Variables()
        );
    }

    /**
     * The text lists the installation, the modules sorted by name and every store view below its website and store.
     *
     * @return void
     */
    public function testGetText(): void
    {
        $systemInformation = $this->createSystemInformation(
            ['infrangible/m2-foundation' => '3.0.0', 'infrangible/m2-core' => '4.0.0']
        );

        $this->assertSame(
            implode(
                "\n",
                [
                    'Magento: Magento Community 2.4.8-p3',
                    'Mode: production',
                    'PHP: ' . PHP_VERSION,
                    '',
                    'Infrangible modules:',
                    '- infrangible/m2-core 4.0.0',
                    '- infrangible/m2-foundation 3.0.0',
                    '',
                    'Website: Main Website (base)',
                    '  Store: Main Store (main)',
                    '    Store view: De Store View (de, ID 1)',
                    '      Language: de_DE',
                    '      Time zone: Europe/Berlin',
                    '      URL: http://de.example.com/',
                    '      Secure URL: https://de.example.com/',
                    '      Currencies: base EUR, default EUR, allowed EUR, CHF',
                    '    Store view: En Store View (en, ID 2), inactive',
                    '      Language: en_US',
                    '      Time zone: Europe/Berlin',
                    '      URL: http://en.example.com/',
                    '      Secure URL: https://en.example.com/',
                    '      Currencies: base EUR, default USD, allowed EUR, USD',
                    '',
                    'Website: B2B Website (b2b)',
                    '  Store: B2B Store (b2b)',
                    '    Store view: B2b Store View (b2b, ID 3)',
                    '      Language: de_CH',
                    '      Time zone: Europe/Zurich',
                    '      URL: http://b2b.example.com/',
                    '      Secure URL: https://b2b.example.com/',
                    '      Currencies: base EUR, default EUR, allowed EUR',
                    '    Store view: Other Store View (other, ID 4)',
                    '      Language: ',
                    '      Time zone: '
                ]
            ),
            $systemInformation->getText()
        );
    }

    /**
     * Without installed Infrangible packages the list says so.
     *
     * @return void
     */
    public function testGetTextWithoutModules(): void
    {
        $this->assertStringContainsString(
            "Infrangible modules:\n- none\n",
            $this->createSystemInformation([])->getText()
        );
    }
}
