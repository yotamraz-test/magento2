<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Tag\Api\Data\TagInterface;

/**
 * Tag helper
 */
class Data extends AbstractHelper
{
    /**
     * @param Context $context
     */
    public function __construct(
        Context $context
    ) {
        parent::__construct($context);
    }

    /**
     * Check tags on the correctness of symbols and split string to array of tags.
     *
     * Splits a space-delimited string preserving single-quoted phrases via regex.
     *
     * @param string $string
     * @return array
     */
    public function extractTags(string $string): array
    {
        return explode("\n", preg_replace("/(\'(.*?)\')|(\s+)/i", "$1\n", $string));
    }

    /**
     * Clear tags from the separating characters.
     *
     * Trims quotes and whitespace from each tag, removes empty entries.
     *
     * @param array $tags
     * @return array
     */
    public function cleanTags(array $tags): array
    {
        foreach (array_keys($tags) as $key) {
            $tags[$key] = trim($tags[$key], "'");
            $tags[$key] = trim($tags[$key]);
            if ($tags[$key] == '') {
                unset($tags[$key]);
            }
        }

        return $tags;
    }

    /**
     * Get tag statuses as an associative array.
     *
     * @return array
     */
    public function getStatusesArray(): array
    {
        return [
            TagInterface::STATUS_APPROVED => __('Approved'),
            TagInterface::STATUS_PENDING => __('Pending'),
            TagInterface::STATUS_DISABLED => __('Disabled'),
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
