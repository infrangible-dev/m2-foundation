<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Controller\Adminhtml\Contact;

use Exception;
use Infrangible\Foundation\Model\ContactMail;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\User\Model\User;

/**
 * Sends the contact form; on an error the message is kept for the form.
 */
class Send extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Infrangible_Foundation::infrangible_contact';

    public const DATA_PERSISTOR_KEY = 'infrangible_foundation_contact_message';

    /**
     * @param Context $context
     * @param ContactMail $contactMail
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        Context $context,
        protected ContactMail $contactMail,
        protected DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        $message = $this->getRequest()->getParam('message');
        $message = is_string($message) ? $message : '';

        $user = $this->_auth->getUser();

        try {
            if (! $user instanceof User) {
                throw new LocalizedException(__('The admin user could not be determined.'));
            }

            $this->contactMail->send(
                $message,
                $user
            );

            $this->dataPersistor->clear(static::DATA_PERSISTOR_KEY);

            $this->messageManager->addSuccessMessage(
                (string)__('Thank you for your message. We will get back to you as soon as possible.')
            );
        } catch (Exception $exception) {
            // keep the message, so that it does not have to be written again
            $this->dataPersistor->set(
                static::DATA_PERSISTOR_KEY,
                $message
            );

            // a rejected message has a reason for the user, a mail error points to the mail setup of the shop
            if ($exception instanceof LocalizedException && ! $exception instanceof MailException) {
                $this->messageManager->addErrorMessage($exception->getMessage());
            } else {
                $this->messageManager->addExceptionMessage(
                    $exception,
                    (string)__('The message could not be sent. Please check the mail configuration of the shop.')
                );
            }
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
