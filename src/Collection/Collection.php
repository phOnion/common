<?php
declare(strict_types=1);

namespace Onion\Framework\Common\Collection;

use Onion\Framework\Common\Collection\Interfaces\CollectionInterface;

class Collection implements CollectionInterface, \Countable
{
    public const COMBINE_USE_VALUES = 1;
    public const COMBINE_USE_KEYS = 2;

    public const PAD_LEFT = 3;
    public const PAD_RIGHT = 4;

    private $items;

    public function __construct(iterable $items)
    {
        if (is_array($items)) {
            $items = new \ArrayIterator($items);
        }

        $this->items = new \CachingIterator($items, \CachingIterator::FULL_CACHE);
    }

    public function map(callable $callback): CollectionInterface
    {
        /** @var \Iterator|iterable $items */
        $items = new class($this, $callback) extends \IteratorIterator {
            private $callback;

            public function __construct(\Traversable $iterator, callable $callback)
            {
                parent::__construct($iterator);
                $this->callback = $callback;
            }

            public function current()
            {
                return call_user_func($this->callback, parent::current());
            }
        };

        return new self($items);
    }

    public function filter(callable $callback): CollectionInterface
    {
        return new self(new \CallbackFilterIterator($this, $callback));
    }

    public function sort(callable $callback, string $sortFunction = 'usort'): CollectionInterface
    {
        $items = iterator_to_array($this);

        return new self(call_user_func($sortFunction, $items, $callback));
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
        return new self(
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
        if (!is_countable($this->items)) {
            return count($this->raw());
        }

        return count($this->items);
    }

    public function keys(): CollectionInterface
    {
        return new self(array_keys($this->raw()));
    }

    public function values(): self
    {
        return new self(array_values($this->raw()));
    }

    public function each(callable $callback): void
    {
        foreach ($this as $key => $value) {
            call_user_func($callback, $value, $key);
        }
    }

    public function join(string $separator)
    {
        return implode($separator, iterator_to_array($this));
    }

    public function append(iterable $items): self
    {
        if (is_array($items)) {
            $items = new \ArrayIterator($items);
        }

        $iterator = $this->items;
        if (!$iterator instanceof \AppendIterator) {
            $iterator = new \AppendIterator($this);
        }

        $iterator->append($items);

        return new self($iterator);
    }

    public function prepend(iterable $items)
    {
        if (is_array($items)) {
            $items = new \ArrayIterator($items);
        }

        $iterator = new \AppendIterator($items);
        $iterator->append($this);

        return new self($iterator);
    }

    public function unique(): self
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

        return new self($items);
    }

    public function contains($item)
    {
        foreach ($this as $node) {
            if ($node === $item) {
                return true;
            }
        }

        return false;
    }

    public function intersect(iterable ... $elements)
    {
        $result = [];

        foreach ($this as $key => $item) {
            foreach ($elements as $element) {
                if (!$element instanceof CollectionInterface) {
                    $element = new self($element);
                }

                if (!$element->contains($item)) {
                    continue 2;
                }
            }

            $result[$key] = $item;
        }

        return new self($result);
    }

    public function diff(iterable ... $elements)
    {
        $result = [];

        foreach ($this as $key => $value) {
            foreach ($elements as $element) {
                if (!$element instanceof CollectionInterface) {
                    $elements[$index] = new self($element);
                }

                if ($element->contains($value)) {
                    continue 2;
                }
            }

            $result[$key] = $value;
        }

        return new self($result);
    }

    public function combine(iterable $values, int $mode = self::COMBINE_USE_VALUES)
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

        $self = $this;
        $generator = function () use ($self, $values, $mode) {
            $self->rewind();
            $values->rewind();

            while ($self->valid() && $values->valid()) {
                $key = $self->current();
                if (($mode & self::COMBINE_USE_KEYS) === self::COMBINE_USE_KEYS) {
                    $key = $self->key();
                }

                yield $key => $values->current();

                $self->next();
                $values->next();
            }
        };

        return new self($generator());
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
        return new self(array_reverse($this->raw(), true));
    }

    public function flip()
    {
        $self = $this;
        $generator = function () use ($self) {
            while ($self->valid()) {
                yield $self->current() => $self->key();
                $self->next();
            }
        };

        return new self($generator());
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

    public function raw()
    {
        return iterator_to_array($this, true);
    }
}
