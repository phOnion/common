<?php
namespace Onion\Framework\Common\Config;

use function Onion\Framework\Common\find_by_separator;
use Onion\Framework\Dependency\Interfaces\AttachableContainer;
use Psr\Container\ContainerInterface;

class Container implements ContainerInterface, AttachableContainer
{
    private const DETECTION_REGEX = '/(?P<expression>(?P<operation>[a-z_\-]+)(?:\((?P<args>([^()]|(?R))*)\)|\:(?P<decorate>[^\s]+)))/i';

    private $storage = [];
    private $handlers = [];
    private $separator;

    private $delegate;

    public function __construct(array $configuration, array $handlers = [], string $separator = '.')
    {
        $this->storage = $configuration;
        $this->delegate = $this;
        $this->handlers = array_merge([
            'get' => function ($key) {
                return $this->delegate->get($key);
            },
            'has' => function ($key) {
                return $this->delegate->has($key);
            },
            'env' => 'getenv',
        ], $handlers);
        $this->separator = $separator;
    }

    /**
     * @codeCoverageIgnore
     */
    public function attach(ContainerInterface $container): void
    {
        $this->delegate = $container;
    }

    public function get($key)
    {
        $value = find_by_separator($this->storage, $key, $this->separator);

        if (is_string($value) && $this->handlers !== []) {
            return $this->processValue($value);
        }

        if (is_array($value)) {
            if (isset($value[0])) {
                return $this->filterMetaValues($value);
            }

            return new self($value, $this->handlers, $this->separator);
        }

        return $value;
    }

    private function filterMetaValues(array $values)
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

    public function has($key)
    {
        try {
            find_by_separator($this->storage, $key, $this->separator);

            return true;
        } catch (\LogicException $ex) {
            return false;
        }
    }

    private function getTyped($value)
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
                    $result = $this->getTyped(call_user_func($handler, $this->getTyped($match['decorate'])));
                }

                if (isset($match['args'])) {
                    $args = explode(',', $match['args']);
                    $args = array_map(function ($value) {
                        return $this->processValue($value);
                    }, $args);

                    $result = call_user_func_array($handler, $args);
                }

                if ($result !== null) {
                    if (!is_scalar($result)) {
                        $type = gettype($result);
                        throw new \LogicException(
                            "Bad template value, cannot use {$type} as string"
                        );
                    }

                    $value = $this->getTyped(str_replace($match['expression'], $result, $value));
                }
            }
        }

        return $value;
    }
}
