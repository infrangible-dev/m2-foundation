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

    /**
     * @param Variables       $variables
     * @param Arrays          $arrays
     * @param Json            $json
     * @param FlagManager     $flagManager
     * @param Data            $helper
     * @param LoggerInterface $logging
     * @param InboxFactory    $inboxFactory
     */
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
    public function checkUpdate()
    {
        $lastSolutionDate = $this->flagManager->getFlagData('infrangible_foundation_solution_date');
        $packageVersions = $this->flagManager->getFlagData('infrangible_foundation_package_versions');

        $lastSolutionDate = $this->variables->isEmpty($lastSolutionDate) ? time() : $lastSolutionDate;

        $packageVersions = $this->variables->isEmpty($packageVersions) ? [] : $packageVersions;

        $latestTime = $lastSolutionDate;
        $latestItem = [];

        foreach ($this->helper->getLatestItems() as $guid => $item) {
            $pubDate = strtotime(trim($this->arrays->getValue($item, 'pubDate')));

            if ($pubDate > $latestTime) {
                $latestTime = $pubDate;
                $latestItem = $item;
            }

            $packageVersions[$guid] = $item;
        }

        if ($latestTime > $lastSolutionDate) {
            $model = $this->inboxFactory->create();

            $model->addNotice(
                sprintf(
                    '%s: %s',
                    __('New Infrangible Solution'),
                    trim($this->arrays->getValue($latestItem, 'title'))
                ),
                trim($this->arrays->getValue($latestItem, 'description')),
                trim($this->arrays->getValue($latestItem, 'link')),
                false
            );
        }

        $this->flagManager->saveFlag('infrangible_foundation_solution_date', $latestTime);
        $this->flagManager->saveFlag('infrangible_foundation_package_versions', $packageVersions);
    }
}
