<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ArticleBundle\Content;

use Pucene\Component\QueryBuilder\Query\Compound\BoolQuery;
use Pucene\Component\QueryBuilder\Query\MatchAllQuery;
use Pucene\Component\QueryBuilder\Query\QueryInterface;
use Pucene\Component\QueryBuilder\Query\TermLevel\TermQuery;
use Pucene\Component\QueryBuilder\Search;
use Sulu\Bundle\ArticleBundle\Document\ArticleViewDocumentInterface;
use Sulu\Bundle\ArticleBundle\Elasticsearch\ViewManager;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Content\Compat\PropertyParameter;
use Sulu\Component\SmartContent\Configuration\Builder;
use Sulu\Component\SmartContent\Configuration\BuilderInterface;
use Sulu\Component\SmartContent\DataProviderAliasInterface;
use Sulu\Component\SmartContent\DataProviderInterface;
use Sulu\Component\SmartContent\DataProviderResult;

/**
 * Introduces articles in smart-content.
 */
class ArticleDataProvider implements DataProviderInterface, DataProviderAliasInterface
{
    /**
     * @var ViewManager
     */
    protected $viewManager;

    /**
     * @var ReferenceStoreInterface
     */
    private $referenceStore;

    /**
     * @var ArticleResourceItemFactory
     */
    protected $articleResourceItemFactory;

    /**
     * @var int
     */
    protected $defaultLimit;

