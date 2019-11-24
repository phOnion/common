<?php
declare(strict_types=1);
namespace Onion\Framework\Common\Collection\Interfaces;

interface CollectionInterface extends \Iterator
{
    const USE_VALUES_ONLY = 1;
    const USE_KEYS_ONLY = 2;
    const USE_BOTH = self::USE_KEYS_ONLY | self::USE_VALUES_ONLY;

    const PAD_LEFT = 3;
    const PAD_RIGHT = 4;

    public function map(callable $callback): CollectionInterface;
    public function filter(callable $callback): CollectionInterface;
    public function sort(callable $callback, string $sortFunction = "usort"): CollectionInterface;
    public function reduce(callable $callback, $initial = null);
    public function slice(int $offset, int $limit = -1): CollectionInterface;
    public function find($item);
    public function current();
    public function key();
    public function next(): void;
    public function rewind(): void;
    public function valid(): bool;
    public function count();
    public function keys(): CollectionInterface;
    public function values(): CollectionInterface;
    public function each(callable $callback): void;
    public function implode(string $separator);
    public function append(iterable $items): CollectionInterface;
    public function prepend(iterable $items);
    public function unique(): CollectionInterface;
    public function contains($item);
    public function intersect(iterable ...$elements);
    public function diff(iterable ...$elements);
    public function combine(iterable $values, int $mode = self::COMBINE_USE_VALUES);
    public function pad(int $length, $padding, int $direction = self::PAD_RIGHT);
    public function reverse();
    public function flip();
    public function validate(callable $callback);
    public function when(callable $expression, callable $callback);
    public function group(callable $grouping);
    public function join(iterable ...$iterables);
    public function serialize(string $serializeFn = "serialize");
    public function unserialize(string $unserializeFn = "unserialize");
    public function raw(): array;
}
