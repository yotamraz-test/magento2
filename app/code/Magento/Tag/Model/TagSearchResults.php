<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Framework\Api\SearchResults;
use Magento\Tag\Api\Data\TagSearchResultsInterface;

/**
 * Service Data Object with Tag search results.
 */
class TagSearchResults extends SearchResults implements TagSearchResultsInterface
{
}
