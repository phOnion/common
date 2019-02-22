<?php
namespace Onion\Framework\Common\Hydrator;

use Onion\Framework\Hydrator\Interfaces\HydratableInterface;

trait MethodHydrator
{
    public function hydrate(iterable $data, int $options = 0): HydratableInterface
    {
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

    public function extract(iterable $keys = [], int $options = 0): iterable
    {
        $target = [];

        $includeIsAndHas = ($options & self::EXTRACT_ALT_GETTERS) === self::EXTRACT_ALT_GETTERS;
        $underscoreKeys = ($options & self::USE_SNAKE_CASE) === self::USE_SNAKE_CASE;

        $getGetterName = $underscoreKeys ?
            function ($name): string {
                return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
            } :
            function ($name): string {
                return \str_replace('_', '', \ucwords($name, '_'));
            };



        $extractor = \Closure::bind(function () {
            return \array_keys(\get_object_vars($this));
        }, $this, static::class);

        $result = [];


        foreach ($extractor() as $key) {
            $name =  $getGetterName($key);
            if (($options & self::USE_RAW_KEYS) !== self::USE_RAW_KEYS) {
                $key = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
            }

            if ($keys !== [] && !\in_array($name, $keys)) {
                continue;
            }

            $getterName = $underscoreKeys ? strtolower("get_{$name}") : 'get'.ucfirst($name);
            $name = \lcfirst($name);


            if (\method_exists($this, $getterName)) {
                $result[$key] = $this->{$getterName}();
                continue;
            }


            if ($includeIsAndHas) {
                $getterName = $underscoreKeys ? "is_{$name}" : "is{$name}";
                var_dump($getterName);
                if (\method_exists($this, $getterName)) {
                    $result[$key] = $this->{$getterName}();
                    continue;
                }

                $getterName = $underscoreKeys ? "has_{$name}" : "has{$name}";
                if (\method_exists($this, $getterName)) {
                    $result[$key] = $this->{$getterName}();
                    continue;
                }
            }
        }

        return $result;
    }
}
