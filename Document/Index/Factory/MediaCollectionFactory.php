<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ArticleBundle\Document\Index\Factory;

use Sulu\Bundle\ArticleBundle\Document\MediaViewObject;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;

/**
 * Create a media collection view object.
 */
class MediaCollectionFactory
{
    /**
     * @var MediaManagerInterface
     */
    private $mediaManager;

    /**
     * MediaCollectionFactory constructor.
     *
     * @param MediaManagerInterface $mediaManager
     */
    public function __construct(MediaManagerInterface $mediaManager)
    {
        $this->mediaManager = $mediaManager;
    }

    /**
     * Create media collection object.
     *
     * @param array $data
     * @param $locale
     *
     * @return MediaViewObject[]
     */
    public function create($data, $locale)
    {
        if (empty($data) || !array_key_exists('ids', $data)) {
            return [];
        }

        $result = [];
        $medias = $this->mediaManager->getByIds($data['ids'], $locale);
        foreach ($medias as $media) {
            $mediaViewObject = new MediaViewObject();
            $mediaViewObject->setData($media);

            $result[] = $mediaViewObject;
        }

        return $result;
    }
}
