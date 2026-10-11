<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Tag\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tag\Api\Data\TagInterface;
use Magento\Tag\Api\Data\TagSearchResultsInterface;
use Magento\Tag\Api\Data\TagSearchResultsInterfaceFactory;
use Magento\Tag\Api\TagRepositoryInterface;
use Magento\Tag\Model\ResourceModel\Tag as TagResource;
use Magento\Tag\Model\ResourceModel\Tag\CollectionFactory as TagCollectionFactory;

/**
 * Default Tag repository implementation.
 *
 * Thin service-contracts wrapper around the Tag resource model, following the pattern
 * established by Magento_Cms BlockRepository.
 */
class TagRepository implements TagRepositoryInterface
{
    /**
     * @param TagResource $resource
     * @param TagFactory $tagFactory
     * @param TagCollectionFactory $tagCollectionFactory
     * @param TagSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly TagResource $resource,
        private readonly TagFactory $tagFactory,
        private readonly TagCollectionFactory $tagCollectionFactory,
        private readonly TagSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * Save tag.
     *
     * @param TagInterface $tag
     * @return TagInterface
     * @throws CouldNotSaveException
     */
    public function save(TagInterface $tag)
    {
        try {
            $this->resource->save($tag);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()), $exception);
        }
        return $tag;
    }

    /**
     * Retrieve tag by id.
     *
     * @param int $tagId
     * @return TagInterface
     * @throws NoSuchEntityException
     */
    public function getById($tagId)
    {
        /** @var Tag $tag */
        $tag = $this->tagFactory->create();
        $this->resource->load($tag, $tagId);
        if (!$tag->getId()) {
            throw new NoSuchEntityException(
                __('The tag with the "%1" ID doesn\'t exist.', $tagId)
            );
        }
        return $tag;
    }

    /**
     * Retrieve tags matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return TagSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        /** @var \Magento\Tag\Model\ResourceModel\Tag\Collection $collection */
        $collection = $this->tagCollectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var TagSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * Delete tag.
     *
     * @param TagInterface $tag
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(TagInterface $tag)
    {
        try {
            $this->resource->delete($tag);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()), $exception);
        }
        return true;
    }

    /**
     * Delete tag by id.
     *
     * @param int $tagId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById($tagId)
    {
        return $this->delete($this->getById($tagId));
    }
}
