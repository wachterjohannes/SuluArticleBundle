<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ArticleBundle\Document\Subscriber;

use Doctrine\ORM\EntityManagerInterface;
use PHPCR\ItemNotFoundException;
use PHPCR\SessionInterface;
use Sulu\Bundle\ArticleBundle\Document\ArticleDocument;
use Sulu\Bundle\ArticleBundle\Document\Behavior\RoutableBehavior;
use Sulu\Bundle\ArticleBundle\Document\Behavior\RoutablePageBehavior;
use Sulu\Bundle\DocumentManagerBundle\Bridge\DocumentInspector;
use Sulu\Bundle\DocumentManagerBundle\Bridge\PropertyEncoder;
use Sulu\Bundle\RouteBundle\Entity\RouteRepositoryInterface;
use Sulu\Bundle\RouteBundle\Generator\ChainRouteGeneratorInterface;
use Sulu\Bundle\RouteBundle\Manager\RouteManagerInterface;
use Sulu\Bundle\RouteBundle\Model\RouteInterface;
use Sulu\Component\Content\Metadata\Factory\StructureMetadataFactoryInterface;
use Sulu\Component\DocumentManager\Behavior\Mapping\ChildrenBehavior;
use Sulu\Component\DocumentManager\DocumentManagerInterface;
use Sulu\Component\DocumentManager\Event\AbstractMappingEvent;
use Sulu\Component\DocumentManager\Event\ConfigureOptionsEvent;
use Sulu\Component\DocumentManager\Event\CopyEvent;
use Sulu\Component\DocumentManager\Event\MetadataLoadEvent;
use Sulu\Component\DocumentManager\Event\PublishEvent;
use Sulu\Component\DocumentManager\Event\RemoveEvent;
use Sulu\Component\DocumentManager\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles document-manager events to create/update/remove routes.
 */
class RoutableSubscriber implements EventSubscriberInterface
{
    const ROUTE_FIELD = 'routePath';
    const ROUTES_PROPERTY = 'suluRoutes';
    const TAG_NAME = 'sulu_article.article_route';

    /**
     * @var ChainRouteGeneratorInterface
     */
    private $routeGeneratorPool;

    /**
     * @var RouteManagerInterface
     */
    private $routeManager;

    /**
     * @var RouteRepositoryInterface
     */
    private $routeRepository;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var DocumentManagerInterface
     */
    private $documentManager;

    /**
     * @var DocumentInspector
     */
    private $documentInspector;

    /**
     * @var PropertyEncoder
     */
    private $propertyEncoder;

    /**
     * @var StructureMetadataFactoryInterface
     */
    private $metadataFactory;

