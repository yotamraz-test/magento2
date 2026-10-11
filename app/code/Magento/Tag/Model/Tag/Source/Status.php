<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\Tag\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Tag\Api\Data\TagInterface;

/**
 * Tag status source model for UI components.
 */
class Status implements OptionSourceInterface
{
    /**
     * @var array|null
     */
    private $options;

    /**
     * Get option array for tag statuses.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $this->options = [
                [
                    'label' => __('Approved'),
                    'value' => TagInterface::STATUS_APPROVED,
                ],
                [
                    'label' => __('Pending'),
                    'value' => TagInterface::STATUS_PENDING,
                ],
                [
                    'label' => __('Disabled'),
                    'value' => TagInterface::STATUS_DISABLED,
                ],
            ];
        }

        return $this->options;
    }

    /**
     * Get all options. Alias for toOptionArray().
     *
     * @return array
     */
    public function getAllOptions(): array
    {
        return $this->toOptionArray();
    }

    /**
     * Get tag statuses as an associative array (value => label).
     *
     * @return array
     */
    public function getStatusesArray(): array
    {
        return [
            TagInterface::STATUS_DISABLED => __('Disabled'),
            TagInterface::STATUS_PENDING  => __('Pending'),
            TagInterface::STATUS_APPROVED => __('Approved'),
        ];
    }

    /**
     * Get tag statuses as an options array for UI dropdowns.
     *
     * @return array
     */
    public function getStatusesOptionsArray(): array
    {
        return [
            [
                'label' => __('Disabled'),
                'value' => TagInterface::STATUS_DISABLED,
            ],
            [
                'label' => __('Pending'),
                'value' => TagInterface::STATUS_PENDING,
            ],
            [
                'label' => __('Approved'),
                'value' => TagInterface::STATUS_APPROVED,
            ],
        ];
    }
}
