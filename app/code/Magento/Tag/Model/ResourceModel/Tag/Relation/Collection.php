<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model\ResourceModel\Tag\Relation;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;
use Magento\Tag\Model\Tag\Relation as RelationModel;

/**
 * Tag Relation collection resource model.
 *
 * Thin scaffold around the tag_relation table; join helpers can be added as callers need them.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(RelationModel::class, RelationResource::class);
    }
}
