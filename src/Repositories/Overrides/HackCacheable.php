<?php
/**
 * Created by PhpStorm.
 * User: KEL
 * Date: 2017/1/18
 * Time: 13:55
 */

namespace App\Services\Repository\Overrides;


use Illuminate\Support\Facades\Request;

trait HackCacheable
{
    protected function generateCacheHash($args)
    {

        //查询时可能使用一些动态条件 like where('created_at', '>', time());会导致hashkey无法保持一致。
        if ($this->cacheHashCondition) {
            $result = md5(json_encode($this->cacheHashCondition));
        } else {
            $result = md5(json_encode($args + [
                    $this->getRepositoryId(),
                    $this->getModel(),
                    $this->getCacheDriver(),
                    $this->getCacheLifetime(),
                    $this->relations,
                    $this->join,
                    $this->where,
                    $this->whereIn,
                    $this->whereNotIn,
                    $this->offset,
                    $this->limit,
                    $this->orderBy
                ]));
        }

        return $result;
    }

    public function setCacheHashCondition($condition)
    {
        $this->cacheHashCondition = $condition;
        return $this;
    }


//    protected function cacheCallback($class, $method, $args, Closure $closure)
//    {
//        $repositoryId = $this->getRepositoryId();
//        $lifetime     = $this->getCacheLifetime();
//        $hash         = $this->generateCacheHash($args);
//        $cacheKey     = $class.'@'.$method.'.'.$hash;
//
//        // Switch cache driver on runtime
//        if ($driver = $this->getCacheDriver()) {
//            $this->getContainer('cache')->setDefaultDriver($driver);
//        }
//
//        // We need cache tags, check if default driver supports it
//        if (method_exists($this->getContainer('cache')->getStore(), 'tags')) {
//            $result = $lifetime === -1
//                ? $this->getContainer('cache')->tags($repositoryId)->rememberForever($cacheKey, $closure)
//                : $this->getContainer('cache')->tags($repositoryId)->remember($cacheKey, $lifetime, $closure);
//
//            // We're done, let's clean up!
//            $this->resetRepository();
//
//            return $result;
//        }
//
//        // Default cache driver doesn't support tags, let's do it manually
//        $this->storeCacheKeys($class, $method, $hash);
//        try {
//            $result = $lifetime === -1
//                ? $this->getContainer('cache')->rememberForever($cacheKey, $closure)
//                : $this->getContainer('cache')->remember($cacheKey, $lifetime, $closure);
//        } catch (\Throwable $t){
////            $result = $closure();
//        }
//        // We're done, let's clean up!
//        $this->resetCachedRepository();
//
//        return $result;
//    }
}