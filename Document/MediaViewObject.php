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

use Sulu\Bundle\MediaBundle\Api\Media;

/**
 * Contains the ids and display-options.
 */
class MediaViewObject
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $copyright;

    /**
     * @var string
     */
    protected $formats = '{}';

    /**
     * @var string
     */
    public $url;

    /**
     * Set data.
     *
     * @param Media $media
     *
     * @return $this
     */
    public function setData(Media $media)
    {
        $this->id = $media->getId();
        $this->title = $media->getTitle();
        $this->setFormats($media->getFormats());
        $this->url = $media->getUrl();
        $this->copyright = $media->getCopyright();

        return $this;
    }

    /**
     * Get formats.
     *
     * @return string
     */
    public function getFormats()
    {
        return $this->formats;
    }

    /**
     * Set formats.
     *
     * @param string[] $formats
     *
     * @return $this
     */
    public function setFormats($formats)
    {
        if (is_array($formats)) {
            $formats = json_encode($formats);
        }

        $this->formats = $formats;

        return $this;
    }

    /**
     * Get thumbnails.
     *
     * @return array
     */
    public function getThumbnails()
    {
        return json_decode($this->formats, true);
    }
}
