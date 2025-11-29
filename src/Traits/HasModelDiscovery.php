<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Traits;

use CodeIgniter\Autoloader\FileLocatorInterface;

/**
 * Provides automatic model class discovery for entities.
 * Searches for the corresponding model class based on naming conventions
 * and caches the result for performance.
 */
trait HasModelDiscovery
{
    /**
     * Cache of discovered model classes per entity class
     *
     * @var array<string, string|null>
     */
    private static array $modelClassCache = [];

    /**
     * Search for the proper model class
     *
     * Attempts to auto-discover the model class corresponding to this entity.
     * First searches in the same namespace structure (Entities -> Models),
     * then falls back to the base Models directory.
     *
     * Results are cached per entity class to avoid repeated file system searches.
     *
     * @internal
     *
     * @return string|null The fully qualified model class name or null if not found
     */
    public function findModelClass(): ?string
    {
        $entityClass = static::class;

        // Return cached result if available (even if null)
        if (array_key_exists($entityClass, self::$modelClassCache)) {
            return self::$modelClassCache[$entityClass];
        }

        /** @var FileLocatorInterface $locator */
        $locator = service('locator');

        $className     = $entityClass;
        $namespace     = substr($className, (int) strpos($className, 'Entities'));
        $baseNamespace = substr($namespace, 0, (int) strrpos($namespace, '\\'));
        $baseClassName = substr((string) strrchr($className, '\\'), 1);
        $modelPath     = str_replace('Entities', 'Models', $baseNamespace) . '\\' . $baseClassName . 'Model';

        $files = $locator->search(str_replace('\\', '/', $modelPath) . '.php');

        if ($files === []) {
            // Fallback to search in the base Models directory
            $modelPath = 'Models\\' . $baseClassName . 'Model';
            $files     = $locator->search(str_replace('\\', '/', $modelPath) . '.php');
        }

        $modelClass = ($files === [])
            ? null
            : $locator->findQualifiedNameFromPath($files[0]);

        // Cache the result (even if null to avoid repeated failed lookups)
        self::$modelClassCache[$entityClass] = $modelClass;

        return $modelClass;
    }
}
