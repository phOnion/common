<?php

namespace Onion\Framework\Config;

use Closure;
use Onion\Framework\Dependency\Traits\AttachableContainerTrait;
use Onion\Framework\Dependency\Traits\ContainerTrait;
use Onion\Framework\Dependency\Interfaces\AttachableContainer;
use Onion\Framework\Proxy\LazyFactory;
use Psr\Container\ContainerInterface;

use function Onion\Framework\Common\find_by_separator;

class Container implements ContainerInterface, AttachableContainer
{
    use ContainerTrait;
    use AttachableContainerTrait;

    private const DETECTION_REGEX =
    '/(?|(?P<expression>(?P<operation>[a-z_\-]+)(?:\((?P<args>([^\s]+)|(?R))*\)|\:(?P<decorate>[^\s]+))))/i';

    private $storage = [];
    private $handlers = [];
    private $separator;

    private $delegate;

    public function __construct(array $configuration, array $handlers = [], string $separator = '.')
    {
        $this->storage = $configuration;
        $this->delegate = $this;
        $this->handlers = array_merge([
            'get' => fn ($key) => $this->delegate->get($key),
            'has' => fn ($key) => $this->delegate->has($key),
            'env' => fn ($key) => getenv($key, true),
            'wrap' => fn ($key) => new static($this->get($key), array_map(fn ($h) => Closure::fromCallable($h), $this->handlers), $this->separator),
            'lazy' => function ($key) {
                $callable = fn () => $this->delegate->get($key);
                $class = $key;
                if (!class_exists($key)) {
                    throw new \InvalidArgumentException("Unable to use 'lazy' on {$key}. Not a class");
                }

                return $this->delegate
                    ->get(LazyFactory::class)
                    ->generate($callable, $class);
            }
        ], $handlers);
        $this->separator = $separator;
    }

    public function get($key)
    {
        assert(
            $this->isKeyValid($key),
            new \InvalidArgumentException('Invalid key. Expecting string, received ' . gettype($key))
        );


        try {
            $value = find_by_separator($this->storage, $key, $this->separator);
        } catch (\LogicException $ex) {
            $value = $this->processValue($key);
        }

        if (is_string($value) && $this->handlers !== []) {
            return $this->processValue($value);
        }

        if (is_array($value)) {
            if (isset($value[0]) || empty($value)) {
                return $this->filterMetaValues($value);
            }
        }

        return $value;
    }

    private function filterMetaValues(array $values): array
    {
        return array_map(function ($member) {
            if (is_array($member)) {
                return $this->filterMetaValues($member);
            }

            if (!is_string($member)) {
                return $member;
            }

            return $this->processValue($member);
        }, $values);
    }

    public function has($key): bool
    {
        try {
            find_by_separator($this->storage, $this->normalizeKey($key), $this->separator);

            return true;
        } catch (\LogicException $ex) {
            return false;
        }
    }

    private function normalizeKey(string $key)
    {
        $count = preg_match_all(self::DETECTION_REGEX, $key, $matches, PREG_SET_ORDER);

        if ($count > 0) {
            $targetKey = $intermediate = $key;
            foreach ($matches as $index => $match) {
                $targetKey = !empty($match['args']) ? $match['args'] : $match['decorate'];
                if ($index === 0) {
                    $intermediate = $targetKey;
                }
                if ($intermediate !== $targetKey) {
                    $targetKey = $this->get("{$match['operation']}({$targetKey})");
                }
            }

            $key = $targetKey;
        }

        return $key;
    }

    private function getTyped(string $value): int | float | string
    {
        $value = trim($value);
        if (preg_match('/^\d+$/i', $value)) {
            return (int) $value;
        }

        // handle exponents and negative numbers
        if (preg_match('/^[\-+]?[\d\.]+(?:[e\d+\-]+)?\d$/i', $value)) {
            return (float) $value;
        }

        return trim($value, '\'"');
    }

    private function processValue(string $value)
    {
        $value = trim($value);
        preg_match_all(self::DETECTION_REGEX, $value, $matches, PREG_SET_ORDER);

        // $value
        foreach ($matches as $match) {
            $match = array_filter($match);

            if (isset($match['operation']) && isset($this->handlers[$match['operation']])) {
                $handler = $this->handlers[$match['operation']];

                assert(is_callable($handler), new \UnexpectedValueException(
                    "Invalid handler registered for '{$match['operation']}'"
                ));

                $result = null;
                if (isset($match['decorate'])) {
                    $result = $this->getTyped($handler($this->processValue($this->getTyped($match['decorate']))));
                }

                if (isset($match['args'])) {
                    $args = explode(',', $match['args']);
                    $args = array_map(function ($value) {
                        return $this->processValue($value);
                    }, $args);

                    $result = $handler(...$args);
                }

                /** @var array<array-key, float|int|string>|string $result */
                if ($result !== null) {
                    if (is_array($result)) {
                        $result = $this->filterMetaValues($result);
                    } elseif (is_scalar($result)) {
                        $value = $this->getTyped(str_replace($match['expression'], $result, (string) $value));
                    } else {
                        $value = $result;
                    }
                }
            }
        }

        return $value;
    }
}
