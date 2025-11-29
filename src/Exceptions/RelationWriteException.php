<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Exceptions;

use RuntimeException;

final class RelationWriteException extends RuntimeException
{
    /**
     * Private constructor - use static factory methods
     *
     * @param array  $succeededIds  IDs of successfully written records
     * @param array  $failedIndexes Indexes of records that failed
     * @param array  $errors        Per-item error messages indexed by position
     * @param string $message       Exception message
     */
    private function __construct(private readonly array $succeededIds, private readonly array $failedIndexes, private readonly array $errors, string $message)
    {
        parent::__construct($message);
    }

    public static function forTransactionRollback(
        array $succeededIds,
        array $failedIndexes,
        array $errors,
        string $operation,
        int $failedIndex,
    ): static {
        return new self(
            $succeededIds,
            $failedIndexes,
            $errors,
            lang('Relations.batchOperationRolledBack', [$operation, $failedIndex]),
        );
    }

    public static function forPartialFailure(
        array $succeededIds,
        array $failedIndexes,
        array $errors,
    ): static {
        $totalCount  = count($succeededIds) + count($failedIndexes);
        $failedCount = count($failedIndexes);

        return new self(
            $succeededIds,
            $failedIndexes,
            $errors,
            lang('Relations.batchWritePartialFailure', [$failedCount, $totalCount]),
        );
    }

    /**
     * Get IDs of successfully written records
     *
     * @return array<int, string>
     */
    public function succeededIds(): array
    {
        return $this->succeededIds;
    }

    /**
     * Get indexes of failed records
     */
    public function failedIndexes(): array
    {
        return $this->failedIndexes;
    }

    /**
     * Get per-item errors
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