    /**
     * @param ChainRouteGeneratorInterface $routeGeneratorPool
     * @param RouteManagerInterface $routeManager
     * @param RouteRepositoryInterface $routeRepository
     * @param EntityManagerInterface $entityManager
     * @param DocumentManagerInterface $documentManager
     * @param DocumentInspector $documentInspector
     * @param PropertyEncoder $propertyEncoder
     * @param StructureMetadataFactoryInterface $metadataFactory
     */
    public function __construct(
        ChainRouteGeneratorInterface $routeGeneratorPool,
        RouteManagerInterface $routeManager,
        RouteRepositoryInterface $routeRepository,
        EntityManagerInterface $entityManager,
        DocumentManagerInterface $documentManager,
        DocumentInspector $documentInspector,
        PropertyEncoder $propertyEncoder,
        StructureMetadataFactoryInterface $metadataFactory
    ) {
        $this->routeGeneratorPool = $routeGeneratorPool;
        $this->routeManager = $routeManager;
        $this->routeRepository = $routeRepository;
        $this->entityManager = $entityManager;
        $this->documentManager = $documentManager;
        $this->documentInspector = $documentInspector;
        $this->propertyEncoder = $propertyEncoder;
        $this->metadataFactory = $metadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::HYDRATE => ['handleHydrate'],
            Events::PERSIST => [
                // low priority because all other subscriber should be finished
                ['handlePersist', -2000],
            ],
            Events::REMOVE => [
                // high priority to ensure nodes are not deleted until we iterate over children
                ['handleRemove', 1024],
            ],
            Events::PUBLISH => ['handlePublish', -2000],
            Events::COPY => ['handleCopy', -2000],
            Events::METADATA_LOAD => 'handleMetadataLoad',
            Events::CONFIGURE_OPTIONS => 'configureOptions',
        ];
    }

    /**
     * Load route.
     *
     * @param AbstractMappingEvent $event
     */
    public function handleHydrate(AbstractMappingEvent $event)
    {
        $document = $event->getDocument();
        if (!$document instanceof RoutablePageBehavior) {
            return;
        }

        $propertyName = $this->getRoutePathPropertyName($document->getStructureType(), $event->getLocale());
        $routePath = $event->getNode()->getPropertyValueWithDefault($propertyName, null);
        $document->setRoutePath($routePath);

        $route = $this->routeRepository->findByEntity($document->getClass(), $document->getUuid(), $event->getLocale());
        if ($route) {
            $document->setRoute($route);
        }
    }

    /**
     * Generate route and save route-path.
     *
     * @param AbstractMappingEvent $event
     */
    public function handlePersist(AbstractMappingEvent $event)
    {
        $document = $event->getDocument();
        if (!$document instanceof RoutablePageBehavior) {
            return;
        }

        $document->setUuid($event->getNode()->getIdentifier());

        $generatedRoute = $this->routeGeneratorPool->generate(
            $document,
            $event->getOption('route_path') ?: $document->getRoutePath()
        );

        $document->setRoutePath($generatedRoute->getPath());

        $propertyName = $this->getRoutePathPropertyName($document->getStructureType(), $event->getLocale());
        $event->getNode()->setProperty($propertyName, $generatedRoute->getPath());
    }

    /**
     * Handle publish event and generate route and the child-routes.
     *
     * @param PublishEvent $event
     */
    public function handlePublish(PublishEvent $event)
    {
        $document = $event->getDocument();
        if (!$document instanceof RoutableBehavior) {
            return;
        }

        $this->entityManager->persist($this->createOrUpdateRoute($document, $event->getLocale()));

        $propertyName = $this->getPropertyName($event->getLocale(), self::ROUTES_PROPERTY);

        // check if nodes previous generated routes exists and remove them if not
        $oldRoutes = $event->getNode()->getPropertyValueWithDefault($propertyName, []);
        $this->removeOldChildRoutes($event->getNode()->getSession(), $oldRoutes, $event->getLocale());

        $routes = [];
        if ($document instanceof ChildrenBehavior) {
            // generate new routes of children
            $routes = $this->generateChildRoutes($document, $event->getLocale());
        }

        // save the newly generated routes of children
        $event->getNode()->setProperty($this->getPropertyName($event->getLocale(), self::ROUTES_PROPERTY), $routes);
        $this->entityManager->flush();
    }

    /**
     * Create or update for given document.
     *
     * @param RoutablePageBehavior $document
     * @param string $locale
     *
     * @return RouteInterface
     */
    private function createOrUpdateRoute(RoutablePageBehavior $document, $locale)
    {
        $route = $this->routeRepository->findByEntity($document->getClass(), $document->getUuid(), $locale);
        if ($route) {
            $document->setRoute($route);

            return $this->routeManager->update($document, $document->getRoutePath());
        }

        return $this->routeManager->create($document, $document->getRoutePath());
    }

    /**
     * Removes old-routes where the node does not exists anymore.
     *
     * @param SessionInterface $session
     * @param array $oldRoutes
     * @param string $locale
     */
    private function removeOldChildRoutes(SessionInterface $session, array $oldRoutes, $locale)
    {
        foreach ($oldRoutes as $oldRoute) {
            $oldRouteEntity = $this->routeRepository->findByPath($oldRoute, $locale);
            if (!$this->nodeExists($session, $oldRouteEntity->getEntityId())) {
                $this->entityManager->remove($oldRouteEntity);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Generates child routes.
     *
     * @param ChildrenBehavior $document
     * @param string $locale
     *
     * @return string[]
     */
    private function generateChildRoutes(ChildrenBehavior $document, $locale)
    {
        $routes = [];
        foreach ($document->getChildren() as $child) {
            if (!$child instanceof RoutablePageBehavior) {
                continue;
            }

            $childRoute = $this->createOrUpdateRoute($child, $locale);
            $this->entityManager->persist($childRoute);

            $child->setRoutePath($childRoute->getPath());
            $childNode = $this->documentInspector->getNode($child);

            $propertyName = $this->getRoutePathPropertyName($child->getStructureType(), $locale);
            $childNode->setProperty($propertyName, $childRoute->getPath());

            $routes[] = $childRoute->getPath();
        }

        return $routes;
    }

    /**
     * Removes route.
     *
     * @param RemoveEvent $event
     */
    public function handleRemove(RemoveEvent $event)
    {
        $document = $event->getDocument();
        if (!$document instanceof RoutableBehavior) {
            return;
        }

        $route = $this->routeRepository->findByPath($document->getRoutePath(), $document->getOriginalLocale());
        if (!$route) {
            return;
        }

        $this->entityManager->remove($route);

        if ($document instanceof ChildrenBehavior) {
            $this->removeChildRoutes($document);
        }

        $this->entityManager->flush();
    }

    /**
     * Update routes for copied article.
     *
     * @param CopyEvent $event
     */
    public function handleCopy(CopyEvent $event)
    {
        $document = $event->getDocument();
        if (!$document instanceof RoutableBehavior) {
            return;
        }

        $locales = $this->documentInspector->getLocales($document);
        foreach ($locales as $locale) {
            /** @var ArticleDocument $localizedDocument */
            $localizedDocument = $this->documentManager->find($event->getCopiedPath(), $locale);

            $localizedDocument->removeRoute();
            $route = $this->routeManager->create($localizedDocument);
            $document->setRoutePath($route->getPath());
            $this->entityManager->persist($route);

            $event->getCopiedNode()->setProperty(
                $this->propertyEncoder->localizedSystemName(self::ROUTE_FIELD, $locale),
                $route->getPath()
            );
        }

        $this->entityManager->flush();
    }

    /**
     * Iterate over children and remove routes.
     *
     * @param ChildrenBehavior $document
     */
    private function removeChildRoutes(ChildrenBehavior $document)
    {
        foreach ($document->getChildren() as $child) {
            if ($child instanceof RoutablePageBehavior) {
                $this->removeChildRoute($child);
            }

            if ($child instanceof ChildrenBehavior) {
                $this->removeChildRoutes($child);
            }
        }
    }

    /**
     * Removes route if exists.
     *
     * @param RoutablePageBehavior $document
     */
    private function removeChildRoute(RoutablePageBehavior $document)
    {
        $route = $this->routeRepository->findByPath($document->getRoutePath(), $document->getOriginalLocale());
        if ($route) {
            $this->entityManager->remove($route);
        }
    }

    /**
     * Add route to metadata.
     *
     * @param MetadataLoadEvent $event
     */
    public function handleMetadataLoad(MetadataLoadEvent $event)
    {
        if (!$event->getMetadata()->getReflectionClass()->implementsInterface(RoutableBehavior::class)) {
            return;
        }

        $metadata = $event->getMetadata();
        $metadata->addFieldMapping(
            self::ROUTE_FIELD,
            [
                'encoding' => 'system_localized',
                'property' => self::ROUTE_FIELD,
            ]
        );
    }

    /**
     * Add route-path to options.
     *
     * @param ConfigureOptionsEvent $event
     */
    public function configureOptions(ConfigureOptionsEvent $event)
    {
        $options = $event->getOptions();
        $options->setDefaults(['route_path' => null]);
    }

    /**
     * Returns encoded "routePath" property-name.
     *
     * @param string $structureType
     * @param string $locale
     *
     * @return string
     */
    private function getRoutePathPropertyName($structureType, $locale)
    {
        $metadata = $this->metadataFactory->getStructureMetadata('article', $structureType);

        if ($metadata->hasTag(self::TAG_NAME)) {
            return $this->getPropertyName($locale, $metadata->getPropertyByTagName(self::TAG_NAME)->getName());
        }

        return $this->getPropertyName($locale, self::ROUTE_FIELD);
    }

    /**
     * Returns encoded property-name.
     *
     * @param string $locale
     * @param string $field
     *
     * @return string
     */
    private function getPropertyName($locale, $field)
    {
        return $this->propertyEncoder->localizedSystemName($field, $locale);
    }

    /**
     * Returns true if given uuid exists.
     *
     * @param SessionInterface $session
     * @param string $uuid
     *
     * @return bool
     */
    private function nodeExists(SessionInterface $session, $uuid)
    {
        try {
            $session->getNodeByIdentifier($uuid);

            return true;
        } catch (ItemNotFoundException $exception) {
            return false;
        }
    }
}
