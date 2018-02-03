<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ArticleBundle\Document\Repository;

use Pucene\Component\QueryBuilder\Query\Compound\BoolQuery;
use Pucene\Component\QueryBuilder\Query\Specialized\MoreLikeThis\DocumentLike;
use Pucene\Component\QueryBuilder\Query\Specialized\MoreLikeThis\MoreLikeThisQuery;
use Pucene\Component\QueryBuilder\Query\TermLevel\TermQuery;
use Pucene\Component\QueryBuilder\Search;
use Sulu\Bundle\ArticleBundle\Document\ArticleViewDocumentInterface;
use Sulu\Bundle\ArticleBundle\Elasticsearch\ViewManager;
use Sulu\Bundle\ArticleBundle\Metadata\ArticleViewDocumentIdTrait;
use Sulu\Component\DocumentManager\DocumentManagerInterface;

/**
 * Find article view documents in elasticsearch index.
 */
class ArticleViewDocumentRepository
{
    use ArticleViewDocumentIdTrait;

    const DEFAULT_LIMIT = 5;

    /**
     * @var ViewManager
     */
    protected $viewManager;

    /**
     * @var DocumentManagerInterface
     */
    protected $documentManager;

    /**
     * @var array
     */
    protected $searchFields;

    public function __construct(ViewManager $viewManager, array $searchFields)
    {
        $this->viewManager = $viewManager;
        $this->searchFields = $searchFields;
    }

    /**
     * Finds recent articles for given parameters sorted by field `authored`.
     *
     * @param null|string $excludeUuid
     * @param int $limit
     * @param null|array $types
     * @param null|string $locale
     *
     * @return ArticleViewDocumentInterface[]
     */
    public function findRecent($excludeUuid = null, $limit = self::DEFAULT_LIMIT, array $types = null, $locale = null)
    {
        $query = $this->createSearchQuery($types, $locale);
        $search = new Search();
        $search->setSize($limit);

        if ($excludeUuid) {
            $query->mustNot(new TermQuery('uuid', $excludeUuid));
        }

        // TODO field-sort
        // $search->addSort(new FieldSort('authored', FieldSort::DESC));

        return $this->viewManager->search($search);
    }

    /**
     * Finds similar articles for given `uuid` with given parameters.
     *
     * @param string $uuid
     * @param int $limit
     * @param null|array $types
     * @param null|string $locale
     *
     * @return ArticleViewDocumentInterface[]
     */
    public function findSimilar($uuid, $limit = self::DEFAULT_LIMIT, array $types = null, $locale = null)
    {
        $query = $this->createSearchQuery($types, $locale);
        $search = new Search();
        $search->setSize($limit);

        $moreLikeThis = new MoreLikeThisQuery(
            [
                new DocumentLike($this->getViewDocumentId($uuid, $locale))
            ],
            $this->searchFields
        );
        $moreLikeThis->setMinTermFreq(1);
        $moreLikeThis->setMinDocFreq(2);
        $query->must($moreLikeThis);

        return $this->viewManager->search($search);
    }

    /**
     * Creates search with default queries (size, locale, types).
     *
     * @param null|array $types
     * @param null|string $locale
     *
     * @return BoolQuery
     */
    private function createSearchQuery(array $types = null, $locale = null)
    {
        $query = new BoolQuery();

        // filter by locale if provided
        if ($locale) {
            $query->filter(new TermQuery('locale', $locale));
        }

        // filter by types if provided
        if ($types) {
            $typesQuery = new BoolQuery();
            foreach ($types as $type) {
                $typesQuery->should(new TermQuery('type', $type));
            }
            $query->must($typesQuery);
        }

        return $query;
    }
}
