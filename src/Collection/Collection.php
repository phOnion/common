<?php

declare(strict_types=1);

namespace Onion\Framework\Collection;

use AppendIterator;
use ArrayIterator;
use CallbackFilterIterator;
use Generator;
use InvalidArgumentException;
use Iterator;
use IteratorIterator;
use LimitIterator;
use Onion\Framework\Collection\Interfaces\CollectionInterface;
use Onion\Framework\Collection\Types\CollectionPad;
use Onion\Framework\Collection\Types\CollectionPart;
use RuntimeException;

use function array_keys;
use function array_reverse;
use function array_values;
use function count;
use function Onion\Framework\generator;
use function implode;
use function in_array;
use function is_array;
use function is_countable;
use function is_object;
use function iterator_to_array;
use function sprintf;

class Collection implements CollectionInterface
{
    private Iterator $items;

    public function __construct(iterable $items)
    {
        $this->items = generator(function () use ($items) {
            foreach ($items as $key => $value) {
                yield $key => $value;
            }
        });
    }

    public function __clone()
    {
        if (is_object($this->items)) {
            try {
                $this->items = clone $this->items;
            } catch (\Error $ex) {
                throw new RuntimeException(
                    'Unable to clone collection items',
                    previous: $ex
                );
            }
        }
    }

