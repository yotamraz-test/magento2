<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Magento\Tag\Api\Data\TagInterface;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\ResourceModel\Tag\Collection;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\Tag\Relation;
use Magento\Tag\Model\Tag\RelationFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for the Tag model.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TagTest extends TestCase
{
    private const TAG_ID = 42;
    private const CUSTOMER_ID = 10;
    private const PRODUCT_ID = 100;
    private const STORE_ID = 1;

    /**
     * @var Tag
     */
    private Tag $tag;

    /**
     * @var TagResource|MockObject
     */
    private $tagResourceMock;

    /**
     * @var Collection|MockObject
     */
    private $collectionMock;

    /**
     * @var RelationFactory|MockObject
     */
    private $relationFactoryMock;

    protected function setUp(): void
    {
        $this->tagResourceMock = $this->createMock(TagResource::class);
        $this->tagResourceMock->method('getIdFieldName')->willReturn('tag_id');

        $this->collectionMock = $this->createMock(Collection::class);

        $this->relationFactoryMock = $this->getMockBuilder(RelationFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();

        $objectManagerHelper = new ObjectManagerHelper($this);
        $this->tag = $objectManagerHelper->getObject(
            Tag::class,
            [
                'resource' => $this->tagResourceMock,
                'resourceCollection' => $this->collectionMock,
                'relationFactory' => $this->relationFactoryMock,
                'data' => ['tag_id' => self::TAG_ID],
            ]
        );
    }

    /**
     * Build a partial Relation mock that keeps the real setData/getData but stubs the DB-touching methods.
     */
    private function makeRelationMock(): Relation
    {
        return $this->getMockBuilder(Relation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getProductIds', 'save', 'loadByTagCustomerProduct', 'getId'])
            ->getMock();
    }

    public function testSaveRelationApprovedExistingLinkCreatesForNewCustomer(): void
    {
        $this->tag->setStatus(TagInterface::STATUS_APPROVED);

        $relationModel = $this->makeRelationMock();
        $lookup = $this->makeRelationMock();

        // Link exists for the store (any customer).
        $relationModel->method('getProductIds')->willReturn([self::PRODUCT_ID]);
        // No existing relation for this customer.
        $lookup->method('getId')->willReturn(null);
        $lookup->expects($this->once())
            ->method('loadByTagCustomerProduct')
            ->with(self::TAG_ID, self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID)
            ->willReturnSelf();
        $relationModel->expects($this->once())->method('save')->willReturnSelf();
        $lookup->expects($this->never())->method('save');

        $this->relationFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($relationModel, $lookup);

        $result = $this->tag->saveRelation(self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID);

        $this->assertSame(Tag::ADD_STATUS_EXIST, $result);
    }

    public function testSaveRelationApprovedExistingLinkReactivatesInactiveRelation(): void
    {
        $this->tag->setStatus(TagInterface::STATUS_APPROVED);

        $relationModel = $this->makeRelationMock();
        $lookup = $this->makeRelationMock();

        $existingRelationId = 999;

        $relationModel->method('getProductIds')->willReturn([self::PRODUCT_ID]);
        $lookup->method('getId')->willReturn($existingRelationId);
        $lookup->setData('active', 0);
        $lookup->expects($this->once())
            ->method('loadByTagCustomerProduct')
            ->with(self::TAG_ID, self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID)
            ->willReturnSelf();
        $relationModel->expects($this->once())->method('save')->willReturnSelf();
        $lookup->expects($this->never())->method('save');

        $this->relationFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($relationModel, $lookup);

        $result = $this->tag->saveRelation(self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID);

        $this->assertSame(Tag::ADD_STATUS_EXIST, $result);
        $this->assertSame($existingRelationId, $relationModel->getData('tag_relation_id'));
    }

    public function testSaveRelationApprovedExistingLinkNoNewSave(): void
    {
        $this->tag->setStatus(TagInterface::STATUS_APPROVED);

        $relationModel = $this->makeRelationMock();
        $lookup = $this->makeRelationMock();

        $relationModel->method('getProductIds')->willReturn([self::PRODUCT_ID]);
        $lookup->method('getId')->willReturn(777);
        $lookup->setData('active', 1);
        $lookup->expects($this->once())
            ->method('loadByTagCustomerProduct')
            ->with(self::TAG_ID, self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID)
            ->willReturnSelf();
        $relationModel->expects($this->never())->method('save');
        $lookup->expects($this->never())->method('save');

        $this->relationFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($relationModel, $lookup);

        $result = $this->tag->saveRelation(self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID);

        $this->assertSame(Tag::ADD_STATUS_EXIST, $result);
    }

    public function testSaveRelationApprovedNewRelationReturnsSuccess(): void
    {
        $this->tag->setStatus(TagInterface::STATUS_APPROVED);

        $relationModel = $this->makeRelationMock();
        // No existing link in the store (any customer).
        $relationModel->method('getProductIds')->willReturn([]);
        $relationModel->expects($this->once())->method('save')->willReturnSelf();

        // Only one factory call — the APPROVED/no-link branch does not look up the per-customer relation.
        $this->relationFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($relationModel);

        $result = $this->tag->saveRelation(self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID);

        $this->assertSame(Tag::ADD_STATUS_SUCCESS, $result);
    }

    public function testSaveRelationPendingNewRelation(): void
    {
        $this->tag->setStatus(TagInterface::STATUS_PENDING);

        $relationModel = $this->makeRelationMock();
        $lookup = $this->makeRelationMock();

        // No existing relation for this customer.
        $lookup->method('getId')->willReturn(null);
        $lookup->expects($this->once())
            ->method('loadByTagCustomerProduct')
            ->with(self::TAG_ID, self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID)
            ->willReturnSelf();
        $relationModel->expects($this->once())->method('save')->willReturnSelf();
        $lookup->expects($this->never())->method('save');

        $this->relationFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($relationModel, $lookup);

        $result = $this->tag->saveRelation(self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID);

        $this->assertSame(Tag::ADD_STATUS_NEW, $result);
    }

    public function testSaveRelationPendingExistingInactiveReactivates(): void
    {
        $this->tag->setStatus(TagInterface::STATUS_PENDING);

        $relationModel = $this->makeRelationMock();
        $lookup = $this->makeRelationMock();

        $existingRelationId = 555;
        $lookup->method('getId')->willReturn($existingRelationId);
        $lookup->setData('active', 0);
        $lookup->expects($this->once())
            ->method('loadByTagCustomerProduct')
            ->with(self::TAG_ID, self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID)
            ->willReturnSelf();
        $relationModel->expects($this->once())->method('save')->willReturnSelf();
        $lookup->expects($this->never())->method('save');

        $this->relationFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($relationModel, $lookup);

        $result = $this->tag->saveRelation(self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID);

        $this->assertSame(Tag::ADD_STATUS_NEW, $result);
        $this->assertSame($existingRelationId, $relationModel->getData('tag_relation_id'));
    }

    public function testSaveRelationDisabledExistingRelationRejected(): void
    {
        $this->tag->setStatus(TagInterface::STATUS_DISABLED);

        $relationModel = $this->makeRelationMock();
        $lookup = $this->makeRelationMock();

        // checkLinkBetweenTagCustomerProduct uses the lookup's product id list.
        $lookup->method('getProductIds')->willReturn([self::PRODUCT_ID]);
        $lookup->expects($this->once())
            ->method('loadByTagCustomerProduct')
            ->with(self::TAG_ID, self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID)
            ->willReturnSelf();
        $relationModel->expects($this->never())->method('save');
        $lookup->expects($this->never())->method('save');

        $this->relationFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($relationModel, $lookup);

        // Tag->save() must not be invoked when the request is rejected.
        $this->tagResourceMock->expects($this->never())->method('save');

        $result = $this->tag->saveRelation(self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID);

        $this->assertSame(Tag::ADD_STATUS_REJECTED, $result);
    }

    public function testSaveRelationDisabledNoExistingPromotesToPending(): void
    {
        $this->tag->setStatus(TagInterface::STATUS_DISABLED);

        $relationModel = $this->makeRelationMock();
        $lookup = $this->makeRelationMock();

        $lookup->method('getProductIds')->willReturn([]);
        $lookup->expects($this->once())
            ->method('loadByTagCustomerProduct')
            ->with(self::TAG_ID, self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID)
            ->willReturnSelf();
        $relationModel->expects($this->once())->method('save')->willReturnSelf();
        $lookup->expects($this->never())->method('save');

        $this->relationFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($relationModel, $lookup);

        // Tag::save() delegates to the resource.
        $this->tagResourceMock->expects($this->once())
            ->method('save')
            ->with($this->tag)
            ->willReturnSelf();

        $result = $this->tag->saveRelation(self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID);

        $this->assertSame(Tag::ADD_STATUS_NEW, $result);
        $this->assertSame(TagInterface::STATUS_PENDING, (int)$this->tag->getStatus());
    }

    public function testBeforeSaveTruncatesNameTo255(): void
    {
        $longName = str_repeat('a', 300);
        $this->tag->setName($longName);

        $this->tag->beforeSave();

        $truncated = $this->tag->getName();
        $this->assertSame(255, mb_strlen($truncated));
        $this->assertSame(str_repeat('a', 255), $truncated);
    }

    public function testIsAvailableInStoreReturnsTrueForNullStore(): void
    {
        $this->assertTrue($this->tag->isAvailableInStore(null));
    }

    public function testIsAvailableInStoreReturnsFalseForMissingStore(): void
    {
        $this->tag->setVisibleInStoreIds([1, 2]);
        $this->assertFalse($this->tag->isAvailableInStore(5));
    }

    public function testIsAvailableInStoreReturnsTrueForVisibleStore(): void
    {
        $this->tag->setVisibleInStoreIds([1, 2, 3]);
        $this->assertTrue($this->tag->isAvailableInStore(2));
    }

    public function testGetIdentitiesReturnsCacheTag(): void
    {
        $this->assertSame(
            [Tag::CACHE_TAG . '_' . self::TAG_ID],
            $this->tag->getIdentities()
        );
    }
}
