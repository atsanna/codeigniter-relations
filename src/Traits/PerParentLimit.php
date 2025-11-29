<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Traits;

use ReflectionException;
use ReflectionObject;

/**
 * Supports per-parent limiting in eager loading
 *
 * Enables relations to apply LIMIT clauses per parent record instead of globally
 * by using SQL window functions (ROW_NUMBER() OVER PARTITION BY).
 *
 * This trait provides the functionality to transform queries like:
 *   SELECT * FROM posts WHERE user_id IN (1,2) ORDER BY created_at DESC LIMIT 2
 * Into:
 *   SELECT * FROM (
 *     SELECT *, ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at DESC) AS __rn
 *     FROM posts WHERE user_id IN (1,2)
 *   ) AS __t WHERE __rn <= 2
 *
 * Requires: MySQL 8.0+, PostgreSQL 9.0+, SQLite 3.25+, or SQL Server 2005+
 */
trait PerParentLimit
{
    /**
     * Check if the query builder has a LIMIT clause
     *
     * @return bool True if LIMIT is set, false otherwise
     */
    protected function hasLimitInQuery(): bool
    {
        try {
            $builder    = $this->model->builder();
            $reflection = new ReflectionObject($builder);
            $property   = $reflection->getProperty('QBLimit');
            $limit      = $property->getValue($builder);

            return $limit !== false;
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * Execute query with window function for per-parent limiting
     *
     * @param array  $parentIds  Array of parent IDs
     * @param string $foreignKey The foreign key column name (may include table prefix)
     *
     * @return array Array of related records
     */
    protected function executeWithWindowFunction(array $parentIds, string $foreignKey): array
    {
        // Get compiled SQL
        $compiledSql = $this->model->builder()->getCompiledSelect(false);

        // Extract components from SQL
        $orderBy = $this->extractOrderBy($compiledSql);
        $limit   = $this->extractLimit($compiledSql);
        $offset  = $this->extractOffset($compiledSql);

        // If no limit found, fall back to standard query
        if ($limit === null) {
            return $this->model->findAll();
        }

        // If no ORDER BY specified, use primary key
        if ($orderBy === null) {
            $primaryKey = get_model_property($this->model, 'primaryKey');
            $orderBy    = "{$primaryKey} ASC";
        }

        // Remove ORDER BY, LIMIT, OFFSET from base query
        $baseQuery = $this->removeOrderByLimitOffset($compiledSql);

        // Strip table prefixes from foreign key for partition column
        // e.g., "course_student.student_id" becomes "student_id"
        $partitionColumn = $this->stripTablePrefixes($foreignKey);

        // Strip table prefixes from ORDER BY clause
        $orderBy = $this->stripTablePrefixes($orderBy);

        // Build window function query
        $sql = "SELECT __t.* FROM (
            SELECT __base.*, ROW_NUMBER() OVER (
                PARTITION BY __base.{$partitionColumn}
                ORDER BY {$orderBy}
            ) AS __rn
            FROM ({$baseQuery}) AS __base
        ) AS __t
        WHERE __t.__rn > ? AND __t.__rn <= ?";

        // Execute with bindings for row number filter
        $result = $this->model->db->query($sql, [$offset, $offset + $limit]);

        return $result->getResult(get_model_property($this->model, 'tempReturnType'));
    }

    /**
     * Extract ORDER BY clause from compiled SQL
     *
     * @param string $sql The compiled SQL query
     *
     * @return string|null The ORDER BY clause or null if not found
     */
    protected function extractOrderBy(string $sql): ?string
    {
        // Match: ORDER BY ... (until LIMIT, OFFSET, or end of string)
        if (preg_match('/ORDER\s+BY\s+(.+?)(?:\s+LIMIT|\s+OFFSET|$)/is', $sql, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Extract LIMIT value from compiled SQL
     *
     * @param string $sql The compiled SQL query
     *
     * @return int|null The LIMIT value or null if not found
     */
    protected function extractLimit(string $sql): ?int
    {
        if (preg_match('/LIMIT\s+(\d+)/i', $sql, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Extract OFFSET value from compiled SQL
     *
     * @param string $sql The compiled SQL query
     *
     * @return int The OFFSET value (0 if not found)
     */
    protected function extractOffset(string $sql): int
    {
        if (preg_match('/OFFSET\s+(\d+)/i', $sql, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Remove ORDER BY, LIMIT, and OFFSET clauses from SQL
     *
     * @param string $sql The compiled SQL query
     *
     * @return string The SQL without ORDER BY, LIMIT, OFFSET
     */
    protected function removeOrderByLimitOffset(string $sql): string
    {
        // Remove ORDER BY clause
        $sql = preg_replace('/\s+ORDER\s+BY\s+.+?(?=\s+LIMIT|\s+OFFSET|$)/is', '', $sql);

        // Remove LIMIT clause
        $sql = preg_replace('/\s+LIMIT\s+\d+/i', '', (string) $sql);

        // Remove OFFSET clause
        $sql = preg_replace('/\s+OFFSET\s+\d+/i', '', (string) $sql);

        return trim((string) $sql);
    }

    /**
     * Strip table prefixes from column references
     *
     * Converts "table.column" to "column" and handles multiple columns
     * separated by commas (common in ORDER BY clauses).
     * Supports both plain and backtick-quoted identifiers.
     *
     * Examples:
     *   "users.id" -> "id"
     *   "`users`.`id`" -> "`id`"
     *   "posts.created_at DESC" -> "created_at DESC"
     *   "courses.title ASC, courses.id DESC" -> "title ASC, id DESC"
     *
     * @param string $columns Column reference(s), possibly with table prefixes
     *
     * @return string Column reference(s) without table prefixes
     */
    protected function stripTablePrefixes(string $columns): string
    {
        // Handle both plain (table.column) and quoted (`table`.`column`) identifiers
        return (string) preg_replace(
            '/`?\b\w+`?\./i',
            '',
            $columns,
        );
    }
}
