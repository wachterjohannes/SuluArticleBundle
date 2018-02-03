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
 * Contains page information.
 */
class ArticlePageViewObject
{
    /**
     * @var string
     */
    public $uuid;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $routePath;

    /**
     * @var int
     */
    public $pageNumber;

    /**
     * @var string
     */
    public $contentData;

    /**
     * @var \ArrayObject
     */
    public $content;

    /**
     * @var \ArrayObject
     */
    public $view;
}
