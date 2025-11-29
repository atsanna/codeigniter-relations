<?php

declare(strict_types=1);

use CodeIgniter\Model;

if (! function_exists('get_foreign_key')) {
    /**
     * Get foreign key based on model info.
     *
     * @param Model $model The model to extract foreign key from
     *
     * @return string Foreign key name (e.g., 'user_id')
     */
    function get_foreign_key(Model $model): string
    {
        static $cache = [];

        $modelClass = $model::class;

        // Return cached value if available
        if (isset($cache[$modelClass])) {
            return $cache[$modelClass];
        }

        helper('inflector');

        $table      = get_model_property($model, 'table');
        $primaryKey = get_model_property($model, 'primaryKey');

        $result = singular($table) . '_' . $primaryKey;

        // Cache the result
        $cache[$modelClass] = $result;

        return $result;
    }
}

if (! function_exists('get_model_property')) {
    /**
     * Get any property from model using reflection.
     *
     * @param Model  $model    The model instance
     * @param string $property The property name to retrieve
     */
    function get_model_property(Model $model, string $property): mixed
    {
        static $cache = [];

        $modelClass = $model::class;
        $cacheKey   = $modelClass . '::' . $property;

        // Return cached value if available
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $refObj  = new ReflectionObject($model);
        $refProp = $refObj->getProperty($property);
        $value   = $refProp->getValue($model);

        // Cache the result
        $cache[$cacheKey] = $value;

        return $value;
    }
}
