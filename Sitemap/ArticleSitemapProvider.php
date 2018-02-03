<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ArticleBundle\Sitemap;

use Pucene\Component\QueryBuilder\Query\TermLevel\TermQuery;
use Pucene\Component\QueryBuilder\Search;
use Sulu\Bundle\ArticleBundle\Document\ArticleViewDocumentInterface;
use Sulu\Bundle\ArticleBundle\Document\Index\DocumentFactoryInterface;
use Sulu\Bundle\ArticleBundle\Elasticsearch\ViewManager;
use Sulu\Bundle\WebsiteBundle\Sitemap\Sitemap;
use Sulu\Bundle\WebsiteBundle\Sitemap\SitemapAlternateLink;
use Sulu\Bundle\WebsiteBundle\Sitemap\SitemapProviderInterface;
use Sulu\Bundle\WebsiteBundle\Sitemap\SitemapUrl;

/**
 * Integrates articles into sitemap.
 */
class ArticleSitemapProvider implements SitemapProviderInterface
{
    /**
     * @var ViewManager
     */
    private $viewManager;

    /**
     * @var DocumentFactoryInterface
     */
    private $documentFactory;

    public function __construct(ViewManager $viewManager, DocumentFactoryInterface $documentFactory)
    {
        $this->viewManager = $viewManager;
        $this->documentFactory = $documentFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function build($page, $portalKey)
    {
        $result = [];

        $from = 0;
        $size = 1000;
        do {
            $bulk = $this->getBulk($from, $size);
            /** @var SitemapUrl[] $alternatives */
            $sitemapUrlListByUuid = [];

            /** @var ArticleViewDocumentInterface $item */
            foreach ($bulk as $item) {
                $sitemapUrl = new SitemapUrl($item->getRoutePath(), $item->getLocale(), $item->getChanged());
                $result[] = $sitemapUrl;

                if (!isset($sitemapUrlListByUuid[$item->getUuid()])) {
                    $sitemapUrlListByUuid[$item->getUuid()] = [];
                }

                $sitemapUrlListByUuid[$item->getUuid()] = $this->setAlternatives(
                    $sitemapUrlListByUuid[$item->getUuid()],
                    $sitemapUrl
                );
            }

            $from += $size;
        } while ($bulk->count() > $from || $from > self::PAGE_SIZE);

        return $result;
    }

    /**
     * Set alternatives to sitemap url.
     *
     * @param SitemapUrl[] $sitemapUrlList
     * @param SitemapUrl $sitemapUrl
     *
     * @return SitemapUrl[]
     */
    private function setAlternatives(array $sitemapUrlList, SitemapUrl $sitemapUrl)
    {
        foreach ($sitemapUrlList as $sitemapUrlFromList) {
            // Add current as alternative to exist.
            $sitemapUrlFromList->addAlternateLink(
                new SitemapAlternateLink($sitemapUrl->getLoc(), $sitemapUrl->getLocale())
            );

            // Add others as alternative to current.
            $sitemapUrl->addAlternateLink(
                new SitemapAlternateLink($sitemapUrlFromList->getLoc(), $sitemapUrlFromList->getLocale())
            );
        }

        $sitemapUrlList[] = $sitemapUrl;

        return $sitemapUrlList;
    }

    private function getBulk(int $from, int $size = 1000)
    {
        $search = new Search(new TermQuery('seo.hide_in_sitemap', 'false'));
        $search->setFrom($from);
        $search->setSize($size);

        return $this->viewManager->search($search);
    }

    /**
     * {@inheritdoc}
     */
    public function createSitemap($alias)
    {
        return new Sitemap($alias, $this->getMaxPage());
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxPage()
    {
        $search = new Search(new TermQuery('seo.hide_in_sitemap', 'false'));
        $total = $this->viewManager->count($search);

        return ceil($total / self::PAGE_SIZE);
    }
}
