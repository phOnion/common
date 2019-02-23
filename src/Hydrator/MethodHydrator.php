<?php
namespace Onion\Framework\Common\Hydrator;

use Onion\Framework\Hydrator\Interfaces\HydratableInterface;

trait MethodHydrator
{
    public function hydrate(iterable $data, int $options = 0): HydratableInterface
    {
        /** @var HydratableInterface $target */
        $target = clone $this;
        if (($options & self::USE_RAW_KEYS) !== self::USE_RAW_KEYS) {
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
                return "set{$name}";
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

    public function extract(iterable $keys = [], int $options = 0): iterable
    {
        $includeIsAndHas = ($options & self::EXTRACT_ALT_GETTERS) === self::EXTRACT_ALT_GETTERS;
        $underscoreKeys = ($options & self::USE_SNAKE_CASE) === self::USE_SNAKE_CASE;
        $useRawKeys = ($options & self::USE_RAW_KEYS) === self::USE_RAW_KEYS;

        $extractor = function () use ($includeIsAndHas) {
            return array_filter(get_class_methods(static::class), function ($name) use ($includeIsAndHas) {
                $is = $has = false;

                if ($includeIsAndHas) {
                    $is = substr($name, 0, 2) === 'is';
                    $has = substr($name, 0, 3) === 'has';
                }

                if ($is || $has) {
                    return true;
                }

                return substr($name, 0, 3) === 'get';
            });
        };

        $result = [];
        foreach ($extractor() as $name) {
            $key = $name;
            if (!$useRawKeys || $underscoreKeys) {
                $key = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
            }
            $key = trim(preg_replace('/^(get|is|has)/', '', $key), '_');

            // $getterName = $underscoreKeys ? strtolower("get_{$name}") : 'get'.ucfirst($name);
            $name = $name;
            $result[$key] = $this->{$name}();
        }

        return array_filter($result, function ($key) use ($keys) {
            return in_array($key, $keys);
        }, ARRAY_FILTER_USE_KEY);
    }
}
