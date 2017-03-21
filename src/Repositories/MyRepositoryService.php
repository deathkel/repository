<?php

/*
 * NOTICE OF LICENSE
 *
 * Part of the Rinvex Repository Package.
 *
 * This source file is subject to The MIT License (MIT)
 * that is bundled with this package in the LICENSE file.
 *
 * Package: Rinvex Repository Package
 * License: The MIT License (MIT)
 * Link:    https://rinvex.com
 *------------------------------
 * 引入者：KEL
 * phone：15757161281
 *------------------------------
 */
namespace App\Services;

use App\Services\Exceptions\ServiceException;
use App\Services\Repository\Overrides\HackBaseRepository;
use App\Services\Repository\Overrides\HackCacheable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Rinvex\Repository\Exceptions\RepositoryException;
use Rinvex\Repository\Repositories\BaseRepository;

class MyRepositoryService extends BaseRepository
{
    const CACHE_NAME_PREFIX = 'repository';

    use HackBaseRepository, HackCacheable;
    /**
     *
     */
    protected $modelInstance;

    protected $cacheHashCondition = null;

    /**
     * The query where clauses.
     *
     * @var array
     */
    protected $join = [];

    public function __construct()
    {
        $this->setCacheDriver(config('rinvex.repository.cache.driver'))
            ->setCacheLifetime($this->cacheLifetime ?: config('rinvex.repository.cache.lifetime'));
    }

    /**
     * for RPC to forget cache
     */
    public function forgetCacheRPC()
    {
        try {
            $this->forgetCache();
        } catch (\Throwable $throwable) {
            return false;
            throw new ServiceException($throwable->getMessage(), $throwable->getCode(), $throwable->getPrevious());
        }

        return true;
    }

    /**
     * @return object|string
     * @throws RepositoryException
     * 获取Model实体单例
     */
    public function getModelInstance()
    {
        if (!$this->modelInstance) {
            $this->modelInstance = $this->createModel();
        }
        return $this->modelInstance;
    }

    public function getQuerySql()
    {
        return $this->prepareQuery($this->getModelInstance())->toSql();
    }

    /**
     * {@inheritdoc}
     */
    public function createModel()
    {
        if (is_string($model = $this->getModel())) {
            if (!class_exists($class = '\\' . ltrim($model, '\\'))) {
                throw new RepositoryException("Class {$model} does NOT exist!");
            }
            $model = $this->getContainer()->make($class);
        }
        // Set the connection used by the model
        if (!empty($this->connection)) {
            $model = $model->setConnection($this->connection);
        }
        if (!$model instanceof Model) {
            throw new RepositoryException("Class {$model} must be an instance of \\Illuminate\\Database\\Eloquent\\Model");
        }
        return $model;
    }

    /**
     * @param array $columns
     * @return mixed
     * @author kel
     */
    public function get($columns = ['*'])
    {
        return $this->findAll($columns);
    }

    /**
     * @param array $columns
     * @return mixed
     * @author kel
     */
    public function first($columns = ['*'])
    {
        return $this->findFirst($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function find($id, $attributes = ['*'])
    {
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], func_get_args(),
            function () use ($id, $attributes) {
                $result = $this->prepareQuery($this->getModelInstance())->find($id, $attributes);
                return $result ? $result->toArray() : [];
            });
    }

//    /**
//     * {@inheritdoc}
//     */
//    public function findOrFail($id, $attributes = ['*'])
//    {
//        $result = $this->find($id, $attributes);
//        if (is_array($id)) {
//            if (count($result) == count(array_unique($id))) {
//                return $result;
//            }
//        } elseif (!is_null($result)) {
//            return $result;
//        }
//        throw new EntityNotFoundException($this->getModel(), $id);
//    }

    /**
     * {@inheritdoc}
     */
    public function findOrNew($id, $attributes = ['*'])
    {
        if (!is_null($entity = $this->find($id, $attributes))) {
            return $entity;
        }
        return $this->createModel();
    }

