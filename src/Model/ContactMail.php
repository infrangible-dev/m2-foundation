<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Model;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\User\Model\User;

/**
 * Sends a contact request from the admin to Infrangible, together with the system information.
 */
class ContactMail
{
    public const RECIPIENT_EMAIL = 'publish@infrangible.dev';

    public const RECIPIENT_NAME = 'Infrangible';

    public const TEMPLATE_IDENTIFIER = 'infrangible_foundation_contact';

    public const MAX_MESSAGE_LENGTH = 10000;

    public const XML_PATH_EMAIL_DISABLED = 'system/smtp/disable';

    /**
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param SystemInformation $systemInformation
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        protected TransportBuilder $transportBuilder,
        protected StateInterface $inlineTranslation,
        protected SystemInformation $systemInformation,
        protected ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Sends the message with the mail setup of the installation; replies go to the admin user who wrote it.
     *
     * @param string $message
     * @param User $user
     * @return void
     * @throws LocalizedException if the message is empty or too long or email communication is disabled
     * @throws MailException if the mail cannot be sent
     */
    public function send(string $message, User $user): void
    {
        $message = trim($message);

        if ($message === '') {
            throw new LocalizedException(__('Please enter a message.'));
        }

        if (mb_strlen($message) > static::MAX_MESSAGE_LENGTH) {
            throw new LocalizedException(
                __(
                    'The message must not be longer than %1 characters.',
                    static::MAX_MESSAGE_LENGTH
                )
            );
        }

        // Magento would silently drop the mail
        if ($this->scopeConfig->isSetFlag(static::XML_PATH_EMAIL_DISABLED, ScopeInterface::SCOPE_STORE)) {
            throw new LocalizedException(
                __('Email communication is disabled in the configuration (Advanced > System > Mail Sending Settings).')
            );
        }

        $userName = trim(
            sprintf(
                '%s %s',
                $user->getFirstName(),
                $user->getLastName()
            )
        );
        $userEmail = (string)$user->getEmail();

        $this->inlineTranslation->suspend();

        try {
            $this->transportBuilder->setTemplateIdentifier(static::TEMPLATE_IDENTIFIER)
                ->setTemplateOptions(['area' => Area::AREA_ADMINHTML, 'store' => Store::DEFAULT_STORE_ID])
                ->setTemplateVars(
                    [
                        'message'            => $message,
                        'user_name'          => $userName,
                        'user_email'         => $userEmail,
                        'user_login'         => (string)$user->getUserName(),
                        'system_information' => $this->systemInformation->getText()
                    ]
                )
                ->setFromByScope('general')
                ->addTo(
                    static::RECIPIENT_EMAIL,
                    static::RECIPIENT_NAME
                )
                ->setReplyTo(
                    $userEmail,
                    $userName
                );

            $this->transportBuilder->getTransport()->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
