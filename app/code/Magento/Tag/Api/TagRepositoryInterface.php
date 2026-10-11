<?php
/**
 * Copyright 2015 Adobe
 * All Rights Reserved.
 */
namespace Magento\Tag\Api;

/**
 * Tag CRUD interface.
 * @api
 * @since 100.0.2
 */
interface TagRepositoryInterface
{
    /**
     * Save tag.
     *
     * @param \Magento\Tag\Api\Data\TagInterface $tag
     * @return \Magento\Tag\Api\Data\TagInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(Data\TagInterface $tag);

    /**
     * Retrieve tag.
     *
     * @param int $tagId
     * @return \Magento\Tag\Api\Data\TagInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getById($tagId);

    /**
     * Retrieve tags matching the specified criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Magento\Tag\Api\Data\TagSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);

    /**
     * Delete tag.
     *
     * @param \Magento\Tag\Api\Data\TagInterface $tag
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(Data\TagInterface $tag);

    /**
     * Delete tag by ID.
     *
     * @param int $tagId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($tagId);
}
