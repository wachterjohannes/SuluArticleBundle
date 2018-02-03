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
 * Contains localization state information for articles.
 */
class LocalizationStateViewObject
{
    /**
     * @var string
     */
    public $state;

    /**
     * @var string
     */
    public $locale;

    /**
     * @param string $state
     * @param string $locale
     */
    public function __construct($state = null, $locale = null)
    {
        $this->state = $state;
        $this->locale = $locale;
    }
}
