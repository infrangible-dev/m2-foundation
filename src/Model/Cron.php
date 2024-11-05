<?php

declare(strict_types=1);

namespace Infrangible\Foundation\Model;

use Exception;
use FeWeDev\Base\Arrays;
use FeWeDev\Base\Json;
use FeWeDev\Base\Variables;
use Infrangible\Foundation\Helper\Data;
use Magento\AdminNotification\Model\InboxFactory;
use Magento\Framework\FlagManager;
use Psr\Log\LoggerInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Cron
{
    /** @var Variables */
    protected $variables;

    /** @var Arrays */
    protected $arrays;

    /** @var Json */
    protected $json;

    /** @var FlagManager */
    protected $flagManager;

    /** @var Data */
    protected $helper;

    /** @var LoggerInterface */
    protected $logging;

    /** @var InboxFactory */
    protected $inboxFactory;

    public function __construct(
        Variables $variables,
        Arrays $arrays,
        Json $json,
        FlagManager $flagManager,
        Data $helper,
        LoggerInterface $logging,
        InboxFactory $inboxFactory
    ) {
        $this->variables = $variables;
        $this->arrays = $arrays;
        $this->json = $json;
        $this->flagManager = $flagManager;
        $this->helper = $helper;

        $this->logging = $logging;
        $this->inboxFactory = $inboxFactory;
    }

    /**
     * @throws Exception
     */
    public function checkUpdate(): void
    {
        $availablePackages = $this->flagManager->getFlagData('infrangible_foundation_available_packages');

        if ($availablePackages === null) {
            $availablePackages = $this->helper->getAvailablePackages();
        }

        $lastCheckTime = $this->flagManager->getFlagData('infrangible_foundation_check_time');

        if ($lastCheckTime === null) {
            $lastCheckTime = time();
        }

        $updatePackageNames = $this->helper->getUpdatedPackages($lastCheckTime);

        $this->flagManager->saveFlag(
            'infrangible_foundation_check_time',
            time()
        );

        foreach ($updatePackageNames as $packageName) {
            if (array_key_exists(
                $packageName,
                $availablePackages
            )) {
                $availablePackage = $availablePackages[ $packageName ];

                $previousUpdateTime = strtotime($availablePackage[ 'time' ]);
            } else {
                $previousUpdateTime = 0;
            }

            $availablePackage = $this->helper->getAvailablePackage($packageName);

            $newUpdateTime = strtotime($availablePackage[ 'time' ]);

            $availablePackages[ $packageName ] = $availablePackage;

            if ($newUpdateTime > $previousUpdateTime) {
                $model = $this->inboxFactory->create();

                $model->addNotice(
                    sprintf(
                        '%s: %s',
                        __('New Infrangible Solution'),
                        trim(
                            $this->arrays->getValue(
                                $availablePackage,
                                'name'
                            )
                        )
                    ),
                    sprintf(
                        '%s: %s',
                        __('New Version'),
                        trim(
                            $this->arrays->getValue(
                                $availablePackage,
                                'version'
                            )
                        )
                    ),
                    sprintf(
                        'https://packagist.org/packages/%s',
                        trim(
                            $this->arrays->getValue(
                                $availablePackage,
                                'name'
                            )
                        )
                    ),
                    false
                );
            }
        }

        $this->flagManager->saveFlag(
            'infrangible_foundation_available_packages',
            $availablePackages
        );
    }
}
