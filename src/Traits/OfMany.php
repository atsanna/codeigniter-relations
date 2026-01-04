<?php

declare(strict_types=1);

namespace Michalsn\CodeIgniterRelations\Traits;

use Michalsn\CodeIgniterRelations\Enums\OrderType;

/**
 * Provides "of many" functionality for singular relations (HasOne, MorphOne).
 *
 * Examples:
 * - User hasOne latestPost (latest of many posts)
 * - Post morphOne latestImage (latest of many images)
 */
trait OfMany
{
    /**
     * Column to use for "of many" selection
     */
    protected ?string $ofManyColumn = null;

    /**
     * Order direction for "of many" selection
     */
    protected ?OrderType $ofManyOrder = null;

    /**
     * Whether this is an "of many" relation
     */
    protected bool $isOfMany = false;

    /**
     * Get the latest related record
     *
     * Used when multiple records could match but you only want the most recent one.
     * Automatically uses createdField if timestamps are enabled, otherwise primaryKey.
     *
     * Example: User hasOne latestOrder (when user can have multiple orders)
     */
    public function latestOfMany(): self
    {
        return $this->ofMany($this->getOrderField(), OrderType::MAX);
    }

    /**
     * Get the oldest related record
     *
     * Used when multiple records could match but you only want the first one.
     * Automatically uses createdField if timestamps are enabled, otherwise primaryKey.
     *
     * Example: User hasOne firstOrder (when user can have multiple orders)
     */
    public function oldestOfMany(): self
    {
        return $this->ofMany($this->getOrderField(), OrderType::MIN);
    }

    /**
     * Determine the field to order by for ofMany methods
     *
     * Uses createdField if timestamps are enabled, otherwise falls back to primaryKey.
     */
    private function getOrderField(): string
    {
        if (get_model_property($this->model, 'useTimestamps')) {
            return get_model_property($this->model, 'createdField');
        }

        return get_model_property($this->model, 'primaryKey');
    }

    /**
     * Get one related record of many based on aggregate
     *
     * Used to select a specific record from multiple potential matches.
     *
     * Examples:
     * - Most expensive order: ofMany('price', OrderType::MAX)
     * - Cheapest order: ofMany('price', OrderType::MIN)
     * - Latest post: ofMany('created_at', OrderType::MAX)
     *
     * @param string     $column Column to aggregate on
     * @param OrderType $order  Aggregate function (MIN or MAX)
     */
    public function ofMany(string $column, OrderType $order): self
    {
        $this->ofManyColumn = $column;
        $this->ofManyOrder  = $order;
        $this->isOfMany     = true;

        return $this;
    }

    /**
     * Apply "of many" constraint to the query
     *
     * This method must be implemented by each relation class
     * as the constraint differs based on the relation type.
     *
     * @param list<int|string> $parentIds Parent IDs to filter by
     */
    abstract protected function applyOfManyConstraint(array $parentIds): void;
}
