<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Test\Unit\Model;

use Infrangible\Foundation\Model\ContactMail;
use Infrangible\Foundation\Model\SystemInformation;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\User\Model\User;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Tests sending a contact request with the mail setup of the installation.
 */
#[AllowMockObjectsWithoutExpectations]
class ContactMailTest extends TestCase
{
    /** @var TransportBuilder&MockObject */
    private MockObject $transportBuilder;

    /** @var TransportInterface&MockObject */
    private MockObject $transport;

    /** @var StateInterface&MockObject */
    private MockObject $inlineTranslation;

    /** @var bool */
    private bool $emailDisabled = false;

    /** @var ContactMail */
    private ContactMail $contactMail;

    /** @var User&Stub */
    private Stub $user;

    /**
     * Creates the service with a mocked transport builder.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);

        $this->transportBuilder = $this->createMock(TransportBuilder::class);
        foreach (['setTemplateIdentifier', 'setTemplateOptions', 'setTemplateVars', 'setFromByScope', 'addTo',
                     'setReplyTo'] as $method) {
            $this->transportBuilder->method($method)->willReturnSelf();
        }
        $this->transportBuilder->method('getTransport')->willReturn($this->transport);

        $this->inlineTranslation = $this->createMock(StateInterface::class);

        $systemInformation = $this->createStub(SystemInformation::class);
        $systemInformation->method('getText')->willReturn('Magento: Magento Community 2.4.8-p3');

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            function (string $path, string $scope): bool {
                $this->assertSame(ContactMail::XML_PATH_EMAIL_DISABLED, $path);
                $this->assertSame(ScopeInterface::SCOPE_STORE, $scope);

                return $this->emailDisabled;
            }
        );

        $this->contactMail = new ContactMail(
            $this->transportBuilder,
            $this->inlineTranslation,
            $systemInformation,
            $scopeConfig
        );

        $this->user = $this->createStub(User::class);
        $this->user->method('getFirstName')->willReturn('Dev');
        $this->user->method('getLastName')->willReturn('Admin');
        $this->user->method('getEmail')->willReturn('admin@example.com');
        $this->user->method('getUserName')->willReturn('dev-admin');
    }

    /**
     * The mail goes to Infrangible from the general contact of the shop, with the admin user as reply address.
     *
     * @return void
     * @throws LocalizedException
     * @throws MailException
     */
    public function testSend(): void
    {
        $this->transportBuilder->expects($this->once())
            ->method('setTemplateIdentifier')
            ->with('infrangible_foundation_contact');
        $this->transportBuilder->expects($this->once())
            ->method('setTemplateOptions')
            ->with(['area' => Area::AREA_ADMINHTML, 'store' => 0]);
        $this->transportBuilder->expects($this->once())
            ->method('setTemplateVars')
            ->with(
                [
                    'message'            => "Hello\n\nworld",
                    'user_name'          => 'Dev Admin',
                    'user_email'         => 'admin@example.com',
                    'user_login'         => 'dev-admin',
                    'system_information' => 'Magento: Magento Community 2.4.8-p3'
                ]
            );
        $this->transportBuilder->expects($this->once())->method('setFromByScope')->with('general');
        $this->transportBuilder->expects($this->once())
            ->method('addTo')
            ->with('publish@infrangible.dev', 'Infrangible');
        $this->transportBuilder->expects($this->once())
            ->method('setReplyTo')
            ->with('admin@example.com', 'Dev Admin');
        $this->transport->expects($this->once())->method('sendMessage');
        $this->inlineTranslation->expects($this->once())->method('suspend');
        $this->inlineTranslation->expects($this->once())->method('resume');

        $this->contactMail->send("  Hello\n\nworld \n", $this->user);
    }

    /**
     * Provides messages that are rejected and the expected error.
     *
     * @return array<string, array{string, string}>
     */
    public static function invalidMessageProvider(): array
    {
        return [
            'empty'       => ['', 'Please enter a message.'],
            'whitespace'  => [" \n\t ", 'Please enter a message.'],
            'too long'    => [
                str_repeat('ä', ContactMail::MAX_MESSAGE_LENGTH + 1),
                'The message must not be longer than 10000 characters.'
            ]
        ];
    }

    /**
     * @dataProvider invalidMessageProvider
     *
     * @param string $message
     * @param string $expectedError
     * @return void
     * @throws LocalizedException
     * @throws MailException
     */
    #[DataProvider('invalidMessageProvider')]
    public function testInvalidMessageIsNotSent(string $message, string $expectedError): void
    {
        $this->transport->expects($this->never())->method('sendMessage');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($expectedError);

        $this->contactMail->send($message, $this->user);
    }

    /**
     * A message of exactly the maximum length is sent; the length counts characters, not bytes.
     *
     * @return void
     * @throws LocalizedException
     * @throws MailException
     */
    public function testMessageWithMaximumLength(): void
    {
        $this->transport->expects($this->once())->method('sendMessage');

        $this->contactMail->send(str_repeat('ä', ContactMail::MAX_MESSAGE_LENGTH), $this->user);
    }

    /**
     * If email communication is disabled, Magento would drop the mail silently, so it is reported as an error.
     *
     * @return void
     * @throws LocalizedException
     * @throws MailException
     */
    public function testEmailDisabled(): void
    {
        $this->emailDisabled = true;
        $this->transport->expects($this->never())->method('sendMessage');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Email communication is disabled');

        $this->contactMail->send('Hello', $this->user);
    }

    /**
     * A failing transport is passed on and inline translation is resumed nevertheless.
     *
     * @return void
     * @throws LocalizedException
     * @throws MailException
     */
    public function testMailError(): void
    {
        $this->transport->method('sendMessage')->willThrowException(new MailException(__('SMTP error')));
        $this->inlineTranslation->expects($this->once())->method('resume');

        $this->expectException(MailException::class);

        $this->contactMail->send('Hello', $this->user);
    }
}
