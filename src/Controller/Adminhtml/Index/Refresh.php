<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Controller\Adminhtml\Index;

use Exception;
use Infrangible\Foundation\Helper\Data;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;

/**
 * Loads the package list from Packagist on request instead of waiting for the cron job.
 */
class Refresh extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Infrangible_Foundation::infrangible_solutions';

    /** @var Data */
    protected Data $helper;

    /**
     * @param Context $context
     * @param Data $helper
     */
    public function __construct(Context $context, Data $helper)
    {
        parent::__construct($context);

        $this->helper = $helper;
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        try {
            $packages = $this->helper->refreshPackages();

            $this->messageManager->addSuccessMessage(
                (string)__(
                    'Loaded %1 packages from Packagist.',
                    count($packages)
                )
            );
        } catch (Exception $exception) {
            $this->messageManager->addExceptionMessage(
                $exception,
                (string)__('The package list could not be loaded from Packagist.')
            );
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
