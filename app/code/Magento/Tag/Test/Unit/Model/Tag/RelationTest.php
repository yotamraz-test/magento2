<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model\Tag;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Magento\Tag\Model\ResourceModel\Tag\Relation as RelationResource;
use Magento\Tag\Model\Tag\Relation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for the Tag relation model.
 */
class RelationTest extends TestCase
{
    /**
     * @var Relation
     */
    private Relation $relation;

    /**
     * @var RelationResource|MockObject
     */
    private $resourceMock;

    protected function setUp(): void
    {
        $this->resourceMock = $this->createMock(RelationResource::class);

        $objectManagerHelper = new ObjectManagerHelper($this);
        $this->relation = $objectManagerHelper->getObject(
            Relation::class,
            ['resource' => $this->resourceMock]
        );
    }

    public function testLoadByTagCustomerProductSetsDataAndCallsResource(): void
    {
        $tagId = 11;
        $customerId = 22;
        $productId = 33;
        $storeId = 44;

        $this->resourceMock->expects($this->once())
            ->method('loadByTagCustomer')
            ->with($this->relation)
            ->willReturnSelf();

        $result = $this->relation->loadByTagCustomerProduct($tagId, $customerId, $productId, $storeId);

        $this->assertSame($this->relation, $result);
        $this->assertSame($productId, $this->relation->getData('product_id'));
        $this->assertSame($tagId, $this->relation->getData('tag_id'));
        $this->assertSame($customerId, $this->relation->getData('customer_id'));
        $this->assertSame($storeId, $this->relation->getData('store_id'));
    }

    public function testGetProductIdsCachesAndCallsResource(): void
    {
        $ids = [1, 2, 3];

        $this->resourceMock->expects($this->once())
            ->method('getProductIds')
            ->with($this->relation)
            ->willReturn($ids);

        $first = $this->relation->getProductIds();
        $second = $this->relation->getProductIds();

        $this->assertSame($ids, $first);
        $this->assertSame($ids, $second);
        $this->assertSame($ids, $this->relation->getData('product_ids'));
    }

    public function testGetRelatedTagIdsCachesAndCallsResource(): void
    {
        $tagIds = [10, 20, 30];

        $this->resourceMock->expects($this->once())
            ->method('getRelatedTagIds')
            ->with($this->relation)
            ->willReturn($tagIds);

        $first = $this->relation->getRelatedTagIds();
        $second = $this->relation->getRelatedTagIds();

        $this->assertSame($tagIds, $first);
        $this->assertSame($tagIds, $second);
        $this->assertSame($tagIds, $this->relation->getData('related_tag_ids'));
    }

    public function testDeactivateSetsActiveZeroAndSaves(): void
    {
        $this->resourceMock->expects($this->once())
            ->method('save')
            ->with($this->relation)
            ->willReturnSelf();

        $result = $this->relation->deactivate();

        $this->assertSame($this->relation, $result);
        $this->assertSame(Relation::STATUS_NOT_ACTIVE, $this->relation->getData('active'));
    }

    public function testActivateSetsActiveOneAndSaves(): void
    {
        $this->resourceMock->expects($this->once())
            ->method('save')
            ->with($this->relation)
            ->willReturnSelf();

        $result = $this->relation->activate();

        $this->assertSame($this->relation, $result);
        $this->assertSame(Relation::STATUS_ACTIVE, $this->relation->getData('active'));
    }

    public function testAddRelationsSingleTagSetsTagIdToSingleValue(): void
    {
        $tagIds = [55];
        $productIds = [100, 200];
        $customerId = 7;
        $storeId = 2;

        $this->resourceMock->expects($this->once())
            ->method('addRelations')
            ->with($this->relation)
            ->willReturnSelf();

        $result = $this->relation->addRelations($tagIds, $productIds, $customerId, $storeId);

        $this->assertSame($this->relation, $result);
        $this->assertSame($productIds, $this->relation->getData('added_product_ids'));
        $this->assertSame(55, $this->relation->getData('tag_id'));
        $this->assertSame($tagIds, $this->relation->getData('related_tag_ids'));
        $this->assertSame($customerId, $this->relation->getData('customer_id'));
        $this->assertSame($storeId, $this->relation->getData('store_id'));
    }

    public function testAddRelationsMultipleTagsSetsTagIdNull(): void
    {
        $tagIds = [55, 66, 77];
        $productIds = [100];
        $customerId = 7;
        $storeId = 2;

        $this->resourceMock->expects($this->once())
            ->method('addRelations')
            ->with($this->relation)
            ->willReturnSelf();

        $result = $this->relation->addRelations($tagIds, $productIds, $customerId, $storeId);

        $this->assertSame($this->relation, $result);
        $this->assertSame($productIds, $this->relation->getData('added_product_ids'));
        $this->assertNull($this->relation->getData('tag_id'));
        $this->assertSame($tagIds, $this->relation->getData('related_tag_ids'));
        $this->assertSame($customerId, $this->relation->getData('customer_id'));
        $this->assertSame($storeId, $this->relation->getData('store_id'));
    }
}
