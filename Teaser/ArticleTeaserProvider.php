<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ArticleBundle\Teaser;

use Pucene\Component\QueryBuilder\Query\TermLevel\IdsQuery;
use Pucene\Component\QueryBuilder\Search;
use Sulu\Bundle\ArticleBundle\Document\ArticleViewDocument;
use Sulu\Bundle\ArticleBundle\Document\ArticleViewDocumentInterface;
use Sulu\Bundle\ArticleBundle\Elasticsearch\ViewManager;
use Sulu\Bundle\ArticleBundle\Metadata\ArticleViewDocumentIdTrait;
use Sulu\Bundle\ContentBundle\Teaser\Configuration\TeaserConfiguration;
use Sulu\Bundle\ContentBundle\Teaser\Provider\TeaserProviderInterface;
use Sulu\Bundle\ContentBundle\Teaser\Teaser;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Enables selection of articles in teaser content-type.
 */
class ArticleTeaserProvider implements TeaserProviderInterface
{
    use ArticleViewDocumentIdTrait;

    /**
     * @var ViewManager
     */
    private $viewManager;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @param ViewManager $viewManager
     * @param TranslatorInterface $translator
     */
    public function __construct(ViewManager $viewManager, TranslatorInterface $translator)
    {
        $this->viewManager = $viewManager;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration()
    {
        $okDefaultText = $this->translator->trans('sulu-content.teaser.apply', [], 'backend');

        return new TeaserConfiguration(
            'sulu_article.teaser',
            'teaser-selection/list@suluarticle',
            [
                'url' => '/admin/api/articles?locale={locale}',
                'resultKey' => 'articles',
                'searchFields' => ['title', 'routePath', 'changerFullName', 'creatorFullName', 'authorFullName'],
            ],
            [
                [
                    'title' => $this->translator->trans('sulu_article.authored', [], 'backend'),
                    'cssClass' => 'authored-slide',
                    'contentSpacing' => true,
                    'okDefaultText' => $okDefaultText,
                    'buttons' => [
                        [
                            'type' => 'ok',
                            'align' => 'right',
                        ],
                        [
                            'type' => 'cancel',
                            'align' => 'left',
                        ],
                    ],
                ],
                [
                    'title' => $this->translator->trans('sulu_article.contact-selection-overlay.title', [], 'backend'),
                    'cssClass' => 'contact-slide',
                    'contentSpacing' => true,
                    'okDefaultText' => $okDefaultText,
                    'buttons' => [
                        [
                            'type' => 'ok',
                            'align' => 'right',
                        ],
                        [
                            'type' => 'cancel',
                            'align' => 'left',
                        ],
                    ],
                ],
                [
                    'title' => $this->translator->trans('sulu_article.category-selection-overlay.title', [], 'backend'),
                    'cssClass' => 'category-slide',
                    'contentSpacing' => true,
                    'okDefaultText' => $okDefaultText,
                    'buttons' => [
                        [
                            'type' => 'ok',
                            'align' => 'right',
                        ],
                        [
                            'type' => 'cancel',
                            'align' => 'left',
                        ],
                    ],
                ],
                [
                    'title' => $this->translator->trans('sulu_article.tag-selection-overlay.title', [], 'backend'),
                    'cssClass' => 'tag-slide',
                    'contentSpacing' => true,
                    'okDefaultText' => $okDefaultText,
                    'buttons' => [
                        [
                            'type' => 'ok',
                            'align' => 'right',
                        ],
                        [
                            'type' => 'cancel',
                            'align' => 'left',
                        ],
                    ],
                ],
                [
                    'title' => $this->translator->trans('public.choose', [], 'backend'),
                    'cssClass' => 'page-slide data-source-slide',
                    'contentSpacing' => false,
                    'okDefaultText' => $okDefaultText,
                    'buttons' => [
                        [
                            'type' => 'ok',
                            'align' => 'right',
                        ],
                        [
                            'type' => 'cancel',
                            'align' => 'left',
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function find(array $ids, $locale)
    {
        if (0 === count($ids)) {
            return [];
        }

        $search = new Search(new IdsQuery($this->getViewDocumentIds($ids, $locale)));

        $result = [];
        foreach ($this->viewManager->search($search) as $item) {
            $excerpt = $item->getExcerpt();
            $result[] = new Teaser(
                $item->getUuid(),
                'article',
                $item->getLocale(),
                ('' !== $excerpt->title ? $excerpt->title : $item->getTitle()),
                ('' !== $excerpt->description ? $excerpt->description : $item->getTeaserDescription()),
                $excerpt->more,
                $item->getRoutePath(),
                count($excerpt->images) ? $excerpt->images[0]->id : $item->getTeaserMediaId(),
                $this->getAttributes($item)
            );
        }

        return $result;
    }

    /**
     * Returns attributes for teaser.
     *
     * @param ArticleViewDocumentInterface $viewDocument
     *
     * @return array
     */
    protected function getAttributes(ArticleViewDocumentInterface $viewDocument)
    {
        return [
            'structureType' => $viewDocument->getStructureType(),
            'type' => $viewDocument->getType(),
        ];
    }
}
