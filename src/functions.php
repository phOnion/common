<?php

declare(strict_types=1);

namespace Onion\Framework;

use Closure;

if (!function_exists(__NAMESPACE__ . '\merge')) {
    function merge(array $initial, array ...$arrays): array
    {
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (isset($initial[$key])) {
                    if (is_int($key)) {
                        $initial[] = $value;
                        continue;
                    }

                    if (is_array($value) && is_array($initial[$key])) {
                        $initial[$key] = merge($initial[$key], $value);
                        continue;
                    }

                    $initial[$key] = $value;
                    continue;
                }

                $initial[$key] = $value;
            }
        }
        return $initial;
    }
}

if (!function_exists(__NAMESPACE__ . '\normalize_tree_keys')) {
    /**
     * Builds a tree of normalized single-value keys from
     */
    function normalize_tree_keys(iterable $input, string $separator = '.')
    {
        $result = [];

        foreach ($input as $key => $value) {
            $ptr = &$result;

            $parts = explode($separator, trim($key, '/'));
            while (count($parts) > 1) {
                $key = array_shift($parts);

                if (!isset($ptr[$key])) {
                    $ptr[$key] = [];
                }
                $ptr = &$ptr[$key];
            }

            $ptr[array_shift($parts)] = $value;
        }

        return $result;
    }
}

if (!function_exists(__NAMESPACE__ . '\find_by_separator')) {
    function find_by_separator(array $storage, string $key, string $separator = '.')
    {
        $fragments = explode($separator, $key);

        $cursor = &$storage;
        while ($fragments !== []) {
            $key = array_shift($fragments);
            if (!isset($cursor[$key])) {
                throw new \LogicException(
                    "Unable to resolve '{$key}' of '{$key}'"
                );
            }

            $cursor = &$cursor[$key];
        }

        return $cursor;
    }
}

if (!function_exists(__NAMESPACE__ . '\generator')) {
    function generator(iterable | callable $generatorFn): \Iterator
    {
        return new class($generatorFn) implements \Iterator
        {
            private $function;
            /** @var \Generator */
            private $iterable;

            public function __construct(iterable | callable $callable)
            {
                if (!is_callable($callable)) {
                    $callable = function () use ($callable) {
                        yield from $callable;
                    };
                }

                $this->function = Closure::fromCallable($callable);
                $generator = ($this->function)();
                if (!$generator instanceof \Generator) {
                    throw new \InvalidArgumentException('Provided callback must be a valid generator');
                }

                $this->iterable = $generator;
            }

            public function rewind(): void
            {
                $this->iterable = ($this->function)();
            }

            public function next(): void
            {
                $this->iterable->next();
            }

            public function key(): mixed
            {
                return $this->iterable->key();
            }

            public function current(): mixed
            {
                return $this->iterable->current();
            }

            public function valid(): bool
            {
                return $this->iterable->valid();
            }

            public function __clone()
            {
                $this->iterable = call_user_func($this->function);
            }
        };
    }
}
