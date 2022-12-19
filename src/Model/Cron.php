<?php

namespace Infrangible\Foundation\Model;

use Exception;
use Magento\AdminNotification\Model\InboxFactory;
use Pest;
use Psr\Log\LoggerInterface;
use Infrangible\Core\Helper\Database;
use Infrangible\Foundation\Helper\Data;
use Tofex\Help\Arrays;
use Tofex\Help\Json;
use Tofex\Help\Variables;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Cron
{
    /** @var Variables */
    protected $variableHelper;

    /** @var Arrays */
    protected $arrayHelper;

    /** @var Json */
    protected $jsonHelper;

    /** @var Database */
    protected $databaseHelper;

    /** @var Data */
    protected $helper;

    /** @var LoggerInterface */
    protected $logging;

    /** @var InboxFactory */
    protected $inboxFactory;

    /**
     * @param Variables       $variableHelper
     * @param Arrays          $arrayHelper
     * @param Json            $jsonHelper
     * @param Database        $databaseHelper
     * @param Data            $helper
     * @param LoggerInterface $logging
     * @param InboxFactory    $inboxFactory
     */
    public function __construct(
        Variables $variableHelper,
        Arrays $arrayHelper,
        Json $jsonHelper,
        Database $databaseHelper,
        Data $helper,
        LoggerInterface $logging,
        InboxFactory $inboxFactory)
    {
        $this->variableHelper = $variableHelper;
        $this->arrayHelper = $arrayHelper;
        $this->jsonHelper = $jsonHelper;
        $this->databaseHelper = $databaseHelper;
        $this->helper = $helper;

        $this->logging = $logging;
        $this->inboxFactory = $inboxFactory;
    }

    /**
     * @throws Exception
     */
    public function checkSolutions()
    {
        $dateQuery = $this->databaseHelper->select($this->databaseHelper->getTableName('core_config_data'), ['value']);

        $dateQuery->where('path = ?', 'infrangible_foundation/solution/date');

        $dateQueryResult = $this->databaseHelper->fetchOne($dateQuery);

        $lastSolutionDate = $this->variableHelper->isEmpty($dateQueryResult) ? time() : $dateQueryResult;

        $latestTime = 0;
        $latestItem = [];

        foreach ($this->helper->getItems() as $item) {
            $pubDate = strtotime(trim($this->arrayHelper->getValue($item, 'pubDate')));

            if ($pubDate > $latestTime) {
                $latestTime = $pubDate;
                $latestItem = $item;
            }
        }

        if ($latestTime > $lastSolutionDate) {
            $model = $this->inboxFactory->create();

            $model->addNotice(sprintf('%s: %s', __('New Infrangible Solution'),
                trim($this->arrayHelper->getValue($latestItem, 'title'))),
                trim($this->arrayHelper->getValue($latestItem, 'description')),
                trim($this->arrayHelper->getValue($latestItem, 'link')), false);

            $this->databaseHelper->createTableData($this->databaseHelper->getDefaultConnection(),
                $this->databaseHelper->getTableName('core_config_data'), [
                    'scope'    => 'default',
                    'scope_id' => 0,
                    'path'     => 'infrangible_foundation/solution/date',
                    'value'    => $latestTime
                ], true);
        }
    }

    /**
     * @throws Exception
     */
    public function checkPackages()
    {
        $versionQuery =
            $this->databaseHelper->select($this->databaseHelper->getTableName('core_config_data'), ['value']);

        $versionQuery->where('path = ?', 'infrangible_foundation/package/versions');

        $versionQueryResult = $this->databaseHelper->fetchOne($versionQuery);

        $previousCheckInfrangiblePackageVersions =
            $this->variableHelper->isEmpty($versionQueryResult) ? $this->helper->getInstalledInfrangiblePackageVersions() :
                $this->jsonHelper->decode($versionQueryResult);

        $latestInfrangiblePackageVersions = $this->helper->getLatestInfrangiblePackageVersions();

        foreach ($previousCheckInfrangiblePackageVersions as $packageName => $packageVersion) {
            $latestVersion = $this->arrayHelper->getValue($latestInfrangiblePackageVersions, $packageName);

            if (strnatcasecmp($latestVersion, $packageVersion) > 0) {
                $description = null;

                try {
                    $restClient = new Pest('https://composer.infrangible.dev');

                    $url = sprintf('release/version/repository/%s/version/%s', base64_encode($packageName),
                        base64_encode($latestVersion));

                    $data = $this->jsonHelper->decode($restClient->get($url));

                    $description = $this->arrayHelper->getValue($data, 'description');
                } catch (Exception $exception) {
                    $this->logging->error($exception);
                }

                $model = $this->inboxFactory->create();

                $model->addNotice(sprintf('%s - %s: %s', __('Infrangible Solution'), $description, __('New Version')),
                    sprintf('%s: %s', __('New Version'), $latestVersion), null, false);
            }
        }

        $this->databaseHelper->createTableData($this->databaseHelper->getDefaultConnection(),
            $this->databaseHelper->getTableName('core_config_data'), [
                'scope'    => 'default',
                'scope_id' => 0,
                'path'     => 'infrangible_foundation/package/versions',
                'value'    => $this->jsonHelper->encode($latestInfrangiblePackageVersions)
            ], true);
    }
}
