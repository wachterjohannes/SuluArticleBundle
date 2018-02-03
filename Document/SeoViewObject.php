<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ArticleBundle\Document;

/**
 * Contains seo information for articles.
 */
class SeoViewObject
{
    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $keywords;

    /**
     * @var string
     */
    public $canonicalUrl;

    /**
     * @var bool
     */
    public $noIndex;

    /**
     * @var bool
     */
    public $noFollow;

    /**
     * @var bool
     */
    public $hideInSitemap;
}
