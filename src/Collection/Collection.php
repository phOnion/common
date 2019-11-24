<?php
declare(strict_types=1);

namespace Onion\Framework\Common\Collection;

use function Onion\Framework\Common\generator;
use function Onion\Framework\Common\is_cloneable;

use Onion\Framework\Common\Collection\Interfaces\CollectionInterface;

class Collection implements CollectionInterface, \Countable, \ArrayAccess
{
    private $items;

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
        if (\is_object($this->items) && is_cloneable($this->items)) {
            $this->items = clone $this->items;
        }
    }

    public function offsetSet($offset, $value)
    {
        $items = $this->raw();
        $items[$offset] = $value;

        $this->items = new static($items);
    }

    public function offsetGet($offset)
    {
        return $this->raw()[$offset] ?? null;
    }

    public function offsetExists($offset)
    {
        return isset($this->raw()[$offset]);
    }

    public function offsetUnset($offset)
    {
        $items = $this->raw();
        unset($items[$offset]);

        $this->items = new static($items);
    }

    public function map(callable $callback): CollectionInterface
    {
        $self = clone $this;
        return new static(generator(function () use ($self, $callback) {
            foreach ($self as $key => $value) {
                yield $key => \call_user_func($callback, $value);
            }
        }));
    }

    public function filter(callable $callback): CollectionInterface
    {
        return new static(new \CallbackFilterIterator(new \ArrayIterator($this->raw()), $callback));
    }

    public function sort(callable $callback, string $sortFunction = 'usort'): CollectionInterface
    {
        $items = $this->raw();
        $sortFunction($items, $callback);

        return new static($items);
    }

    public function reduce(callable $callback, $initial = null)
    {
        $result = $initial;
        foreach ($this as $value) {
            $result = \call_user_func($callback, $result, $value);
        }

        return $result;
    }

    public function slice(int $offset, int $limit = -1): CollectionInterface
    {
        return new static(
            new \LimitIterator($this, $offset, $limit)
        );
    }

    /**
     * @param mixed $item
     *
     * @return mixed|null
     */
    public function find($item)
    {
        $self = clone $this;
        foreach ($self as $index => $value) {
            if ($value === $item) {
                return $index;
            }
        }

        return null;
    }

    public function current()
    {
        return $this->items->current();
    }

    public function key()
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

    public function count()
    {
        return \count($this->raw());
    }

    public function keys(): CollectionInterface
    {
        return new static(\array_keys($this->raw()));
    }

    public function values(): CollectionInterface
    {
        return new static(\array_values($this->raw()));
    }

    public function each(callable $callback): void
    {
        foreach ($this as $key => $value) {
            \call_user_func($callback, $value, $key);
        }
    }

    public function implode(string $separator): string
    {
        return \implode($separator, (array) \iterator_to_array($this));
    }

    public function append(iterable $items): CollectionInterface
    {
        if (\is_array($items)) {
            $items = new \ArrayIterator($items);
        }

        $iterator = $this->items;
        if (!$iterator instanceof \AppendIterator) {
            $iterator = new \AppendIterator($this);
        }

        $iterator->append($items);

        return new static($iterator);
    }

    public function prepend(iterable $items): CollectionInterface
    {
        if (\is_array($items)) {
            $items = new \ArrayIterator($items);
        }

        $iterator = new \AppendIterator($items);
        $iterator->append($this);

        return new static($iterator);
    }

    public function unique(): CollectionInterface
    {
        $self = clone $this;

        return new self(generator(function () use ($self) {
            $values = [];

            foreach ($self as $key => $value) {
                if (!\in_array($value, $values)) {
                    $values[] = $value;

                    yield $key => $value;
                }
            }
        }));
    }

    public function contains($item)
    {
        return $this->find($item) !== null;
    }

    public function intersect(iterable ... $elements)
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

    public function diff(iterable ... $elements)
    {
        $self = clone $this;

        $generator = function () use ($self, $elements) {
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

    public function combine(iterable $values, int $mode = self::USE_VALUES_ONLY)
    {
        $values = generator(function () use ($values) {
            foreach ($values as $key => $value) {
                yield $key => $value;
            }
        });

        if (\count($this) !== \count($values)) {
            throw new \InvalidArgumentException(\sprintf(
                'Values count (%d) must be equal to key count (%d)',
                \count($values),
                \count($this)
            ));
        }

        $self = clone $this;
        $generator = function () use ($self, $values, $mode) {
            $self->rewind();
            $values->rewind();

            while ($self->valid() && $values->valid()) {
                $key = $self->current();
                if (($mode & self::USE_KEYS_ONLY) === self::USE_KEYS_ONLY) {
                    $key = $self->key();
                }

                yield $key => $values->current();

                $self->next();
                $values->next();
            }
        };

        return new static(generator($generator));
    }

    public function pad(int $length, $padding, int $direction = self::PAD_RIGHT)
    {
        $itemsCount = \count($this);
        $count = $length - $itemsCount;

        $result = $this;
        if (($direction & self::PAD_RIGHT) === self::PAD_RIGHT) {
            $suffix = [];
            for ($i=0; $i<$count; $i++) {
                $suffix[] = $padding;
            }

            $result = $this->append($suffix);
        }

        if (($direction & self::PAD_LEFT) === self::PAD_LEFT) {
            $prefix = [];
            for ($i=0; $i<$count; $i++) {
                $prefix[] = $prefix;
            }

            $result = $this->prepend($prefix);
        }

        return $result;
    }

    public function reverse()
    {
        return new static(\array_reverse($this->raw(), true));
    }

    public function flip()
    {
        $self = clone $this;
        $generator = function () use ($self) {
            while ($self->valid()) {
                yield $self->current() => $self->key();
                $self->next();
            }
        };

        return new static(generator($generator));
    }

    public function validate(callable $callback)
    {
        foreach ($this as $key => $value) {
            if (!\call_user_func($callback, $value, $key)) {
                return false;
            }
        }

        return true;
    }

    public function when(callable $expression, callable $callback)
    {
        $self = clone $this;
        foreach ($self as $key => $value) {
            if (\call_user_func($expression, $value, $key)) {
                \call_user_func($callback, $value, $key);
            }
        }

        return $this;
    }

    public function group(callable $grouping)
    {
        $result = [];
        foreach ($this as $key => $value) {
            $result[\call_user_func($grouping, $value, $key)][] = $value;
        }

        return new static($result);
    }

    public function join(iterable ... $iterables)
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

    public function serialize(string $serializeFn = 'serialize')
    {
        $self = clone $this;
        return new static(generator(function () use ($self, $serializeFn) {
            foreach ($self as $key => $value) {
                yield $key => \call_user_func($serializeFn, $value);
            }
        }));
    }

    public function unserialize(string $unserializeFn = 'unserialize')
    {
        $self = clone $this;
        return new static(generator(function () use ($self, $unserializeFn) {
            foreach ($self as $key => $value) {
                yield $key => \call_user_func($unserializeFn, $value);
            }
        }));
    }

    public function raw(): array
    {
        $values = (array) \iterator_to_array($this, true);
        foreach ($values as $key => $value) {
            if ($value instanceof CollectionInterface) {
                $values[$key] = $value->raw();
            }
        }

        return $values;
    }

    public static function aggregate(iterable $collections): CollectionInterface
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
