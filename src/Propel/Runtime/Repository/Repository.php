<?php

namespace Propel\Runtime\Repository;

use Propel\Runtime\Configuration;
use Propel\Runtime\Event\DeleteEvent;
use Propel\Runtime\Event\InsertEvent;
use Propel\Runtime\Event\RepositoryEvent;
use Propel\Runtime\Event\SaveEvent;
use Propel\Runtime\Event\UpdateEvent;
use Propel\Runtime\Events;
use Propel\Runtime\Map\EntityMap;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class Repository
{

    /**
     * @var EntityMap
     */
    protected $entityMap;

    /**
     * All committed object IDs get a new key of this array.
     *
     *     $committedIds[spl_object_hash($entity)] = true;
     *
     * @var string[]
     */
    protected $committedIds = [];
    protected $deletedIds = [];

    /**
     * @var array[]
     */
    protected $lastKnownValues = [];

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var object[]
     */
    protected $firstLevelCache;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @param EntityMap     $entityMap
     * @param Configuration $configuration
     */
    public function __construct(EntityMap $entityMap, Configuration $configuration)
    {
        $this->entityMap = $entityMap;
        $this->configuration = $configuration;
        $this->mapEvents();
    }

    /**
     * Maps all event dispatcher events to local event dispatcher, if we have a child class.
     */
    protected function mapEvents()
    {
        $self = $this;
        $mapEvents = [
            Events::PRE_SAVE => 'preSave',
            Events::PRE_UPDATE => 'preUpdate',
            Events::PRE_INSERT => 'preInsert',
            Events::SAVE => 'postSave',
            Events::UPDATE => 'postUpdate',
            Events::INSERT => 'postInsert'
        ];

        foreach ($mapEvents as $eventName => $method) {
            $this->configuration->getEventDispatcher()->addListener(
                $eventName,
                function (RepositoryEvent $event) use ($self, $eventName, $method) {
                    if ($self->getEntityMap()->getFullClassName() === $event->getEntityMap()->getFullClassName()) {
                        $self->$method($event);
                        $self->getEventDispatcher()->dispatch($eventName, $event);
                    }
                }
            );
        }
    }

    protected function preSave($event)
    {
    }

    protected function preUpdate($event)
    {
    }

    protected function preInsert(InsertEvent $event)
    {
    }

    protected function postSave(SaveEvent $event)
    {
        foreach ($event->getEntitiesToInsert() as $entity) {
            var_dump('inserted ' . spl_object_hash($entity) . ' => ' . get_class($entity));
            $this->committedIds[spl_object_hash($entity)] = true;
            $this->snapshot($entity);
        }
        foreach ($event->getEntitiesToUpdate() as $entity) {
            var_dump('updated ' . spl_object_hash($entity) . ' => ' . get_class($entity));
            $this->committedIds[spl_object_hash($entity)] = true;
            $this->snapshot($entity);
        }
    }

    protected function postUpdate(UpdateEvent $event)
    {
    }

    protected function postInsert(InsertEvent $event)
    {
    }

    protected function postDelete(DeleteEvent $event)
    {
        foreach ($event->getEntities() as $entity) {
            $id = spl_object_hash($entity);
            unset($this->committedIds[$id]);
            $this->deletedIds[$id] = true;
            unset($this->originalValues[$id]);
        }
    }

    /**
     * @param string   $eventName
     * @param callable $listener
     */
    public function on($eventName, callable $listener)
    {
        $this->getEventDispatcher()->addListener($eventName, $listener);
    }

    /**
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        if (null === $this->eventDispatcher) {
            $this->eventDispatcher = new EventDispatcher();
        }

        return $this->eventDispatcher;
    }

    /**
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function setEventDispatcher($eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Deletes all objects from the backend and clears the first level cache.
     */
    public function deleteAll()
    {
        $this->clearFirstLevelCache();
        $this->doDeleteAll();
    }

    /**
     * This is the actual implementation of `find`, which will be provided
     * by the platform.
     *
     * @param array|string|integer $key
     *
     * @return object
     */
    abstract protected function doFind($key);

    /**
     * This is the actual implementation of `deleteAll`, which will be provided
     * by the platform.
     */
    abstract protected function doDeleteAll();

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * @param Configuration $configuration
     */
    public function setConfiguration($configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @return EntityMap
     */
    public function getEntityMap()
    {
        return $this->entityMap;
    }

    /**
     * @param EntityMap $entityMap
     */
    public function setEntityMap($entityMap)
    {
        $this->entityMap = $entityMap;
    }

    public function getInstanceFromFirstLevelCache($hashCode)
    {
        if (isset($this->firstLevelCache[$hashCode])) {
            return $this->firstLevelCache[$hashCode];
        }
    }

    public function clearFirstLevelCache()
    {
        $this->firstLevelCache = [];
    }

    public function persistDependencies($entity)
    {
        //not implemented here
    }

    public function isDeleted($entity)
    {
    }

    /**
     * @param object|string $id
     * @param array $values
     */
    public function setLastKnownValues($id, $values)
    {
        if (is_object($id)) {
            $id = spl_object_hash($id);
        }
        $this->lastKnownValues[$id] = $values;
    }

    /**
     * Reads all values of $entities and place it in lastKnownValues.
     *
     * @param object $entity
     */
    public function snapshot($entity)
    {
        $values = $this->getEntityMap()->getSnapshot($entity);
        $this->lastKnownValues[spl_object_hash($entity)] = $values;
    }

    /**
     * @param object|string $id
     *
     * @return array
     */
    public function getLastKnownValues($id)
    {
        if (is_object($id)) {
            $id = spl_object_hash($id);
        }
        return $this->lastKnownValues[$id];
    }

    /**
     * @param object|string $id
     *
     * @return bool
     */
    public function hasKnownValues($id)
    {
        if (is_object($id)) {
            $id = spl_object_hash($id);
        }
        return isset($this->lastKnownValues[$id]);
    }
}