<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Block\Adminhtml;

use Infrangible\Foundation\Controller\Adminhtml\Contact\Send;
use Infrangible\Foundation\Model\ContactMail;
use Infrangible\Foundation\Model\SystemInformation;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Request\DataPersistorInterface;

/**
 * Contact form with a preview of the system information that is sent along.
 */
class Contact extends Template
{
    /** @var SystemInformation */
    protected SystemInformation $systemInformation;

    /** @var DataPersistorInterface */
    protected DataPersistorInterface $dataPersistor;

    /**
     * @param Context $context
     * @param SystemInformation $systemInformation
     * @param DataPersistorInterface $dataPersistor
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        SystemInformation $systemInformation,
        DataPersistorInterface $dataPersistor,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $data
        );

        $this->systemInformation = $systemInformation;
        $this->dataPersistor = $dataPersistor;
    }

    /**
     * Returns the URL the form is sent to.
     *
     * @return string
     */
    public function getSendUrl(): string
    {
        return $this->getUrl('*/*/send');
    }

    /**
     * Returns the message of a failed attempt, so that it does not have to be written again.
     *
     * @return string
     */
    public function getMessage(): string
    {
        $message = $this->dataPersistor->get(Send::DATA_PERSISTOR_KEY);

        $this->dataPersistor->clear(Send::DATA_PERSISTOR_KEY);

        return is_string($message) ? $message : '';
    }

    /**
     * Returns the maximum length of the message.
     *
     * @return int
     */
    public function getMaxMessageLength(): int
    {
        return ContactMail::MAX_MESSAGE_LENGTH;
    }

    /**
     * Returns the recipient of the message.
     *
     * @return string
     */
    public function getRecipient(): string
    {
        return ContactMail::RECIPIENT_EMAIL;
    }

    /**
     * Returns the system information that is sent with the message.
     *
     * @return string
     */
    public function getSystemInformation(): string
    {
        return $this->systemInformation->getText();
    }
}
