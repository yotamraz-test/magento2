<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tag\Api\Data\TagSearchResultsInterface;
use Magento\Tag\Api\Data\TagSearchResultsInterfaceFactory;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\ResourceModel\Tag\Collection;
use Magento\Tag\Model\ResourceModel\Tag\CollectionFactory as TagCollectionFactory;
use Magento\Tag\Model\Tag;
use Magento\Tag\Model\TagFactory;
use Magento\Tag\Model\TagRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for the Tag repository.
 */
class TagRepositoryTest extends TestCase
{
    /**
     * @var TagRepository
     */
    private TagRepository $repository;

    /**
     * @var TagResource|MockObject
     */
    private $resourceMock;

    /**
     * @var TagFactory|MockObject
     */
    private $tagFactoryMock;

    /**
     * @var TagCollectionFactory|MockObject
     */
    private $collectionFactoryMock;

    /**
     * @var TagSearchResultsInterfaceFactory|MockObject
     */
    private $searchResultsFactoryMock;

    /**
     * @var CollectionProcessorInterface|MockObject
     */
    private $collectionProcessorMock;

    /**
     * @var Tag|MockObject
     */
    private $tagMock;

    protected function setUp(): void
    {
        $this->resourceMock = $this->createMock(TagResource::class);
        $this->tagFactoryMock = $this->getMockBuilder(TagFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->collectionFactoryMock = $this->getMockBuilder(TagCollectionFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->searchResultsFactoryMock = $this->getMockBuilder(TagSearchResultsInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->collectionProcessorMock = $this->createMock(CollectionProcessorInterface::class);

        $this->tagMock = $this->createMock(Tag::class);

        $this->repository = new TagRepository(
            $this->resourceMock,
            $this->tagFactoryMock,
            $this->collectionFactoryMock,
            $this->searchResultsFactoryMock,
            $this->collectionProcessorMock
        );
    }

    public function testSaveSavesTagAndReturns(): void
    {
        $this->resourceMock->expects($this->once())
            ->method('save')
            ->with($this->tagMock)
            ->willReturnSelf();

        $this->assertSame($this->tagMock, $this->repository->save($this->tagMock));
    }

    public function testSaveThrowsCouldNotSaveOnException(): void
    {
        $this->resourceMock->expects($this->once())
            ->method('save')
            ->with($this->tagMock)
            ->willThrowException(new \Exception('db exploded'));

        $this->expectException(CouldNotSaveException::class);
        $this->repository->save($this->tagMock);
    }

    public function testGetByIdLoadsExistingTag(): void
    {
        $tagId = 42;

        $this->tagFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($this->tagMock);
        $this->resourceMock->expects($this->once())
            ->method('load')
            ->with($this->tagMock, $tagId)
            ->willReturnSelf();
        $this->tagMock->expects($this->once())
            ->method('getId')
            ->willReturn($tagId);

        $this->assertSame($this->tagMock, $this->repository->getById($tagId));
    }

    public function testGetByIdThrowsNoSuchEntityOnMissing(): void
    {
        $tagId = 99;

        $this->tagFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($this->tagMock);
        $this->resourceMock->expects($this->once())
            ->method('load')
            ->with($this->tagMock, $tagId)
            ->willReturnSelf();
        $this->tagMock->expects($this->once())
            ->method('getId')
            ->willReturn(null);

        $this->expectException(NoSuchEntityException::class);
        $this->repository->getById($tagId);
    }

    public function testDeleteDeletesTag(): void
    {
        $this->resourceMock->expects($this->once())
            ->method('delete')
            ->with($this->tagMock)
            ->willReturnSelf();

        $this->assertTrue($this->repository->delete($this->tagMock));
    }

    public function testDeleteThrowsCouldNotDeleteOnException(): void
    {
        $this->resourceMock->expects($this->once())
            ->method('delete')
            ->with($this->tagMock)
            ->willThrowException(new \Exception('boom'));

        $this->expectException(CouldNotDeleteException::class);
        $this->repository->delete($this->tagMock);
    }

    public function testDeleteByIdDelegatesToDelete(): void
    {
        $tagId = 7;

        $this->tagFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($this->tagMock);
        $this->resourceMock->expects($this->once())
            ->method('load')
            ->with($this->tagMock, $tagId)
            ->willReturnSelf();
        $this->tagMock->expects($this->once())
            ->method('getId')
            ->willReturn($tagId);
        $this->resourceMock->expects($this->once())
            ->method('delete')
            ->with($this->tagMock)
            ->willReturnSelf();

        $this->assertTrue($this->repository->deleteById($tagId));
    }

    public function testGetListReturnsSearchResults(): void
    {
        $totalCount = 5;
        $items = [$this->tagMock];

        $criteriaMock = $this->createMock(SearchCriteriaInterface::class);
        $collectionMock = $this->createMock(Collection::class);
        $searchResultsMock = $this->createMock(TagSearchResultsInterface::class);

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);

        $this->collectionProcessorMock->expects($this->once())
            ->method('process')
            ->with($criteriaMock, $collectionMock);

        $collectionMock->expects($this->once())
            ->method('getItems')
            ->willReturn($items);
        $collectionMock->expects($this->once())
            ->method('getSize')
            ->willReturn($totalCount);

        $this->searchResultsFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($searchResultsMock);

        $searchResultsMock->expects($this->once())
            ->method('setSearchCriteria')
            ->with($criteriaMock)
            ->willReturnSelf();
        $searchResultsMock->expects($this->once())
            ->method('setItems')
            ->with($items)
            ->willReturnSelf();
        $searchResultsMock->expects($this->once())
            ->method('setTotalCount')
            ->with($totalCount)
            ->willReturnSelf();

        $this->assertSame($searchResultsMock, $this->repository->getList($criteriaMock));
    }
}
