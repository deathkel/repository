<?php
/**
 * Created by PhpStorm.
 * User: KEL
 * Date: 2017/1/18
 * Time: 12:51
 */

namespace App\Services\Repository\Overrides;
use Illuminate\Support\Facades\Log;

/**
 * Class HackBaseRepository
 * @package App\Services\Repository\Overrides
 * @override Rinvex\Repository\Repositories\BaseRepository
 * 该类用于重写父类方法
 */
trait HackBaseRepository
{
    /**
     * override parent function
     * @author kel
     *
     * Prepare query.
     *
     * @param object $model
     *
     * @return object
     */
    protected function prepareQuery($model)
    {
        // Set the relationships that should be eager loaded
        if (!empty($this->relations)) {
            $model = $model->with($this->relations);
        }

        //--------------------------
        // Add a basic join clause to the query
        //added by kel
        foreach ($this->join as $join) {
            list($table, $one, $operator, $two) = array_pad($join, 4, null);

            $model = $model->join($table, $one, $operator, $two);
        }
        //--------------------------

        // Add a basic where clause to the query
        foreach ($this->where as $where) {
            list($attribute, $operator, $value, $boolean) = array_pad($where, 4, null);

            $model = $model->where($attribute, $operator, $value, $boolean);
        }

        // Add a "where in" clause to the query
        foreach ($this->whereIn as $whereIn) {
            list($attribute, $values, $boolean, $not) = array_pad($whereIn, 4, null);

            $model = $model->whereIn($attribute, $values, $boolean, $not);
        }

        // Add a "where not in" clause to the query
        foreach ($this->whereNotIn as $whereNotIn) {
            list($attribute, $values, $boolean) = array_pad($whereNotIn, 3, null);

            $model = $model->whereNotIn($attribute, $values, $boolean);
        }

        // Set the "offset" value of the query
        if ($this->offset > 0) {
            $model = $model->offset($this->offset);
        }

        // Set the "limit" value of the query
        if ($this->limit > 0) {
            $model = $model->limit($this->limit);
        }

        // Add an "order by" clause to the query.
        if (!empty($this->orderBy)) {
            list($attribute, $direction) = $this->orderBy;

            $model = $model->orderBy($attribute, $direction);
        }

        return $model;
    }

    /**
     * Reset repository to it's defaults.
     *
     * @return $this
     */
    protected function resetRepository()
    {
        $this->relations  = [];
        $this->where      = [];
        $this->whereIn    = [];
        $this->whereNotIn = [];
        $this->offset     = null;
        $this->limit      = null;
        $this->orderBy    = [];
        //--------------------------
        $this->modelInstance = null;
        $this->cacheHashCondition = null;
        //--------------------------

        return $this;
    }

    protected function executeCallback($class, $method, $args, \Closure $closure)
    {
        $skipUri = $this->getContainer('config')->get('rinvex.repository.cache.skip_uri');

        // Check if cache is enabled
        if ($this->getCacheLifetime() && ! $this->getContainer('request')->has($skipUri)) {
            //use closure when redis throw a throwable
            try {
                $res = $this->cacheCallback($class, $method, $args, $closure);
            } catch (\Throwable $throwable){
                Log::error('repository 获取缓存失败' .$throwable->getMessage() . json_encode($throwable->getTrace()));
                $res = call_user_func($closure);
            }
            return $res;
        }

        // Cache disabled, just execute qurey & return result
        $result = call_user_func($closure);

        // We're done, let's clean up!
        $this->resetRepository();

        return $result;
    }
}