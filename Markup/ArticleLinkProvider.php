<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ArticleBundle\Markup;

use Pucene\Component\QueryBuilder\Query\TermLevel\IdsQuery;
use Pucene\Component\QueryBuilder\Search;
use Sulu\Bundle\ArticleBundle\Document\ArticleViewDocumentInterface;
use Sulu\Bundle\ArticleBundle\Elasticsearch\ViewManager;
use Sulu\Bundle\ArticleBundle\Metadata\ArticleViewDocumentIdTrait;
use Sulu\Bundle\ContentBundle\Markup\Link\LinkConfiguration;
use Sulu\Bundle\ContentBundle\Markup\Link\LinkItem;
use Sulu\Bundle\ContentBundle\Markup\Link\LinkProviderInterface;

/**
 * Integrates articles into link-system.
 */
class ArticleLinkProvider implements LinkProviderInterface
{
    use ArticleViewDocumentIdTrait;

    /**
     * @var ViewManager
     */
    private $liveViewManager;

    /**
     * @var ViewManager
     */
    private $defaultViewManager;

    /**
     * @var array
     */
    private $types;

    /**
     * @param ViewManager $liveViewManager
     * @param ViewManager $defaultViewManager
     * @param array $types
     */
    public function __construct(
        ViewManager $liveViewManager,
        ViewManager $defaultViewManager,
        array $types
    ) {
        $this->liveViewManager = $liveViewManager;
        $this->defaultViewManager = $defaultViewManager;
        $this->types = $types;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration()
    {
        $tabs = null;
        if (1 < count($this->types)) {
            $tabs = array_map(
                function ($type) {
                    return ['title' => $type['translation_key']];
                },
                $this->types
            );
        }

        return new LinkConfiguration(
            'sulu_article.ckeditor.link',
            'ckeditor/link/article@suluarticle',
            [],
            ['tabs' => $tabs]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function preload(array $hrefs, $locale, $published = true)
    {
        $search = new Search(new IdsQuery($this->getViewDocumentIds($hrefs, $locale)));
        $search->setSize(count($hrefs));

        if (!$published) {
            $documents = $this->defaultViewManager->search($search);
        } else {
            $documents = $this->liveViewManager->search($search);
        }

        $result = [];
        /** @var ArticleViewDocumentInterface $document */
        foreach ($documents as $document) {
            $result[] = new LinkItem(
                $document->getUuid(),
                $document->getTitle(),
                $document->getRoutePath(),
                $document->getPublishedState()
            );
        }

        return $result;
    }
}
