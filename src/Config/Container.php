<?php
namespace Onion\Framework\Common\Config;

use Psr\Container\ContainerInterface;
use function Onion\Framework\Common\find_by_separator;
use Onion\Framework\Dependency\Interfaces\AttachableContainer;

class Container implements ContainerInterface, AttachableContainer
{
    private const DETECTION_REGEX = '/^(?P<expression>(?P<operation>[a-z_\-]+)(?:\((?P<args>([^()]|(?R))*)\)|\:(?P<decorate>[^\s]+)))/i';

    private $storage = [];
    private $handlers = [];
    private $separator;

    private $delegate;

    public function __construct(array $configuration, array $handlers = [], string $separator = '.')
    {
        $this->storage = $configuration;
        $this->handlers = array_merge([
            'get' => function ($key) {
                return ($this->delegate ?? $this)->get($key);
            },
            'has' => function ($key) {
                return ($this->delegate ?? $this)->has($key);
            },
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
            $enum = implode('|', array_keys($this->handlers));
            $pattern = "/^{$enum}(?:\(|\:)/i";

            return $this->filterMetaValues($value, $pattern);
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
        preg_match(self::DETECTION_REGEX, $value, $matches);

        if (isset($matches['operation']) && isset($this->handlers[$matches['operation']])) {
            $handler = $this->handlers[$matches['operation']];

            assert(is_callable($handler), new \UnexpectedValueException(
                "Invalid handler registered for '{$matches['operation']}'"
            ));

            if (isset($matches['decorate'])) {
                return $this->getTyped(str_replace(
                    $matches['expression'],
                    call_user_func($handler, $this->getTyped($matches['decorate'])),
                     $value
                ));
            }

            if (isset($matches['args'])) {
                $args = explode(',', $matches['args']);
                $args = array_map(function ($value) {
                    return $this->processValue($value);
                }, $args);

                return call_user_func_array($handler, $args);
            }
        }

        return $value;
    }
}