    public function offsetSet($offset, $value): void
    {
        $items = $this->raw();
        $items[$offset] = $value;

        $this->items = new static($items);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->raw()[$offset] ?? null;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->raw()[$offset]);
    }

    public function offsetUnset($offset): void
    {
        $items = $this->raw();
        unset($items[$offset]);

        $this->items = new static($items);
    }

    public function map(callable $callback): static
    {
        $self = clone $this;
        return new static(generator(function () use ($self, $callback) {
            foreach ($self as $key => $value) {
                yield $key => $callback($value);
            }
        }));
    }

    public function filter(callable $callback): static
    {
        return new static(new CallbackFilterIterator(new ArrayIterator($this->raw()), $callback));
    }

    public function sort(callable $callback, string $sortFunction = 'usort'): static
    {
        $items = $this->raw();
        $sortFunction($items, $callback);

        return new static($items);
    }

    public function reduce(callable $callback, mixed $initial = null): static
    {
        $result = $initial;
        foreach ($this as $value) {
            $result = $callback($result, $value);
        }

        return $result;
    }

    public function slice(int $offset, int $limit = -1): static
    {
        return new static(
            new LimitIterator($this, $offset, $limit)
        );
    }

    public function find(mixed $item): mixed
    {
        $self = clone $this;
        foreach ($self as $index => $value) {
            if ($value === $item) {
                return $index;
            }
        }

        return null;
    }

    public function current(): mixed
    {
        return $this->items->current();
    }

    public function key(): mixed
    {
        return $this->items->key();
    }

    public function next(): void
    {
        $this->items->next();
    }

    public function rewind(): void
    {
        $this->items->rewind();
    }

    public function valid(): bool
    {
        return $this->items->valid();
    }

    public function count(): int
    {
        return count($this->raw());
    }

    public function keys(): static
    {
        return new static(array_keys($this->raw()));
    }

    public function values(): static
    {
        return new static(array_values($this->raw()));
    }

    public function each(callable $callback): void
    {
        foreach ($this as $key => $value) {
            $callback($value, $key);
        }
    }

    public function implode(string $separator): string
    {
        return implode($separator, iterator_to_array($this));
    }

    public function append(iterable $items): static
    {
        if (is_array($items)) {
            $items = new ArrayIterator($items);
        }

        $iterator = $this->items;
        if (!$iterator instanceof AppendIterator) {
            $iterator = new AppendIterator();
            $iterator->append($this);
        }

        $iterator->append(new IteratorIterator($items));

        return new static($iterator);
    }

    public function remove($item): static
    {
        return $this->filter(fn ($existing) => $existing !== $item);
    }

    public function prepend(iterable $items): static
    {
        if (is_array($items)) {
            $items = new ArrayIterator($items);
        }

        $iterator = new AppendIterator();
        $iterator->append($this);
        $iterator->append(new IteratorIterator($items));

        return new static($iterator);
    }

    public function unique(): static
    {
        $self = clone $this;

        return new static(generator(function () use ($self) {
            $values = [];

            foreach ($self as $key => $value) {
                if (!in_array($value, $values, true)) {
                    $values[] = $value;

                    yield $key => $value;
                }
            }
        }));
    }

    public function contains(mixed $item): bool
    {
        return $this->find($item) !== null;
    }

    public function intersect(iterable ...$elements): static
    {
        $result = [];
        $self = clone $this;

        foreach ($self as $key => $item) {
            foreach ($elements as $element) {
                if (!$element instanceof CollectionInterface) {
                    $element = new static($element);
                }

                if (!$element->contains($item)) {
                    continue 2;
                }
            }

            $result[$key] = $item;
        }

        return new static($result);
    }

    public function diff(iterable ...$elements): static
    {
        $self = clone $this;

        $generator =
            function () use ($self, $elements): Generator {
                foreach ($self as $key => $value) {
                    foreach ($elements as $element) {
                        if (!$element instanceof CollectionInterface) {
                            $element = new static($element);
                        }

                        if ($element->contains($value)) {
                            continue 2;
                        }
                    }

                    yield $key => $value;
                }
            };

        return new static(generator($generator));
    }

    public function combine(iterable $values, CollectionPart $mode = CollectionPart::VALUES): static
    {
        $values = generator(function () use ($values) {
            foreach ($values as $key => $value) {
                yield $key => $value;
            }
        });

        if (!is_countable($values) || count($this) !== count($values)) {
            throw new InvalidArgumentException(sprintf(
                'Values count (%d) must be equal to key count (%d)',
                is_countable($values) ? count($values) : -1,
                count($this)
            ));
        }

        $self = clone $this;
        $generator =
            function () use ($self, $values, $mode): Generator {
                $self->rewind();
                $values->rewind();

                while ($self->valid() && $values->valid()) {
                    $key = $self->current();
                    if ($mode === CollectionPart::KEYS) {
                        $key = $self->key();
                    }

                    yield $key => $values->current();

                    $self->next();
                    $values->next();
                }
            };

        return new static(generator($generator));
    }

    public function pad(int $length, mixed $padding, CollectionPad $direction = CollectionPad::RIGHT): static
    {
        $itemsCount = count($this);
        $count = $length - $itemsCount;

        $result = $this;
        if ($direction === CollectionPad::RIGHT) {
            $suffix = [];
            for ($i = 0; $i < $count; $i++) {
                $suffix[] = $padding;
            }

            $result = $this->append($suffix);
        }

        if ($direction === CollectionPad::LEFT) {
            $prefix = [];
            for ($i = 0; $i < $count; $i++) {
                $prefix[] = $prefix;
            }

            $result = $this->prepend($prefix);
        }

        return $result;
    }

    public function reverse(): static
    {
        return new static(array_reverse($this->raw(), true));
    }

    public function flip(): static
    {
        $self = clone $this;
        $generator =
            function () use ($self): Generator {
                while ($self->valid()) {
                    yield $self->current() => $self->key();
                    $self->next();
                }
            };

        return new static(generator($generator));
    }

    public function validate(callable $callback): bool
    {
        foreach ($this as $key => $value) {
            if (!$callback($value, $key)) {
                return false;
            }
        }

        return true;
    }

    public function when(callable $expression, callable $callback): static
    {
        $self = clone $this;
        foreach ($self as $key => $value) {
            if ($expression($value, $key)) {
                $callback($value, $key);
            }
        }

        return $this;
    }

    public function group(callable $grouping): static
    {
        $result = [];
        foreach ($this as $key => $value) {
            $result[$grouping($value, $key)][] = $value;
        }

        return new static($result);
    }

    public function join(iterable ...$iterables): static
    {
        $self = clone $this;
        return new static(generator(function () use ($iterables, $self) {
            foreach ($iterables as $iterable) {
                foreach ($iterable as $element) {
                    foreach ($self as $index => $item) {
                        yield $index => [$item, $element];
                    }
                }
            }
        }));
    }

    public function serialize(callable|string $fn = 'serialize'): static
    {
        $self = clone $this;
        return new static(generator(function () use ($self, $fn) {
            foreach ($self as $key => $value) {
                yield $key => $fn($value);
            }
        }));
    }

    public function unserialize(callable|string $fn = 'unserialize'): static
    {
        $self = clone $this;
        return new static(generator(function () use ($self, $fn) {
            foreach ($self as $key => $value) {
                yield $key => $fn($value);
            }
        }));
    }

    public function raw(): array
    {
        $values = iterator_to_array($this, true);
        foreach ($values as $key => $value) {
            if ($value instanceof CollectionInterface) {
                $values[$key] = $value->raw();
            }
        }

        return $values;
    }

    public static function aggregate(iterable $collections): static
    {
        return new static(generator(function () use ($collections) {
            foreach ($collections as $collection) {
                foreach ($collection as $key => $value) {
                    yield $key => $value;
                }
            }
        }));
    }
}
