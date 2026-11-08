<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Test\Unit\Controller\Adminhtml\Contact;

use Infrangible\Foundation\Controller\Adminhtml\Contact\Send;
use Infrangible\Foundation\Model\ContactMail;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Message\ManagerInterface;
use Magento\User\Model\User;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Tests sending the contact form.
 */
#[AllowMockObjectsWithoutExpectations]
class SendTest extends TestCase
{
    /** @var ContactMail&MockObject */
    private MockObject $contactMail;

    /** @var DataPersistorInterface&MockObject */
    private MockObject $dataPersistor;

    /** @var ManagerInterface&MockObject */
    private MockObject $messageManager;

    /** @var RequestInterface&MockObject */
    private MockObject $request;

    /** @var User&Stub */
    private Stub $user;

    /** @var Redirect&MockObject */
    private MockObject $redirect;

    /** @var Send */
    private Send $controller;

    /**
     * Creates the controller with a logged-in admin user.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->contactMail = $this->createMock(ContactMail::class);
        $this->dataPersistor = $this->createMock(DataPersistorInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->user = $this->createStub(User::class);

        $auth = $this->createStub(Auth::class);
        $auth->method('getUser')->willReturn($this->user);

        $this->redirect = $this->createMock(Redirect::class);
        $this->redirect->method('setPath')->willReturnSelf();

        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->redirect);

        $context = $this->createStub(Context::class);
        $context->method('getMessageManager')->willReturn($this->messageManager);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getAuth')->willReturn($auth);

        $this->controller = new Send($context, $this->contactMail, $this->dataPersistor);
    }

    /**
     * Expects the redirect back to the contact form.
     *
     * @return void
     */
    private function expectRedirectToForm(): void
    {
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/index')->willReturnSelf();
    }

    /**
     * The message is sent for the logged-in user and a kept message of an earlier attempt is removed.
     *
     * @return void
     * @throws NotFoundException
     */
    public function testExecute(): void
    {
        $this->request->method('getParam')->with('message')->willReturn('Hello');
        $this->contactMail->expects($this->once())->method('send')->with('Hello', $this->user);
        $this->dataPersistor->expects($this->once())->method('clear')->with(Send::DATA_PERSISTOR_KEY);
        $this->dataPersistor->expects($this->never())->method('set');
        $this->messageManager->expects($this->once())
            ->method('addSuccessMessage')
            ->with('Thank you for your message. We will get back to you as soon as possible.');
        $this->expectRedirectToForm();

        $this->assertSame($this->redirect, $this->controller->execute());
    }

    /**
     * A rejected message is kept for the form and the reason is shown.
     *
     * @return void
     * @throws NotFoundException
     */
    public function testInvalidMessage(): void
    {
        $this->request->method('getParam')->willReturn(' ');
        $this->contactMail->method('send')->willThrowException(new LocalizedException(__('Please enter a message.')));
        $this->dataPersistor->expects($this->once())->method('set')->with(Send::DATA_PERSISTOR_KEY, ' ');
        $this->messageManager->expects($this->once())->method('addErrorMessage')->with('Please enter a message.');
        $this->messageManager->expects($this->never())->method('addSuccessMessage');
        $this->expectRedirectToForm();

        $this->assertSame($this->redirect, $this->controller->execute());
    }

    /**
     * If the mail cannot be sent, the message is kept and a hint to the mail configuration is shown.
     *
     * @return void
     * @throws NotFoundException
     */
    public function testMailError(): void
    {
        $exception = new MailException(__('SMTP error'));

        $this->request->method('getParam')->willReturn('Hello');
        $this->contactMail->method('send')->willThrowException($exception);
        $this->dataPersistor->expects($this->once())->method('set')->with(Send::DATA_PERSISTOR_KEY, 'Hello');
        $this->messageManager->expects($this->once())
            ->method('addExceptionMessage')
            ->with($exception, 'The message could not be sent. Please check the mail configuration of the shop.');
        $this->expectRedirectToForm();

        $this->assertSame($this->redirect, $this->controller->execute());
    }

    /**
     * Other errors, for example of an SMTP module, are shown with a hint to the mail configuration.
     *
     * @return void
     * @throws NotFoundException
     */
    public function testUnexpectedError(): void
    {
        $exception = new \RuntimeException('Connection refused');

        $this->request->method('getParam')->willReturn('Hello');
        $this->contactMail->method('send')->willThrowException($exception);
        $this->dataPersistor->expects($this->once())->method('set')->with(Send::DATA_PERSISTOR_KEY, 'Hello');
        $this->messageManager->expects($this->once())
            ->method('addExceptionMessage')
            ->with($exception, 'The message could not be sent. Please check the mail configuration of the shop.');
        $this->expectRedirectToForm();

        $this->assertSame($this->redirect, $this->controller->execute());
    }

    /**
     * A missing or invalid parameter counts as an empty message; without a user nothing is sent.
     *
     * @return void
     * @throws NotFoundException
     */
    public function testWithoutUserAndMessage(): void
    {
        $auth = $this->createStub(Auth::class);
        $auth->method('getUser')->willReturn(null);

        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->redirect);

        $context = $this->createStub(Context::class);
        $context->method('getMessageManager')->willReturn($this->messageManager);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getAuth')->willReturn($auth);

        $this->request->method('getParam')->willReturn(['not', 'a', 'string']);
        $this->contactMail->expects($this->never())->method('send');
        $this->dataPersistor->expects($this->once())->method('set')->with(Send::DATA_PERSISTOR_KEY, '');
        $this->messageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with('The admin user could not be determined.');
        $this->expectRedirectToForm();

        $controller = new Send($context, $this->contactMail, $this->dataPersistor);

        $this->assertSame($this->redirect, $controller->execute());
    }

    /**
     * Sending is protected by the ACL resource of the contact form and only possible with POST.
     *
     * @return void
     */
    public function testAccess(): void
    {
        $this->assertSame('Infrangible_Foundation::infrangible_contact', Send::ADMIN_RESOURCE);
        $this->assertContains(HttpPostActionInterface::class, class_implements(Send::class));
        $this->assertNotContains(HttpGetActionInterface::class, class_implements(Send::class));
    }
}
