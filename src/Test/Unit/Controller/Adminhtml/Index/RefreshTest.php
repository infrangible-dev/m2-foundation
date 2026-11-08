<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Test\Unit\Controller\Adminhtml\Index;

use Infrangible\Foundation\Controller\Adminhtml\Index\Refresh;
use Infrangible\Foundation\Helper\Data;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests loading the package list from the solutions page.
 */
#[AllowMockObjectsWithoutExpectations]
class RefreshTest extends TestCase
{
    /** @var Data&MockObject */
    private MockObject $helper;

    /** @var ManagerInterface&MockObject */
    private MockObject $messageManager;

    /** @var Redirect&MockObject */
    private MockObject $redirect;

    /** @var Refresh */
    private Refresh $controller;

    /**
     * Creates the controller with mocked messages and redirect.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->helper = $this->createMock(Data::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);

        $this->redirect = $this->createMock(Redirect::class);
        $this->redirect->method('setPath')->willReturnSelf();

        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->redirect);

        $context = $this->createStub(Context::class);
        $context->method('getMessageManager')->willReturn($this->messageManager);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);

        $this->controller = new Refresh($context, $this->helper);
    }

    /**
     * After loading, the number of packages is shown and the solutions page is opened again.
     *
     * @return void
     * @throws NotFoundException
     */
    public function testExecute(): void
    {
        $this->helper->expects($this->once())->method('refreshPackages')->willReturn(
            [
                'infrangible/m2-core' => [
                    'name'        => 'infrangible/m2-core',
                    'description' => 'Core',
                    'version'     => '4.0.0',
                    'time'        => '2025-10-03T00:56:16+00:00'
                ],
                'infrangible/m2-log'  => [
                    'name'        => 'infrangible/m2-log',
                    'description' => 'Log',
                    'version'     => '2.0.0',
                    'time'        => '2026-01-16T00:59:00+00:00'
                ]
            ]
        );
        $this->messageManager->expects($this->once())
            ->method('addSuccessMessage')
            ->with('Loaded 2 packages from Packagist.');
        $this->messageManager->expects($this->never())->method('addExceptionMessage');

        $this->redirect->expects($this->once())->method('setPath')->with('*/*/index')->willReturnSelf();

        $this->assertSame($this->redirect, $this->controller->execute());
    }

    /**
     * If the list cannot be loaded, an error is shown and the solutions page is opened again.
     *
     * @return void
     * @throws NotFoundException
     */
    public function testExecuteWithError(): void
    {
        $exception = new RuntimeException('Request failed');

        $this->helper->method('refreshPackages')->willThrowException($exception);
        $this->messageManager->expects($this->never())->method('addSuccessMessage');
        $this->messageManager->expects($this->once())
            ->method('addExceptionMessage')
            ->with($exception, 'The package list could not be loaded from Packagist.');

        $this->redirect->expects($this->once())->method('setPath')->with('*/*/index')->willReturnSelf();

        $this->assertSame($this->redirect, $this->controller->execute());
    }

    /**
     * Loading is protected by the ACL resource of the page and only possible with POST.
     *
     * @return void
     */
    public function testAccess(): void
    {
        $this->assertSame('Infrangible_Foundation::infrangible_solutions', Refresh::ADMIN_RESOURCE);
        $this->assertContains(HttpPostActionInterface::class, class_implements(Refresh::class));
        $this->assertNotContains(HttpGetActionInterface::class, class_implements(Refresh::class));
    }
}
