<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ArticleBundle\Builder;

use Sulu\Bundle\ArticleBundle\Elasticsearch\ViewManager;
use Sulu\Bundle\CoreBundle\Build\SuluBuilder;

/**
 * Builder for article-index.
 */
class ArticleIndexBuilder extends SuluBuilder
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'article_index';
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        $this->buildForManager($this->container->get('sulu_article.view_manager.default'), $this->input->getOption('destroy'));
        $this->buildForManager($this->container->get('sulu_article.view_manager.live'), $this->input->getOption('destroy'));
    }

    /**
     * Build index for given manager.
     *
     * If index not exists - it will be created.
     * If index exists and destroy flag is true - drop and create index.
     * Else do nothing.
     *
     * @param ViewManager $viewManager
     * @param bool $destroy
     */
    private function buildForManager(ViewManager $viewManager, $destroy)
    {
        $name = $viewManager->getName();
        if (!$viewManager->indexExists()) {
            $this->output->writeln(sprintf('Create index for "<comment>%s</comment>" manager.', $name));
            $viewManager->createIndex();

            return;
        }

        if (!$destroy) {
            return;
        }

        $this->output->writeln(sprintf('Drop and create index for "<comment>%s</comment>" manager.', $name));
        $viewManager->drop();
        $viewManager->create();
    }
}
