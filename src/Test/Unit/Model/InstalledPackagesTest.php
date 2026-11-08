<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Test\Unit\Model;

use Composer\InstalledVersions;
use Infrangible\Foundation\Model\InstalledPackages;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Module\FullModuleList;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Tests how the installed versions are determined for Composer and app/code installations.
 */
#[AllowMockObjectsWithoutExpectations]
class InstalledPackagesTest extends TestCase
{
    /** @var array<string, string> file path => content */
    private array $files = [];

    /** @var LoggerInterface&MockObject */
    private MockObject $logging;

    /** @var array<string, mixed> static properties of InstalledVersions before the test */
    private array $installedVersionsState = [];

    /**
     * Remembers the state of Composer's InstalledVersions, which a test replaces with fake data.
     *
     * @return void
     */
    protected function setUp(): void
    {
        foreach ((new ReflectionClass(InstalledVersions::class))->getProperties() as $property) {
            if ($property->isStatic()) {
                $this->installedVersionsState[ $property->getName() ] = $property->getValue();
            }
        }
    }

    /**
     * Restores the state of Composer's InstalledVersions.
     *
     * @return void
     * @throws ReflectionException
     */
    protected function tearDown(): void
    {
        foreach ($this->installedVersionsState as $name => $value) {
            $property = (new ReflectionClass(InstalledVersions::class))->getProperty($name);
            $property->setValue(null, $value);
        }
    }

    /**
     * Composer packages of the Infrangible vendor are returned with their version; others and packages without a
     * version (for example replaced ones) are left out.
     *
     * @return void
     */
    public function testComposerVersions(): void
    {
        InstalledVersions::reload(
            [
                'root'     => [
                    'name'           => 'magento/project',
                    'pretty_version' => '1.0.0',
                    'version'        => '1.0.0.0',
                    'reference'      => null,
                    'type'           => 'project',
                    'install_path'   => '/var/www/magento',
                    'aliases'        => [],
                    'dev'            => false
                ],
                'versions' => [
                    'infrangible/m2-fake-installed' => [
                        'pretty_version'  => '9.8.7',
                        'version'         => '9.8.7.0',
                        'reference'       => null,
                        'type'            => 'magento2-module',
                        'install_path'    => '/vendor/infrangible/m2-fake-installed',
                        'aliases'         => [],
                        'dev_requirement' => false
                    ],
                    'infrangible/m2-fake-replaced'  => [
                        'dev_requirement' => false,
                        'replaced'        => ['*']
                    ],
                    'other/m2-fake'                 => [
                        'pretty_version'  => '1.0.0',
                        'version'         => '1.0.0.0',
                        'reference'       => null,
                        'type'            => 'library',
                        'install_path'    => '/vendor/other/m2-fake',
                        'aliases'         => [],
                        'dev_requirement' => false
                    ]
                ]
            ]
        );

        $fullModuleList = $this->createStub(FullModuleList::class);
        $fullModuleList->method('getNames')->willReturn([]);

        $installedPackages = new InstalledPackages(
            $fullModuleList,
            $this->createStub(Reader::class),
            $this->createStub(File::class),
            new Json(),
            $this->createStub(LoggerInterface::class)
        );

        // the real packages of the installation are reported as well, so only the fake ones are checked
        $versions = $installedPackages->getVersions();

        $this->assertSame('9.8.7', $versions[ 'infrangible/m2-fake-installed' ] ?? null);
        $this->assertArrayNotHasKey('infrangible/m2-fake-replaced', $versions);
        $this->assertArrayNotHasKey('other/m2-fake', $versions);

        foreach (array_keys($versions) as $packageName) {
            $this->assertStringStartsWith('infrangible/', $packageName);
        }
    }

    /**
     * Returns the service with fake modules and files; Composer reports the given packages.
     *
     * @param array<string, string> $modules module name => module directory
     * @param array<string, string> $composerVersions
     * @return InstalledPackages
     */
    private function createInstalledPackages(array $modules, array $composerVersions): InstalledPackages
    {
        $fullModuleList = $this->createStub(FullModuleList::class);
        $fullModuleList->method('getNames')->willReturn(array_keys($modules));

        $moduleReader = $this->createStub(Reader::class);
        $moduleReader->method('getModuleDir')->willReturnCallback(
            function (string $type, string $moduleName) use ($modules): string {
                return $modules[ $moduleName ];
            }
        );

        $file = $this->createStub(File::class);
        $file->method('isExists')->willReturnCallback(
            function (string $path): bool {
                return array_key_exists($path, $this->files);
            }
        );
        $file->method('fileGetContents')->willReturnCallback(
            function (string $path): string {
                return $this->files[ $path ];
            }
        );

        $this->logging = $this->createMock(LoggerInterface::class);

        $installedPackages = $this->getMockBuilder(InstalledPackages::class)
            ->setConstructorArgs([$fullModuleList, $moduleReader, $file, new Json(), $this->logging])
            ->onlyMethods(['getComposerVersions'])
            ->getMock();
        $installedPackages->method('getComposerVersions')->willReturn($composerVersions);

        return $installedPackages;
    }

    /**
     * Composer versions win; app/code modules are found in the module directory or, with src, one level above.
     *
     * @return void
     */
    public function testGetVersions(): void
    {
        $this->files = [
            '/app/code/Infrangible/Seo/composer.json'      => '{"name":"infrangible/m2-seo","version":"1.1.1"}',
            '/modules/m2-task/src/../composer.json'        => '{"name":"infrangible/m2-task","version":"2.4.2"}',
            '/vendor/infrangible/m2-core/src/../composer.json' =>
                '{"name":"infrangible/m2-core","version":"3.0.0"}'
        ];

        $installedPackages = $this->createInstalledPackages(
            [
                'Magento_Catalog'      => '/vendor/magento/module-catalog',
                'Infrangible_Seo'      => '/app/code/Infrangible/Seo',
                'Infrangible_Task'     => '/modules/m2-task/src',
                'Infrangible_Core'     => '/vendor/infrangible/m2-core/src',
                'Infrangible_Unknown'  => '/app/code/Infrangible/Unknown'
            ],
            ['infrangible/m2-core' => '4.0.0']
        );

        $this->assertSame(
            [
                'infrangible/m2-core' => '4.0.0',
                'infrangible/m2-seo'  => '1.1.1',
                'infrangible/m2-task' => '2.4.2'
            ],
            $installedPackages->getVersions()
        );
    }

    /**
     * A composer.json that cannot be parsed is logged and skipped; one without a version is ignored.
     *
     * @return void
     */
    public function testInvalidComposerFiles(): void
    {
        $this->files = [
            '/app/code/Infrangible/Broken/composer.json'    => '{invalid',
            '/app/code/Infrangible/NoVersion/composer.json' => '{"name":"infrangible/m2-no-version"}'
        ];

        $installedPackages = $this->createInstalledPackages(
            [
                'Infrangible_Broken'    => '/app/code/Infrangible/Broken',
                'Infrangible_NoVersion' => '/app/code/Infrangible/NoVersion'
            ],
            []
        );

        $this->logging->expects($this->once())->method('error');

        $this->assertSame([], $installedPackages->getVersions());
    }
}
