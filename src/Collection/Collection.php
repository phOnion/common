<?php
declare(strict_types=1);

namespace Onion\Framework\Common\Collection;

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

    public function map(\Closure $callback): CollectionInterface
    {
        /** @var \Iterator|iterable $items */
        $items = new class($this, $callback) extends \IteratorIterator {
            private $callback;

            public function __construct(\Traversable $iterator, \Closure $callback)
            {
                parent::__construct($iterator);
                $this->callback = $callback;
            }

            public function current()
            {
                return ($this->callback)(parent::current());
            }
        };

        return new self($items);
    }

    public function filter(\Closure $callback): CollectionInterface
    {
        return new self(new \CallbackFilterIterator($this, $callback));
    }

    public function sort(\Closure $callback, string $sortFunction = 'usort'): CollectionInterface
    {
        $items = iterator_to_array($this);
        $items = $sortFunction($items, $callback) ?? $items;

        return new self($items);
    }

    public function reduce(\Closure $callback, $initial = null)
    {
        $result = $initial;
        foreach ($this as $value) {
            $result = $callback($result, $initial);
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
            return count(iterator_to_array($this));
        }

        return count($this->items);
    }

    public function keys(): CollectionInterface
    {
        return new self(array_keys(iterator_to_array($this, true)));
    }
}
