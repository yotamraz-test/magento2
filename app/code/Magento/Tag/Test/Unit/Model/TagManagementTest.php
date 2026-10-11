<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tag\Api\Data\TagInterface;
use Magento\Tag\Helper\Data as TagHelper;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\TagFactory;
use Magento\Tag\Model\TagManagement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TagManagementTest extends TestCase
{
    private const CUSTOMER_ID = 7;
    private const PRODUCT_ID  = 42;
    private const STORE_ID    = 1;

    private TagManagement $service;

    /** @var TagFactory|MockObject */
    private $tagFactoryMock;

    /** @var TagResource|MockObject */
    private $tagResourceMock;

    /** @var TagHelper|MockObject */
    private $tagHelperMock;

    /** @var CustomerSession|MockObject */
    private $customerSessionMock;

    /** @var StoreManagerInterface|MockObject */
    private $storeManagerMock;

    /** @var UserContextInterface|MockObject */
    private $userContextMock;

    /** @var Tag|MockObject */
    private $tagMock;

    protected function setUp(): void
    {
        $this->tagFactoryMock = $this->getMockBuilder(TagFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();

        $this->tagResourceMock     = $this->createMock(TagResource::class);
        $this->tagHelperMock       = $this->createMock(TagHelper::class);
        $this->customerSessionMock = $this->createMock(CustomerSession::class);
        $this->storeManagerMock    = $this->createMock(StoreManagerInterface::class);
        $this->userContextMock     = $this->createMock(UserContextInterface::class);
        $this->tagMock             = $this->createMock(Tag::class);

        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(self::STORE_ID);
        $this->storeManagerMock->method('getStore')->willReturn($storeMock);

        $this->service = new TagManagement(
            $this->tagFactoryMock,
            $this->tagResourceMock,
            $this->tagHelperMock,
            $this->customerSessionMock,
            $this->storeManagerMock,
            $this->userContextMock
        );
    }

    private function authenticateViaUserContext(int $customerId = self::CUSTOMER_ID): void
    {
        $this->userContextMock->method('getUserType')
            ->willReturn(UserContextInterface::USER_TYPE_CUSTOMER);
        $this->userContextMock->method('getUserId')->willReturn($customerId);
    }

    private function authenticateViaSession(int $customerId = self::CUSTOMER_ID): void
    {
        $this->userContextMock->method('getUserType')
            ->willReturn(UserContextInterface::USER_TYPE_GUEST);
        $this->customerSessionMock->method('getCustomerId')->willReturn($customerId);
    }

    public function testResolveCustomerIdUsesUserContextWhenTypeIsCustomer(): void
    {
        $this->authenticateViaUserContext();

        $this->tagHelperMock->method('cleanTags')->willReturn(['t']);
        $this->tagFactoryMock->method('create')->willReturn($this->tagMock);
        $this->tagMock->method('getId')->willReturn(1);
        $this->tagMock->expects($this->once())->method('saveRelation')
            ->with(self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID)
            ->willReturn(Tag::ADD_STATUS_EXIST);

        $this->service->addTagsToProduct(self::PRODUCT_ID, ['t']);
    }

    public function testResolveCustomerIdFallsBackToSessionWhenUserContextTypeIsNotCustomer(): void
    {
        $this->authenticateViaSession();

        $this->tagHelperMock->method('cleanTags')->willReturn(['t']);
        $this->tagFactoryMock->method('create')->willReturn($this->tagMock);
        $this->tagMock->method('getId')->willReturn(1);
        $this->tagMock->expects($this->once())->method('saveRelation')
            ->with(self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID)
            ->willReturn(Tag::ADD_STATUS_EXIST);

        $this->service->addTagsToProduct(self::PRODUCT_ID, ['t']);
    }

    public function testResolveCustomerIdFallsBackToSessionWhenUserContextIdIsZero(): void
    {
        $this->userContextMock->method('getUserType')
            ->willReturn(UserContextInterface::USER_TYPE_CUSTOMER);
        $this->userContextMock->method('getUserId')->willReturn(0);
        $this->customerSessionMock->method('getCustomerId')->willReturn(self::CUSTOMER_ID);

        $this->tagHelperMock->method('cleanTags')->willReturn(['t']);
        $this->tagFactoryMock->method('create')->willReturn($this->tagMock);
        $this->tagMock->method('getId')->willReturn(1);
        $this->tagMock->expects($this->once())->method('saveRelation')
            ->with(self::CUSTOMER_ID, self::PRODUCT_ID, self::STORE_ID)
            ->willReturn(Tag::ADD_STATUS_EXIST);

        $this->service->addTagsToProduct(self::PRODUCT_ID, ['t']);
    }

    public function testResolveCustomerIdThrowsAuthorizationExceptionWhenUnauthenticated(): void
    {
        $this->userContextMock->method('getUserType')
            ->willReturn(UserContextInterface::USER_TYPE_GUEST);
        $this->customerSessionMock->method('getCustomerId')->willReturn(0);

        $this->expectException(AuthorizationException::class);

        $this->service->addTagsToProduct(self::PRODUCT_ID, ['tag']);
    }

    public function testNewTagIsCreatedAsPendingWhenNotFoundByName(): void
    {
        $this->authenticateViaUserContext();

        $this->tagHelperMock->method('cleanTags')->willReturn(['newtag']);
        $this->tagFactoryMock->method('create')->willReturn($this->tagMock);
        $this->tagMock->method('getId')->willReturn(null);
        $this->tagMock->expects($this->once())->method('setName')->with('newtag');
        $this->tagMock->expects($this->once())->method('setStatus')->with(TagInterface::STATUS_PENDING);
        $this->tagMock->expects($this->once())->method('setFirstCustomerId')->with(self::CUSTOMER_ID);
        $this->tagMock->expects($this->once())->method('setFirstStoreId')->with(self::STORE_ID);
        $this->tagResourceMock->expects($this->once())->method('save')->with($this->tagMock);
        $this->tagMock->method('saveRelation')->willReturn(Tag::ADD_STATUS_NEW);

        $this->service->addTagsToProduct(self::PRODUCT_ID, ['newtag']);
    }

    public function testExistingTagSkipsResourceSave(): void
    {
        $this->authenticateViaUserContext();

        $this->tagHelperMock->method('cleanTags')->willReturn(['existing']);
        $this->tagFactoryMock->method('create')->willReturn($this->tagMock);
        $this->tagMock->method('getId')->willReturn(55);
        $this->tagResourceMock->expects($this->never())->method('save');
        $this->tagMock->method('saveRelation')->willReturn(Tag::ADD_STATUS_EXIST);

        $this->service->addTagsToProduct(self::PRODUCT_ID, ['existing']);
    }

    public function testResourceSaveExceptionIsWrappedInCouldNotSaveException(): void
    {
        $this->authenticateViaUserContext();

        $this->tagHelperMock->method('cleanTags')->willReturn(['badtag']);
        $this->tagFactoryMock->method('create')->willReturn($this->tagMock);
        $this->tagMock->method('getId')->willReturn(null);
        $this->tagResourceMock->method('save')
            ->willThrowException(new \Exception('db error'));

        $this->expectException(CouldNotSaveException::class);

        $this->service->addTagsToProduct(self::PRODUCT_ID, ['badtag']);
    }

    public function testAggregateIsCalledOnSuccessStatus(): void
    {
        $this->authenticateViaUserContext();

        $this->tagHelperMock->method('cleanTags')->willReturn(['t']);
        $this->tagFactoryMock->method('create')->willReturn($this->tagMock);
        $this->tagMock->method('getId')->willReturn(1);
        $this->tagMock->method('saveRelation')->willReturn(Tag::ADD_STATUS_SUCCESS);
        $this->tagMock->expects($this->once())->method('aggregate');

        $this->service->addTagsToProduct(self::PRODUCT_ID, ['t']);
    }

    public function testAggregateIsCalledOnNewStatus(): void
    {
        $this->authenticateViaUserContext();

        $this->tagHelperMock->method('cleanTags')->willReturn(['t']);
        $this->tagFactoryMock->method('create')->willReturn($this->tagMock);
        $this->tagMock->method('getId')->willReturn(1);
        $this->tagMock->method('saveRelation')->willReturn(Tag::ADD_STATUS_NEW);
        $this->tagMock->expects($this->once())->method('aggregate');

        $this->service->addTagsToProduct(self::PRODUCT_ID, ['t']);
    }

    public function testAggregateIsNotCalledOnExistStatus(): void
    {
        $this->authenticateViaUserContext();

        $this->tagHelperMock->method('cleanTags')->willReturn(['t']);
        $this->tagFactoryMock->method('create')->willReturn($this->tagMock);
        $this->tagMock->method('getId')->willReturn(1);
        $this->tagMock->method('saveRelation')->willReturn(Tag::ADD_STATUS_EXIST);
        $this->tagMock->expects($this->never())->method('aggregate');

        $this->service->addTagsToProduct(self::PRODUCT_ID, ['t']);
    }

    public function testAggregateIsNotCalledOnRejectedStatus(): void
    {
        $this->authenticateViaUserContext();

        $this->tagHelperMock->method('cleanTags')->willReturn(['t']);
        $this->tagFactoryMock->method('create')->willReturn($this->tagMock);
        $this->tagMock->method('getId')->willReturn(1);
        $this->tagMock->method('saveRelation')->willReturn(Tag::ADD_STATUS_REJECTED);
        $this->tagMock->expects($this->never())->method('aggregate');

        $this->service->addTagsToProduct(self::PRODUCT_ID, ['t']);
    }

    public function testReturnsResultsKeyedByTagName(): void
    {
        $this->authenticateViaUserContext();

        $this->tagHelperMock->method('cleanTags')->willReturn(['foo', 'bar']);

        $tagMock1 = $this->createMock(Tag::class);
        $tagMock1->method('getId')->willReturn(10);
        $tagMock1->method('saveRelation')->willReturn(Tag::ADD_STATUS_SUCCESS);

        $tagMock2 = $this->createMock(Tag::class);
        $tagMock2->method('getId')->willReturn(null);
        $tagMock2->method('saveRelation')->willReturn(Tag::ADD_STATUS_NEW);

        $this->tagFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($tagMock1, $tagMock2);

        $results = $this->service->addTagsToProduct(self::PRODUCT_ID, ['foo', 'bar']);

        $this->assertSame(
            ['foo' => Tag::ADD_STATUS_SUCCESS, 'bar' => Tag::ADD_STATUS_NEW],
            $results
        );
    }
}
