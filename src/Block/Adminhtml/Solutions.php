<?php

declare(strict_types=1);

namespace Infrangible\Foundation\Block\Adminhtml;

use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Infrangible\Foundation\Helper\Data;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Psr\Log\LoggerInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Solutions
    extends Template
{
    /** @var Data */
    protected $helper;

    /** @var Variables */
    protected $variables;

    /** @var Arrays */
    protected $arrays;

    /** @var LoggerInterface */
    protected $logging;

    /**
     * @param Context $context
     * @param Data $helper
     * @param Variables $variables
     * @param Arrays $arrayHelper
     * @param array $data
     */
    public function __construct(
        Context           $context,
        Data              $helper,
        Variables         $variables,
        Arrays            $arrayHelper,
        array             $data = [])
    {
        parent::__construct($context, $data);

        $this->helper = $helper;
        $this->variables = $variables;
        $this->arrays = $arrayHelper;

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
     * @return Arrays
     */
    public function getArrays(): Arrays
    {
        return $this->arrays;
    }
}
