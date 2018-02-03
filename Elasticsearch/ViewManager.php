<?php

namespace Sulu\Bundle\ArticleBundle\Elasticsearch;

use Pucene\Component\Client\ClientInterface;
use Pucene\Component\Client\IndexInterface;
use Pucene\Component\QueryBuilder\Search;
use Sulu\Bundle\ArticleBundle\Document\ArticlePageViewObject;
use Sulu\Bundle\ArticleBundle\Document\ArticleViewDocumentInterface;
use Sulu\Bundle\ArticleBundle\Document\CategoryViewObject;
use Sulu\Bundle\ArticleBundle\Document\ExcerptViewObject;
use Sulu\Bundle\ArticleBundle\Document\Index\DocumentFactoryInterface;
use Sulu\Bundle\ArticleBundle\Document\LocalizationStateViewObject;
use Sulu\Bundle\ArticleBundle\Document\MediaViewObject;
use Sulu\Bundle\ArticleBundle\Document\SeoViewObject;
use Sulu\Bundle\ArticleBundle\Document\TagViewObject;

class ViewManager
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var IndexInterface
     */
    private $index;

    /**
     * @var DocumentFactoryInterface
     */
    private $documentFactory;

    /**
     * @var string
     */
    private $indexName;

    /**
     * @var array
     */
    private $indices;

    /**
     * @var int
     */
    private $total;

    public function __construct(
        ClientInterface $client,
        DocumentFactoryInterface $documentFactory,
        string $indexName,
        array $indices
    ) {
        $this->client = $client;
        $this->documentFactory = $documentFactory;
        $this->indexName = $indexName;
        $this->indices = $indices;
    }

    /**
     * @return ArticleViewDocumentInterface[]
     */
    public function search(Search $search): array
    {
        $viewDocuments = [];

        $searchResult = $this->getIndex()->search($search, 'article');
        $this->total = (int) $searchResult['total'];
        foreach ($searchResult['hits'] as $document) {
            $viewDocuments[] = $this->toViewDocument($document);
        }

        return $viewDocuments;
    }

    public function count(Search $search)
    {
        return $this->getIndex()->count($search, 'article');
    }

    public function total(): ?int
    {
        $total = $this->total;
        $this->total = null;

        return $total;
    }

    public function get(string $id): ?ArticleViewDocumentInterface
    {
        $document = $this->getIndex()->get('article', $id);
        if (!$document['found']) {
            return null;
        }

        return $this->toViewDocument($document);
    }

    public function index(ArticleViewDocumentInterface $viewDocument)
    {
        $this->getIndex()->index($this->toArray($viewDocument), 'article', $viewDocument->getId());
    }

    public function delete(ArticleViewDocumentInterface $viewDocument)
    {
        $this->getIndex()->delete('article', $viewDocument->getId());
    }

    public function drop()
    {
        if (!$this->client->exists($this->indexName)) {
            return;
        }

        $this->client->delete($this->indexName);
    }

    public function create()
    {
        if ($this->client->exists($this->indexName)) {
            return;
        }

        // FIXME parameters collector
        $this->index = $this->client->create(
            $this->indexName,
            [
                'settings' => [
                    'analysis' => $this->indices[$this->indexName]['analysis'],
                ],
                'mappings' => $this->indices[$this->indexName]['mappings'],
            ]
        );
    }

    public function dropAndCreateIndex()
    {
        $this->drop();
        $this->create();
    }

    private function getIndex()
    {
        if (!$this->index) {
            return $this->index = $this->client->get($this->indexName);
        }

        return $this->index;
    }

    private function toViewDocument(array $document): ArticleViewDocumentInterface
    {
        $viewDocument = $this->documentFactory->create('article');
        $viewDocument->setId($document['_id']);

        $viewDocument->setUuid($document['_source']['uuid']);
        $viewDocument->setLocale($document['_source']['locale']);
        $viewDocument->setTitle($document['_source']['title']);
        $viewDocument->setRoutePath($document['_source']['routePath']);
        $viewDocument->setParentPageUuid($document['_source']['parentPageUuid']);
        $viewDocument->setType($document['_source']['type']);
        $viewDocument->setTypeTranslation($document['_source']['typeTranslation']);
        $viewDocument->setStructureType($document['_source']['structureType']);

        if ($document['_source']['changed']) {
            $viewDocument->setChanged(new \DateTime($document['_source']['changed']));
        }
        $viewDocument->setChangerContactId($document['_source']['changerContactId']);
        $viewDocument->setChangerFullName($document['_source']['changerFullName']);

        if ($document['_source']['created']) {
            $viewDocument->setCreated(new \DateTime($document['_source']['created']));
        }
        $viewDocument->setCreatorFullName($document['_source']['creatorFullName']);
        $viewDocument->setCreatorContactId($document['_source']['creatorContactId']);

        if ($document['_source']['authored']) {
            $viewDocument->setAuthored(new \DateTime($document['_source']['authored']));
        }
        $viewDocument->setAuthorId($document['_source']['authorId']);
        $viewDocument->setAuthorFullName($document['_source']['authorFullName']);

        if ($document['_source']['published']) {
            $viewDocument->setPublished(new \DateTime($document['_source']['published']));
        }
        $viewDocument->setPublishedState($document['_source']['publishedState']);

        $viewDocument->setContentData($document['_source']['contentData']);
        $viewDocument->setTeaserMediaId($document['_source']['teaserMediaId']);
        $viewDocument->setTeaserDescription($document['_source']['teaserDescription']);

        $viewDocument->setLocalizationState(
            new LocalizationStateViewObject(
                $document['_source']['localizationState']['state'], $document['_source']['localizationState']['locale']
            )
        );

        $excerpt = new ExcerptViewObject();
        $excerpt->title = $document['_source']['excerpt']['title'];
        $excerpt->description = $document['_source']['excerpt']['description'];
        $excerpt->more = $document['_source']['excerpt']['more'];
        $excerpt->images = $this->toMediaViewObjects($document['_source']['excerpt']['images']);
        $excerpt->icon = $this->toMediaViewObjects($document['_source']['excerpt']['icon']);
        $excerpt->categories = $this->toCategoryViewObjects($document['_source']['excerpt']['categories']);
        $excerpt->tags = $this->toTagViewObjects($document['_source']['excerpt']['tags']);
        $viewDocument->setExcerpt($excerpt);

        $seo = new SeoViewObject();
        $seo->title = $document['_source']['seo']['title'];
        $seo->description = $document['_source']['seo']['description'];
        $seo->keywords = $document['_source']['seo']['keywords'];
        $seo->canonicalUrl = $document['_source']['seo']['canonicalUrl'];
        $seo->hideInSitemap = $document['_source']['seo']['hideInSitemap'];
        $seo->noFollow = $document['_source']['seo']['noFollow'];
        $seo->noIndex = $document['_source']['seo']['noIndex'];
        $viewDocument->setSeo($seo);

        $pages = [];
        foreach ($document['_source']['pages'] as $pageDocument) {
            $pages[] = $page = new ArticlePageViewObject();
            $page->uuid = $pageDocument['uuid'];
            $page->title = $pageDocument['title'];
            $page->contentData = $pageDocument['contentData'];
            $page->pageNumber = $pageDocument['pageNumber'];
            $page->routePath = $pageDocument['routePath'];
        }
        $viewDocument->setPages($pages);

        return $viewDocument;
    }

    private function toMediaViewObjects(array $documents)
    {
        $result = [];
        foreach ($documents as $document) {
            $result[] = $media = new MediaViewObject();
            $media->id = $document['id'];
            $media->copyright = $document['copyright'];
            $media->url = $document['url'];
            $media->setFormats($document['formats']);
        }

        return $result;
    }

    private function toCategoryViewObjects(array $documents)
    {
        $result = [];
        foreach ($documents as $document) {
            $result[] = $category = new CategoryViewObject();
            $category->id = $document['id'];
            $category->key = $document['key'];
            $category->name = $document['name'];
            $category->keywords = $document['keywords'];
        }

        return $result;
    }

    private function toTagViewObjects(array $documents)
    {
        $result = [];
        foreach ($documents as $document) {
            $result[] = $tag = new TagViewObject();
            $tag->id = $document['id'];
            $tag->name = $document['name'];
        }

        return $result;
    }

    private function toArray(ArticleViewDocumentInterface $viewDocument): array
    {
        $document = [];
        $document['uuid'] = $viewDocument->getUuid();
        $document['locale'] = $viewDocument->getLocale();
        $document['title'] = $viewDocument->getTitle();
        $document['routePath'] = $viewDocument->getRoutePath();
        $document['parentPageUuid'] = $viewDocument->getParentPageUuid();
        $document['type'] = $viewDocument->getType();
        $document['typeTranslation'] = $viewDocument->getTypeTranslation();
        $document['structureType'] = $viewDocument->getStructureType();

        $changed = $viewDocument->getChanged();
        $document['changed'] = $changed ? $changed->format(\DateTime::ATOM) : null; // TODO dateformat
        $document['changerContactId'] = $viewDocument->getChangerContactId();
        $document['changerFullName'] = $viewDocument->getChangerFullName();

        $created = $viewDocument->getCreated();
        $document['created'] = $created ? $created->format(\DateTime::ATOM) : null; // TODO dateformat
        $document['creatorContactId'] = $viewDocument->getCreatorContactId();
        $document['creatorFullName'] = $viewDocument->getCreatorFullName();

        $authored = $viewDocument->getAuthored();
        $document['authored'] = $authored ? $authored->format(\DateTime::ATOM) : null; // TODO dateformat
        $document['authorId'] = $viewDocument->getAuthorId();
        $document['authorFullName'] = $viewDocument->getAuthorFullName();

        $published = $viewDocument->getPublished();
        $document['published'] = $published ? $published->format(\DateTime::ATOM) : null; // TODO dateformat
        $document['publishedState'] = $viewDocument->getPublishedState();

        $document['contentData'] = $viewDocument->getContentData();
        $document['teaserMediaId'] = $viewDocument->getTeaserMediaId();
        $document['teaserDescription'] = $viewDocument->getTeaserDescription();

        $document['localizationState'] = [
            'state' => $viewDocument->getLocalizationState()->state,
            'locale' => $viewDocument->getLocalizationState()->locale,
        ];

        $document['excerpt'] = [
            'title' => $viewDocument->getExcerpt()->title,
            'description' => $viewDocument->getExcerpt()->description,
            'more' => $viewDocument->getExcerpt()->more,
            'images' => $this->toMediaArray($viewDocument->getExcerpt()->images),
            'icon' => $this->toMediaArray($viewDocument->getExcerpt()->icon),
            'categories' => $this->toCategoryArray($viewDocument->getExcerpt()->categories),
            'tags' => $this->toTagArray($viewDocument->getExcerpt()->tags),
        ];

        $document['seo'] = [
            'title' => $viewDocument->getSeo()->title,
            'description' => $viewDocument->getSeo()->description,
            'keywords' => $viewDocument->getSeo()->keywords,
            'canonicalUrl' => $viewDocument->getSeo()->canonicalUrl,
            'hideInSitemap' => $viewDocument->getSeo()->hideInSitemap,
            'noFollow' => $viewDocument->getSeo()->noFollow,
            'noIndex' => $viewDocument->getSeo()->noIndex,
        ];

        $document['pages'] = [];
        foreach ($viewDocument->getPages() as $page) {
            $document['pages'][] = [
                'uuid' => $page->uuid,
                'title' => $page->title,
                'contentData' => $page->contentData,
                'pageNumber' => $page->pageNumber,
                'routePath' => $page->routePath,
            ];
        }

        return $document;
    }

    /**
     * @param MediaViewObject[] $mediaViewObjects
     *
     * @return array
     */
    private function toMediaArray(array $mediaViewObjects)
    {
        $result = [];
        foreach ($mediaViewObjects as $mediaViewObject) {
            $result[] = [
                'id' => $mediaViewObject->id,
                'title' => $mediaViewObject->title,
                'copyright' => $mediaViewObject->copyright,
                'url' => $mediaViewObject->url,
                'formats' => $mediaViewObject->getFormats(),
            ];
        }

        return $result;
    }

    /**
     * @param CategoryViewObject[] $categoryViewObjects
     *
     * @return array
     */
    private function toCategoryArray(array $categoryViewObjects)
    {
        $result = [];
        foreach ($categoryViewObjects as $categoryViewObject) {
            $result[] = [
                'id' => $categoryViewObject->id,
                'key' => $categoryViewObject->key,
                'name' => $categoryViewObject->name,
                'keywords' => $categoryViewObject->keywords,
            ];
        }

        return $result;
    }

    /**
     * @param TagViewObject[] $tagViewObjects
     *
     * @return array
     */
    private function toTagArray(array $tagViewObjects)
    {
        $result = [];
        foreach ($tagViewObjects as $tagViewObject) {
            $result[] = [
                'id' => $tagViewObject->id,
                'name' => $tagViewObject->name,
            ];
        }

        return $result;
    }
}
