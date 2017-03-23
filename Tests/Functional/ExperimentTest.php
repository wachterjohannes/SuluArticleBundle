<?php

namespace Functional;

use PHPCR\SessionInterface;
use Sulu\Bundle\ArticleBundle\Document\ArticlePageDocument;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Component\DocumentManager\DocumentManagerInterface;

/**
 * TODO add description here
 */
class ExperimentTest extends SuluTestCase
{
    /**
     * @var SessionInterface
     */
    private $defaultSession;

    /**
     * @var SessionInterface
     */
    private $liveSession;

    /**
     * @var DocumentManagerInterface
     */
    private $documentManager;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->initPhpcr();

        $this->defaultSession = $this->getContainer()->get('doctrine_phpcr.default_session');
        $this->liveSession = $this->getContainer()->get('doctrine_phpcr.live_session');
        $this->documentManager = $this->getContainer()->get('sulu_document_manager.document_manager');
    }

    public function testExperiment()
    {
        $articleId = $this->createArticle();
        $this->refresh();

        /** @var ArticlePageDocument $page */
        $page = $this->documentManager->create('articlepage');
        $page->setTitle('hallo');
        $page->setParent($this->documentManager->find($articleId, 'de'));

        $this->documentManager->persist($page, 'de');
        $this->documentManager->flush();

        $this->refresh();

        $this->documentManager->publish($this->documentManager->find($articleId, 'de'), 'de');
        $this->documentManager->flush();
        $this->refresh();

        $version = $this->documentManager->find($articleId, 'de')->getVersions()[0];

        $page = $this->documentManager->find($page->getUuid(), 'de');
        $page->setTitle('QWERTZ');
        $this->documentManager->persist($page, 'de');
        $this->documentManager->flush();
        $this->refresh();

        $article = $this->documentManager->find($articleId, 'de');
        $article->setTitle('QWERTZ');
        $this->documentManager->persist($article, 'de');
        $this->documentManager->flush();
        $this->refresh();

        $this->documentManager->publish($this->documentManager->find($articleId, 'de'), 'de');
        $this->documentManager->flush();
        $this->refresh();

        $this->documentManager->restore($this->documentManager->find($articleId, 'de'), 'de', $version->getId());
        $this->documentManager->flush();
        $this->refresh();

        $article = $this->documentManager->find($articleId, 'de');
        $this->assertEquals('Test', $article->getTitle());

        $page = $this->documentManager->find($page->getUuid(), 'de');
        $this->assertEquals('hallo', $page->getTitle());
    }

    private function createArticle()
    {
        $client = $this->createAuthenticatedClient();
        $client->request(
            'POST',
            '/api/articles?locale=de',
            [
                'title' => 'Test',
                'template' => 'default',
            ]
        );
        $response = json_decode($client->getResponse()->getContent(), true);

        return $response['id'];
    }

    private function refresh()
    {
        $this->defaultSession->save();
        $this->defaultSession->refresh(false);

        $this->liveSession->save();
        $this->liveSession->refresh(false);

        $this->documentManager->flush();
        $this->documentManager->clear();
    }
}
