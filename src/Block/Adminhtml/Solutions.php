<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Block\Adminhtml;

use Infrangible\Foundation\Helper\Data;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * Lists the available Infrangible solutions with their installation status.
 */
class Solutions extends Template
{
    /** @var Data */
    protected Data $helper;

    /**
     * @param Context $context
     * @param Data $helper
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(Context $context, Data $helper, array $data = [])
    {
        parent::__construct(
            $context,
            $data
        );

        $this->helper = $helper;
    }

    /**
     * Returns the packages with installed version and status.
     *
     * @return array<int, array{name: string, description: string, version: string, time: string, installed: ?string,
     *     status: string}>
     */
    public function getItems(): array
    {
        return $this->helper->getItems();
    }

    /**
     * Checks whether the package list has been loaded from Packagist.
     *
     * @return bool
     */
    public function isLoaded(): bool
    {
        return $this->helper->getCachedPackages() !== null;
    }

    /**
     * Returns the URL that loads the package list from Packagist.
     *
     * @return string
     */
    public function getRefreshUrl(): string
    {
        return $this->getUrl('*/*/refresh');
    }

    /**
     * Returns the Packagist page of the package.
     *
     * @param string $packageName
     * @return string
     */
    public function getPackageUrl(string $packageName): string
    {
        return sprintf(
            'https://packagist.org/packages/%s',
            $packageName
        );
    }
}
