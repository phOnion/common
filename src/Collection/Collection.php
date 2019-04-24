<?php
declare(strict_types=1);

namespace Onion\Framework\Common\Collection;

use function Onion\Framework\Common\generator;
use Onion\Framework\Common\Collection\Interfaces\CollectionInterface;

class Collection implements CollectionInterface, \Countable
{
    private $items;

    public function __construct(iterable $items)
    {
        if (is_array($items)) {
            $items = new \ArrayIterator($items);
        }

        $this->items = $items;
    }

    public function map(callable $callback): CollectionInterface
    {
        $self = clone $this;
        return new static(generator(function () use ($self, $callback) {
            foreach ($self as $key => $value) {
                yield $key => call_user_func($callback, $value);
            }
        }));
    }

    public function filter(callable $callback): CollectionInterface
    {
        return new static(new \CallbackFilterIterator($this, $callback));
    }

    public function sort(callable $callback, string $sortFunction = 'usort'): CollectionInterface
    {
        $items = iterator_to_array($this);

        return new static(call_user_func($sortFunction, $items, $callback));
    }

    public function reduce(callable $callback, $initial = null)
    {
        $result = $initial;
        foreach ($this as $value) {
            $result = call_user_func($callback, $result, $value);
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
        foreach ($this as $index => $value) {
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
        return count($this->raw());
    }

    public function keys(): CollectionInterface
    {
        return new static(array_keys($this->raw()));
    }

    public function values(): CollectionInterface
    {
        return new static(array_values($this->raw()));
    }

    public function each(callable $callback): void
    {
        foreach ($this as $key => $value) {
            call_user_func($callback, $value, $key);
        }
    }

    public function implode(string $separator): string
    {
        return implode($separator, iterator_to_array($this));
    }

    public function append(iterable $items): CollectionInterface
    {
        if (is_array($items)) {
            $items = new \ArrayIterator($items);
        }

        $iterator = $this->items;
        if (!$iterator instanceof \AppendIterator) {
            $iterator = new \AppendIterator($this);
        }

        $iterator->append($items);

        return new static($iterator);
    }

    public function prepend(iterable $items)
    {
        if (is_array($items)) {
            $items = new \ArrayIterator($items);
        }

        $iterator = new \AppendIterator($items);
        $iterator->append($this);

        return new static($iterator);
    }

    public function unique(): CollectionInterface
    {
        $items = [];
        foreach ($this as $target) {
            $i = 0;
            foreach ($this as $key => $item) {
                if ($target === $item) {
                    $i++;
                }

                if ($i === 1) {
                    $items[$key] = $item;
                }
            }
        }

        return new static($items);
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
        if (is_array($values)) {
            $values = new \ArrayIterator($values);
        }

        if (count($this) !== count($values)) {
            throw new \InvalidArgumentException(sprintf(
                'Values count (%d) must be equal to key count (%d)',
                count($values),
                count($this)
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
        $itemsCount = count($this);
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
        return new static(array_reverse($this->raw(), true));
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
            if (!call_user_func($callback, $value, $key)) {
                return false;
            }
        }

        return true;
    }

    public function when(callable $expression, callable $callback)
    {
        $self = clone $this;
        foreach ($self as $key => $value) {
            if (call_user_func($expression, $value, $key)) {
                call_user_func($callback, $value, $key);
            }
        }

        return $this;
    }

    public function group(callable $grouping)
    {
        $result = [];
        foreach ($this as $key => $value) {
            $result[call_user_func($grouping, $value, $key)][] = $value;
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
                yield $key => call_user_func($serializeFn, $value);
            }
        }));
    }

    public function unserialize(string $unserializeFn = 'unserialize')
    {
        $self = clone $this;
        return new static(generator(function () use ($self, $unserializeFn) {
            foreach ($self as $key => $value) {
                yield $key => call_user_func($unserializeFn, $value);
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
}
