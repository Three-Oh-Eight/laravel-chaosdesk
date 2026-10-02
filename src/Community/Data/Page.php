<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community\Data;

use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * One page of a paginated community list.
 *
 * @template TItem
 *
 * @implements IteratorAggregate<int, TItem>
 */
final readonly class Page implements Countable, IteratorAggregate
{
    /**
     * @param  list<TItem>  $items
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {}

    /**
     * Build a page from a Laravel paginator response (`data`, `meta`).
     *
     * @template TMapped
     *
     * @param  array<string, mixed>  $response
     * @param  Closure(array<string, mixed>): TMapped  $map
     * @return self<TMapped>
     */
    public static function fromResponse(array $response, Closure $map): self
    {
        $meta = Values::nested($response, 'meta') ?? [];
        $items = array_map($map, Values::items($response, 'data'));

        return new self(
            $items,
            Values::int($meta, 'current_page', 1),
            Values::int($meta, 'last_page', 1),
            Values::int($meta, 'per_page', count($items)),
            Values::int($meta, 'total', count($items)),
        );
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<int, TItem>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
