<?php

namespace Infrangible\Foundation\Block\Adminhtml;

use Exception;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Psr\Log\LoggerInterface;
use Infrangible\Foundation\Helper\Data;
use Tofex\Help\Arrays;
use Tofex\Help\Variables;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Solutions
    extends Template
{
    /** @var Data */
    protected $helper;

    /** @var Variables */
    protected $variableHelper;

    /** @var Arrays */
    protected $arrayHelper;

    /** @var LoggerInterface */
    protected $logging;

    /**
     * @param Context   $context
     * @param Data      $helper
     * @param Variables $variableHelper
     * @param Arrays    $arrayHelper
     * @param array     $data
     */
    public function __construct(
        Context $context,
        Data $helper,
        Variables $variableHelper,
        Arrays $arrayHelper,
        array $data = [])
    {
        parent::__construct($context, $data);

        $this->helper = $helper;
        $this->variableHelper = $variableHelper;
        $this->arrayHelper = $arrayHelper;

        $this->logging = $context->getLogger();
    }

    /**
     * @return array[]
     */
    public function getItems(): array
    {
        return $this->helper->getItems();
    }

    /**
     * @return bool
     */
    public function hasTag(): bool
    {
        try {
            $tag = $this->getRequest()->getParam('tag');
        } catch (Exception $exception) {
            $this->logging->error($exception);

            $tag = null;
        }

        return ! $this->variableHelper->isEmpty($tag);
    }

    /**
     * @return Arrays
     */
    public function getArrayHelper(): Arrays
    {
        return $this->arrayHelper;
    }
}