    /**
     * {@inheritdoc}
     */
    public function findBy($attribute, $value, $attributes = ['*'])
    {
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], func_get_args(),
            function () use ($attribute, $value, $attributes) {
                $result = $this->prepareQuery($this->getModelInstance())->where($attribute, '=',
                    $value)->first($attributes);
                return $result ? $result->toArray() : [];
            });
    }

    /**
     * {@inheritdoc}
     */
    public function findFirst($attributes = ['*'])
    {
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], func_get_args(), function () use ($attributes) {
            $result = $this->prepareQuery($this->getModelInstance())->first($attributes);
            return $result ? $result->toArray() : [];
        });
    }

    /**
     * {@inheritdoc}
     */
    public function findAll($attributes = ['*'])
    {
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], func_get_args(), function () use ($attributes) {
            $result = $this->prepareQuery($this->getModelInstance())->get($attributes);
            return $result ? $result->toArray() : [];
        });
    }

    /**
     * {@inheritdoc}
     */
    public function paginate($perPage = null, $attributes = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], array_merge(func_get_args(), compact('page')),
            function () use ($perPage, $attributes, $pageName, $page) {
                $result = $this->prepareQuery($this->getModelInstance())->paginate($perPage, $attributes, $pageName,
                    $page);
                return $result ? $result->toArray() : [];
            });
    }

    /**
     * {@inheritdoc}
     */
    public function simplePaginate($perPage = null, $attributes = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], array_merge(func_get_args(), compact('page')),
            function () use ($perPage, $attributes, $pageName, $page) {
                $result = $this->prepareQuery($this->getModelInstance())->simplePaginate($perPage, $attributes, $pageName,
                    $page);
                return $result ? $result->toArray() : [];
            });
    }

    /**
     * {@inheritdoc}
     */
    public function findWhere(array $where, $attributes = ['*'])
    {
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], func_get_args(),
            function () use ($where, $attributes) {
                list($attribute, $operator, $value, $boolean) = array_pad($where, 4, null);
                $this->where($attribute, $operator, $value, $boolean);
                $result = $this->prepareQuery($this->getModelInstance())->get($attributes);
                return $result ? $result->toArray() : [];
            });
    }

    /**
     * {@inheritdoc}
     */
    public function findWhereIn(array $where, $attributes = ['*'])
    {
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], func_get_args(),
            function () use ($where, $attributes) {
                list($attribute, $values, $boolean, $not) = array_pad($where, 4, null);
                $this->whereIn($attribute, $values, $boolean, $not);
                $result = $this->prepareQuery($this->getModelInstance())->get($attributes);
                return $result ? $result->toArray() : [];
            });
    }

    /**
     * {@inheritdoc}
     */
    public function findWhereNotIn(array $where, $attributes = ['*'])
    {
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], func_get_args(),
            function () use ($where, $attributes) {
                list($attribute, $values, $boolean) = array_pad($where, 3, null);
                $this->whereNotIn($attribute, $values, $boolean);
                $result = $this->prepareQuery($this->getModelInstance())->get($attributes);
                return $result ? $result->toArray() : [];
            });
    }

    /**
     * {@inheritdoc}
     */
    public function findWhereHas(array $where, $attributes = ['*'])
    {
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], func_get_args(),
            function () use ($where, $attributes) {
                list($relation, $callback, $operator, $count) = array_pad($where, 4, null);
                $this->whereHas($relation, $callback, $operator, $count);
                $result = $this->prepareQuery($this->getModelInstance())->get($attributes);
                return $result ? $result->toArray() : [];
            });
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $attributes = [], bool $syncRelations = false)
    {
        // Create a new instance
        $entity = $this->createModel();
        // Fire the created event
        $this->getContainer('events')->fire($this->getRepositoryId() . '.entity.creating', [$this, $entity]);
        // Extract relationships
        if ($syncRelations) {
            $relations = $this->extractRelations($entity, $attributes);
            array_forget($attributes, array_keys($relations));
        }
        // Fill instance with data
        $entity->fill($attributes);
        // Save the instance
        $created = $entity->save();
        // Sync relationships
        if ($syncRelations && isset($relations)) {
            $this->syncRelations($entity, $relations);
        }
        // Fire the created event
        $this->getContainer('events')->fire($this->getRepositoryId() . '.entity.created', [$this, $entity]);
        // Return instance
        return $created ? $entity : $created;
    }

    /**
     * {@inheritdoc}
     */
    public function update($id, array $attributes = [], bool $syncRelations = false)
    {
        $updated = false;
        // Find the given instance
        $entity = $id instanceof Model ? $id : $this->find($id);
        if ($entity) {
            // Fire the updated event
            $this->getContainer('events')->fire($this->getRepositoryId() . '.entity.updating', [$this, $entity]);
            // Extract relationships
            if ($syncRelations) {
                $relations = $this->extractRelations($entity, $attributes);
                array_forget($attributes, array_keys($relations));
            }
            // Fill instance with data
            $entity->fill($attributes);
            //Check if we are updating attributes values
            $dirty = $entity->getDirty();
            // Update the instance
            $updated = $entity->save();
            // Sync relationships
            if ($syncRelations && isset($relations)) {
                $this->syncRelations($entity, $relations);
            }
            if (count($dirty) > 0) {
                // Fire the updated event
                $this->getContainer('events')->fire($this->getRepositoryId() . '.entity.updated', [$this, $entity]);
            }
        }
        return $updated ? $entity : $updated;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($id)
    {
        $deleted = false;
        // Find the given instance
        $entity = $id instanceof Model ? $id : $this->find($id);
        if ($entity) {
            // Fire the deleted event
            $this->getContainer('events')->fire($this->getRepositoryId() . '.entity.deleting', [$this, $entity]);
            // Delete the instance
            $deleted = $entity->delete();
            // Fire the deleted event
            $this->getContainer('events')->fire($this->getRepositoryId() . '.entity.deleted', [$this, $entity]);
        }
        return $deleted ? $entity : $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function restore($id)
    {
        $restored = false;
        // Find the given instance
        $entity = $id instanceof Model ? $id : $this->find($id);
        if ($entity) {
            // Fire the restoring event
            $this->getContainer('events')->fire($this->getRepositoryId() . '.entity.restoring', [$this, $entity]);
            // Restore the instance
            $restored = $entity->restore();
            // Fire the restored event
            $this->getContainer('events')->fire($this->getRepositoryId() . '.entity.restored', [$this, $entity]);
        }
        return $restored ? $entity : $restored;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        $this->getContainer('db')->beginTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $this->getContainer('db')->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack()
    {
        $this->getContainer('db')->rollBack();
    }

    /**
     * {@inheritdoc}
     */
    public function count($columns = '*')
    {
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], func_get_args(), function () use ($columns) {
            return $this->prepareQuery($this->createModel())->count($columns);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function min($column)
    {
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], func_get_args(), function () use ($column) {
            return $this->prepareQuery($this->createModel())->min($column);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function max($column)
    {
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], func_get_args(), function () use ($column) {
            return $this->prepareQuery($this->createModel())->max($column);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function avg($column)
    {
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], func_get_args(), function () use ($column) {
            return $this->prepareQuery($this->createModel())->avg($column);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function sum($column)
    {
        return $this->executeCallback(get_called_class(), debug_backtrace()[1]['function'], func_get_args(), function () use ($column) {
            return $this->prepareQuery($this->createModel())->sum($column);
        });
    }

    /**
     * Extract relationships.
     *
     * @param mixed $entity
     * @param array $attributes
     *
     * @return array
     */
    protected function extractRelations($entity, array $attributes)
    {
        $relations = [];
        $potential = array_diff(array_keys($attributes), $entity->getFillable());
        array_walk($potential, function ($relation) use ($entity, $attributes, &$relations) {
            if (method_exists($entity, $relation)) {
                $relations[$relation] = [
                    'values' => $attributes[$relation],
                    'class' => get_class($entity->$relation()),
                ];
            }
        });
        return $relations;
    }

    /**
     * Sync relationships.
     *
     * @param mixed $entity
     * @param array $relations
     * @param bool $detaching
     *
     * @return void
     */
    protected function syncRelations($entity, array $relations, $detaching = true)
    {
        foreach ($relations as $method => $relation) {
            switch ($relation['class']) {
                case 'Illuminate\Database\Eloquent\Relations\BelongsToMany':
                default:
                    $entity->$method()->sync((array)$relation['values'], $detaching);
                    break;
            }
        }
    }

}