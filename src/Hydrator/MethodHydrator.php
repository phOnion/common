<?php
namespace Onion\Framework\Common\Hydrator;

use Onion\Framework\Hydrator\Interfaces\HydratableInterface;

class MethodHydrator
{
    public const USE_SNAKE_CASE = 1;
    public const USE_RAW_KEYS = 2;
    public const EXTRACT_ALT_GETTERS = 4;

    public function hydrate(iterable $data, int $options = 0): HydratableInterface
    {
        $target = clone $this;
        if (!($options & self::USE_RAW_KEYS === self::USE_RAW_KEYS)) {
            $underscoreKeys = ($options & self::USE_SNAKE_CASE) === self::USE_SNAKE_CASE;
            $getSetterName = $underscoreKeys ?
                function ($name): string {
                    return 'set_' . \strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
                } :
                function ($name): string {
                    return 'set' . \ucfirst(\str_replace('_', '', $name));
                };
        } else {
            $getSetterName = function ($name): string {
                return $name;
            };
        }

        foreach ($data as $key => $value) {
            $setterName = $getSetterName($key);
            if (\method_exists($target, $setterName)) {
                $target->{$setterName}($value);
            }
        }

        return $target;
    }

    public function extract(iterable $keys = [], int $options = 0)
    {
        $target = clone $this;

        $includeIsAndHas = $options & self::EXTRACT_ALT_GETTERS;
        $underscoreKeys = false;

        if (!($options & self::USE_RAW_KEYS === self::USE_RAW_KEYS)) {
            $underscoreKeys = ($options & self::USE_SNAKE_CASE) === self::USE_SNAKE_CASE;
            $getGetterName = $underscoreKeys ?
                function ($name): string {
                    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
                } :
                function ($name): string {
                    return \str_replace('_', '', \ucwords($name, '_'));
                };
        } else {
            $getGetterName = function ($name): string {
                return $name;
            };
        }

        $extractor = \Closure::bind(function () {
            return \get_object_vars($this);
        }, $this, static::class);

        $result = [];

        foreach ($extractor() as $key) {
            $name =  $getGetterName($key);
            if ($keys !== [] && !\in_array($name, $keys)) {
                continue;
            }

            $getterName = $underscoreKeys ? "get_{$name}" : "get{$name}";
            $name = \lcfirst($name);

            if (\method_exists($this, $getterName)) {
                $result[$name] = $target->{$getterName}();
                continue;
            }

            if ($includeIsAndHas) {
                $getterName = $underscoreKeys ? "is_{$name}" : "is{$name}";
                if (\method_exists($this, $getterName)) {
                    $result[$name] = $target->{$getterName}();
                    continue;
                }

                $getterName = $underscoreKeys ? "has_{$name}" : "has{$name}";
                if (\method_exists($this, $getterName)) {
                    $result[$name] = $target->{$getterName}();
                    continue;
                }
            }
        }

        return $target;
    }
}