    /**
     * @param ViewManager $viewManager
     * @param ReferenceStoreInterface $referenceStore
     * @param ArticleResourceItemFactory $articleResourceItemFactory
     * @param int $defaultLimit
     */
    public function __construct(
        ViewManager $viewManager,
        ReferenceStoreInterface $referenceStore,
        ArticleResourceItemFactory $articleResourceItemFactory,
        $defaultLimit
    ) {
        $this->viewManager = $viewManager;
        $this->referenceStore = $referenceStore;
        $this->articleResourceItemFactory = $articleResourceItemFactory;
        $this->defaultLimit = $defaultLimit;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration()
    {
        return $this->getConfigurationBuilder()->getConfiguration();
    }

    /**
     * Create new configuration-builder.
     *
     * @return BuilderInterface
     */
    protected function getConfigurationBuilder()
    {
        return Builder::create()
            ->enableTags()
            ->enableCategories()
            ->enableLimit()
            ->enablePagination()
            ->enablePresentAs()
            ->setDeepLink('articles/{locale}/edit:{id}/details')
            ->enableSorting(
                [
                    ['column' => 'published', 'title' => 'sulu_article.smart-content.published'],
                    ['column' => 'authored', 'title' => 'sulu_article.smart-content.authored'],
                    ['column' => 'created', 'title' => 'sulu_article.smart-content.created'],
                    ['column' => 'title', 'title' => 'sulu_article.smart-content.title'],
                    ['column' => 'author_full_name', 'title' => 'sulu_article.smart-content.author-full-name'],
                ]
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultPropertyParameter()
    {
        return ['type' => new PropertyParameter('type', null)];
    }

    /**
     * {@inheritdoc}
     */
    public function resolveDataItems(
        array $filters,
        array $propertyParameter,
        array $options = [],
        $limit = null,
        $page = 1,
        $pageSize = null
    ) {
        $filters['types'] = $this->getTypesProperty($propertyParameter);
        $filters['excluded'] = $this->getExcludedFilter($filters, $propertyParameter);

        $queryResult = $this->getSearchResult($filters, $limit, $page, $pageSize, $options['locale']);

        $result = [];
        /** @var ArticleViewDocumentInterface $document */
        foreach ($queryResult as $document) {
            $result[] = new ArticleDataItem($document->getUuid(), $document->getTitle(), $document);
        }

        $total = $this->viewManager->total();
        $hasNextPage = $this->hasNextPage($total, $limit, $page, $pageSize);

        return new DataProviderResult($result, $hasNextPage);
    }

    /**
     * {@inheritdoc}
     */
    public function resolveResourceItems(
        array $filters,
        array $propertyParameter,
        array $options = [],
        $limit = null,
        $page = 1,
        $pageSize = null
    ) {
        $filters['types'] = $this->getTypesProperty($propertyParameter);
        $filters['excluded'] = $this->getExcludedFilter($filters, $propertyParameter);

        $queryResult = $this->getSearchResult($filters, $limit, $page, $pageSize, $options['locale']);

        $result = [];
        /** @var ArticleViewDocumentInterface $document */
        foreach ($queryResult as $document) {
            $this->referenceStore->add($document->getUuid());
            $result[] = $this->articleResourceItemFactory->createResourceItem($document);
        }

        $total = $this->viewManager->total();
        $hasNextPage = $this->hasNextPage($total, $limit, $page, $pageSize);

        return new DataProviderResult($result, $hasNextPage);
    }

    /**
     * {@inheritdoc}
     */
    public function resolveDatasource($datasource, array $propertyParameter, array $options)
    {
        return;
    }

    /**
     * Returns flag "hasNextPage".
     * It combines the limit/query-count with the page and page-size.
     *
     * @param int $count
     * @param int $limit
     * @param int $page
     * @param int $pageSize
     *
     * @return bool
     */
    private function hasNextPage($count, $limit, $page, $pageSize)
    {
        if ($limit && $limit < $count) {
            $count = $limit;
        }

        return $count > ($page * $pageSize);
    }

    /**
     * Creates search for filters and returns search-result.
     *
     * @param array $filters
     * @param int $limit
     * @param int $page
     * @param int $pageSize
     * @param string $locale
     *
     * @return ArticleViewDocumentInterface[]
     */
    private function getSearchResult(array $filters, $limit, $page, $pageSize, $locale)
    {
        $query = $this->createSearchQuery($filters, $locale);
        if (!$query) {
            return [];
        }

        $search = new Search($query);
        $this->addPagination($search, $pageSize, $page, $limit);

        if (array_key_exists('sortBy', $filters) && is_array($filters['sortBy'])) {
            $sortMethod = array_key_exists('sortMethod', $filters) ? $filters['sortMethod'] : 'asc';
            $this->appendSortBy($filters['sortBy'], $sortMethod, $search);
        }

        return $this->viewManager->search($search);
    }

    /**
     * Initialize search with necessary queries.
     *
     * @param array $filters
     * @param string $locale
     *
     * @return QueryInterface
     */
    protected function createSearchQuery(array $filters, $locale)
    {
        $searchQuery = new BoolQuery();

        if (0 < count($filters['excluded'])) {
            foreach ($filters['excluded'] as $uuid) {
                $searchQuery->mustNot(new TermQuery('uuid', $uuid));
            }
        }

        $query = new BoolQuery();

        $queriesCount = 0;
        $operator = $this->getFilter($filters, 'tagOperator', 'or');
        $this->addBoolQuery('tags', $filters, 'excerpt.tags.id', $operator, $query, $queriesCount);
        $operator = $this->getFilter($filters, 'websiteTagsOperator', 'or');
        $this->addBoolQuery('websiteTags', $filters, 'excerpt.tags.id', $operator, $query, $queriesCount);

        $operator = $this->getFilter($filters, 'categoryOperator', 'or');
        $this->addBoolQuery('categories', $filters, 'excerpt.categories.id', $operator, $query, $queriesCount);
        $operator = $this->getFilter($filters, 'websiteCategoriesOperator', 'or');
        $this->addBoolQuery('websiteCategories', $filters, 'excerpt.categories.id', $operator, $query, $queriesCount);

        if (null !== $locale) {
            $searchQuery->must(new TermQuery('locale', $locale));
        }

        if (array_key_exists('types', $filters) && $filters['types']) {
            $typesQuery = new BoolQuery();
            foreach ($filters['types'] as $typeFilter) {
                $typesQuery->should(new TermQuery('type', $typeFilter));
            }
            $searchQuery->must($typesQuery);
        }

        if (0 === $queriesCount) {
            $searchQuery->must(new MatchAllQuery());
        } else {
            $searchQuery->must($query);
        }

        return $searchQuery;
    }

    /**
     * Returns array with all types defined in property parameter.
     *
     * @param array $propertyParameter
     *
     * @return array
     */
    private function getTypesProperty($propertyParameter)
    {
        $filterTypes = [];

        if (array_key_exists('types', $propertyParameter)
            && null !== ($types = explode(',', $propertyParameter['types']->getValue()))
        ) {
            foreach ($types as $type) {
                $filterTypes[] = $type;
            }
        }

        return $filterTypes;
    }

    /**
     * Returns excluded articles.
     *
     * @param array $filters
     * @param PropertyParameter[] $propertyParameter
     *
     * @return array
     */
    private function getExcludedFilter(array $filters, array $propertyParameter)
    {
        $excluded = array_key_exists('excluded', $filters) ? $filters['excluded'] : [];
        if (array_key_exists('exclude_duplicates', $propertyParameter)
            && $propertyParameter['exclude_duplicates']->getValue()
        ) {
            $excluded = array_merge($excluded, $this->referenceStore->getAll());
        }

        return $excluded;
    }

    /**
     * Extension point to append order.
     *
     * @param array $sortBy
     * @param string $sortMethod
     * @param Search $search
     *
     * @return array parameters for query
     */
    private function appendSortBy($sortBy, $sortMethod, $search)
    {
        foreach ($sortBy as $column) {
            // TODO field-sort
            // $search->addSort(new FieldSort($column, $sortMethod));
        }
    }

    /**
     * Add the pagination to given query.
     *
     * @param Search $search
     * @param int $pageSize
     * @param int $page
     * @param int $limit
     */
    private function addPagination(Search $search, $pageSize, $page, $limit)
    {
        $offset = 0;
        if ($pageSize) {
            $pageSize = intval($pageSize);
            $offset = ($page - 1) * $pageSize;
        }

        if (null === $limit) {
            $limit = $this->defaultLimit;
        }

        if (null === $pageSize || $offset + $pageSize > $limit) {
            $pageSize = $limit - $offset;

            if ($pageSize < 0) {
                $pageSize = 0;
            }
        }

        $search->setFrom($offset);
        $search->setSize($pageSize);
    }

    /**
     * Add a boolean-query if filter exists.
     *
     * @param string $filterName
     * @param array $filters
     * @param string $field
     * @param string $operator
     * @param BoolQuery $query
     * @param int $queriesCount
     */
    private function addBoolQuery($filterName, array $filters, $field, $operator, BoolQuery $query, &$queriesCount)
    {
        if (0 !== count($tags = $this->getFilter($filters, $filterName))) {
            ++$queriesCount;
            $query->must($this->getBoolQuery($field, $tags, $operator));
        }
    }

    /**
     * Returns boolean query for given fields and values.
     *
     * @param string $field
     * @param array $values
     * @param string $operator
     *
     * @return BoolQuery
     */
    private function getBoolQuery($field, array $values, $operator)
    {
        $query = new BoolQuery();
        foreach ($values as $value) {
            if (strtolower($operator) === 'or') {
                $query->should(new TermQuery($field, $value));
            } else {
                $query->must(new TermQuery($field, $value));
            }
        }

        return $query;
    }

    /**
     * Returns filter value.
     *
     * @param array $filters
     * @param string $name
     * @param mixed $default
     *
     * @return mixed
     */
    private function getFilter(array $filters, $name, $default = null)
    {
        if ($this->hasFilter($filters, $name)) {
            return $filters[$name];
        }

        return $default;
    }

    /**
     * Returns true if filter-value exists.
     *
     * @param array $filters
     * @param string $name
     *
     * @return bool
     */
    private function hasFilter(array $filters, $name)
    {
        return array_key_exists($name, $filters) && null !== $filters[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        return 'article';
    }
}
