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

use Pucene\Component\QueryBuilder\Query\TermLevel\IdsQuery;
use Pucene\Component\QueryBuilder\Search;
use Sulu\Bundle\ArticleBundle\Elasticsearch\ViewManager;
use Sulu\Bundle\ArticleBundle\Metadata\ArticleViewDocumentIdTrait;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\PreResolvableContentTypeInterface;
use Sulu\Component\Content\SimpleContentType;

/**
 * Provides article_selection content-type.
 */
class ArticleSelectionContentType extends SimpleContentType implements PreResolvableContentTypeInterface
{
    use ArticleViewDocumentIdTrait;

    /**
     * @var ViewManager
     */
    private $viewManager;

    /**
     * @var ReferenceStoreInterface
     */
    private $referenceStore;

    /**
     * @var string
     */
    private $template;

    /**
     * @param ViewManager $viewManager
     * @param ReferenceStoreInterface $referenceStore
     * @param string $template
     */
    public function __construct(ViewManager $viewManager, ReferenceStoreInterface $referenceStore, $template)
    {
        parent::__construct('Article', []);

        $this->viewManager = $viewManager;
        $this->referenceStore = $referenceStore;
        $this->template = $template;
    }

    /**
     * {@inheritdoc}
     */
    public function getContentData(PropertyInterface $property)
    {
        $value = $property->getValue();
        if (null === $value || !is_array($value) || 0 === count($value)) {
            return [];
        }

        $locale = $property->getStructure()->getLanguageCode();
        $search = new Search(new IdsQuery($this->getViewDocumentIds($value, $locale)));

        $result = [];
        foreach ($this->viewManager->search($search) as $articleDocument) {
            $result[array_search($articleDocument->getUuid(), $value, false)] = $articleDocument;
        }

        ksort($result);

        return array_values($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * {@inheritdoc}
     */
    public function preResolve(PropertyInterface $property)
    {
        $uuids = $property->getValue();
        if (!is_array($uuids)) {
            return;
        }

        foreach ($uuids as $uuid) {
            $this->referenceStore->add($uuid);
        }
    }
}
