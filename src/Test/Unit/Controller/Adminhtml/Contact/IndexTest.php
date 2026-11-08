<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Test\Unit\Controller\Adminhtml\Contact;

use Infrangible\Foundation\Controller\Adminhtml\Contact\Index;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;
use PHPUnit\Framework\TestCase;

/**
 * Tests the controller of the contact form.
 */
class IndexTest extends TestCase
{
    /**
     * The page activates the menu entry and gets the page title.
     *
     * @return void
     * @throws NotFoundException
     */
    public function testExecute(): void
    {
        $title = $this->createMock(Title::class);
        $title->expects($this->once())
            ->method('prepend')
            ->with($this->callback(fn($text): bool => (string)$text === 'Contact Infrangible'));

        $config = $this->createStub(Config::class);
        $config->method('getTitle')->willReturn($title);

        $page = $this->createMock(Page::class);
        $page->expects($this->once())->method('setActiveMenu')->with('Infrangible_Foundation::infrangible_contact');
        $page->method('getConfig')->willReturn($config);

        $pageFactory = $this->createStub(PageFactory::class);
        $pageFactory->method('create')->willReturn($page);

        $controller = new Index($this->createStub(Context::class), $pageFactory);

        $this->assertSame($page, $controller->execute());
    }

    /**
     * The form is protected by its own ACL resource and only answers GET requests.
     *
     * @return void
     */
    public function testAccess(): void
    {
        $this->assertSame('Infrangible_Foundation::infrangible_contact', Index::ADMIN_RESOURCE);
        $this->assertContains(HttpGetActionInterface::class, class_implements(Index::class));
    }
}
