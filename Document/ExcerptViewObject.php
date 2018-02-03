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
 * Contains excerpt information for articles.
 */
class ExcerptViewObject
{
    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $more;

    /**
     * @var string
     */
    public $description;

    /**
     * @var CategoryViewObject[]
     */
    public $categories = [];

    /**
     * @var TagViewObject[]
     */
    public $tags = [];

    /**
     * @var MediaViewObject[]
     */
    public $icon = [];

    /**
     * @var MediaViewObject[]
     */
    public $images = [];
}
