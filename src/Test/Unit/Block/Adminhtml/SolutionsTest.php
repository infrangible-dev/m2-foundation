<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Test\Unit\Block\Adminhtml;

use Infrangible\Foundation\Block\Adminhtml\Solutions;
use Infrangible\Foundation\Helper\Data;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Tests the block of the solutions page.
 */
#[AllowMockObjectsWithoutExpectations]
class SolutionsTest extends TestCase
{
    /** @var Data&Stub */
    private Stub $helper;

    /** @var UrlInterface&MockObject */
    private MockObject $urlBuilder;

    /** @var Solutions */
    private Solutions $block;

    /**
     * Creates the block with a mocked context; the parent block fetches two helpers from the object manager.
     *
     * @return void
     */
    protected function setUp(): void
    {
        ObjectManager::setInstance($this->createStub(ObjectManagerInterface::class));

        $this->helper = $this->createStub(Data::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);

        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($this->urlBuilder);

        $this->block = new Solutions($context, $this->helper);
    }

    /**
     * The items come from the helper.
     *
     * @return void
     */
    public function testGetItems(): void
    {
        $items = [
            [
                'name'        => 'infrangible/m2-core',
                'description' => 'Core',
                'version'     => '4.0.0',
                'time'        => '2025-10-03T00:56:16+00:00',
                'installed'   => '4.0.0',
                'status'      => Data::STATUS_OK
            ]
        ];

        $this->helper->method('getItems')->willReturn($items);

        $this->assertSame($items, $this->block->getItems());
    }

    /**
     * The list counts as loaded as soon as the cache exists, even if it is empty.
     *
     * @return void
     */
    public function testIsLoaded(): void
    {
        $this->helper->method('getCachedPackages')->willReturnOnConsecutiveCalls(null, [], ['x' => []]);

        $this->assertFalse($this->block->isLoaded());
        $this->assertTrue($this->block->isLoaded());
        $this->assertTrue($this->block->isLoaded());
    }

    /**
     * The refresh URL points to the refresh action of the current controller.
     *
     * @return void
     */
    public function testGetRefreshUrl(): void
    {
        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('*/*/refresh')
            ->willReturn('https://example.com/admin/infrangible_solutions/index/refresh/');

        $this->assertSame(
            'https://example.com/admin/infrangible_solutions/index/refresh/',
            $this->block->getRefreshUrl()
        );
    }

    /**
     * Packages link to their Packagist page.
     *
     * @return void
     */
    public function testGetPackageUrl(): void
    {
        $this->assertSame(
            'https://packagist.org/packages/infrangible/m2-core',
            $this->block->getPackageUrl('infrangible/m2-core')
        );
    }
}
