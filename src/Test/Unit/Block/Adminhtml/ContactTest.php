<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Test\Unit\Block\Adminhtml;

use Infrangible\Foundation\Block\Adminhtml\Contact;
use Infrangible\Foundation\Controller\Adminhtml\Contact\Send;
use Infrangible\Foundation\Model\SystemInformation;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Tests the block of the contact form.
 */
#[AllowMockObjectsWithoutExpectations]
class ContactTest extends TestCase
{
    /** @var SystemInformation&Stub */
    private Stub $systemInformation;

    /** @var DataPersistorInterface&MockObject */
    private MockObject $dataPersistor;

    /** @var UrlInterface&MockObject */
    private MockObject $urlBuilder;

    /** @var Contact */
    private Contact $block;

    /**
     * Creates the block with a mocked context; the parent block fetches two helpers from the object manager.
     *
     * @return void
     */
    protected function setUp(): void
    {
        ObjectManager::setInstance($this->createStub(ObjectManagerInterface::class));

        $this->systemInformation = $this->createStub(SystemInformation::class);
        $this->dataPersistor = $this->createMock(DataPersistorInterface::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);

        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($this->urlBuilder);

        $this->block = new Contact($context, $this->systemInformation, $this->dataPersistor);
    }

    /**
     * The form is sent to the send action of the current controller.
     *
     * @return void
     */
    public function testGetSendUrl(): void
    {
        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('*/*/send')
            ->willReturn('https://example.com/admin/infrangible_solutions/contact/send/');

        $this->assertSame(
            'https://example.com/admin/infrangible_solutions/contact/send/',
            $this->block->getSendUrl()
        );
    }

    /**
     * The message of a failed attempt is returned once.
     *
     * @return void
     */
    public function testGetMessage(): void
    {
        $this->dataPersistor->method('get')
            ->with(Send::DATA_PERSISTOR_KEY)
            ->willReturnOnConsecutiveCalls('Hello', null);
        $this->dataPersistor->expects($this->exactly(2))->method('clear')->with(Send::DATA_PERSISTOR_KEY);

        $this->assertSame('Hello', $this->block->getMessage());
        $this->assertSame('', $this->block->getMessage());
    }

    /**
     * Maximum length, recipient and system information come from the mail service.
     *
     * @return void
     */
    public function testFormData(): void
    {
        $this->systemInformation->method('getText')->willReturn('Magento: Magento Community 2.4.8-p3');

        $this->assertSame(10000, $this->block->getMaxMessageLength());
        $this->assertSame('publish@infrangible.dev', $this->block->getRecipient());
        $this->assertSame('Magento: Magento Community 2.4.8-p3', $this->block->getSystemInformation());
    }
}
