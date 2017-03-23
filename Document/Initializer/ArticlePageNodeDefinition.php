<?php

namespace Sulu\Bundle\ArticleBundle\Document\Initializer;

use Jackalope\NodeType\NodeType;
use PHPCR\NodeType\NodeDefinitionInterface;
use PHPCR\NodeType\NodeTypeInterface;
use PHPCR\Version\OnParentVersionAction;

/**
 * TODO add description here
 */
class ArticlePageNodeDefinition implements NodeDefinitionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getDeclaringNodeType()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return '*';
    }

    /**
     * {@inheritdoc}
     */
    public function isAutoCreated()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isMandatory()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getOnParentVersion()
    {
        return OnParentVersionAction::COPY;
    }

    /**
     * {@inheritdoc}
     */
    public function isProtected()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredPrimaryTypes()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredPrimaryTypeNames()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultPrimaryType()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultPrimaryTypeName()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function allowsSameNameSiblings()
    {
        return false;
    }
}
