<?php

namespace Onion\Framework\Hydrator;

use Onion\Framework\Hydrator\Interfaces\HydratableInterface;
use ReflectionMethod;
use ReflectionObject;

trait MethodHydrator
{
    public function hydrate(iterable $data): HydratableInterface
    {
        /** @var HydratableInterface $target */
        $target = $this;
        $getSetterName = fn ($name): string => 'set' . \lcfirst(\str_replace(' ', '', \ucfirst(\str_replace('_', ' ', $name))));

        foreach ($data as $key => $value) {
            $setterName = $getSetterName($key);
            if (\method_exists($target, $setterName)) {
                $target->{$setterName}($value);
            }
        }

        return $target;
    }

    public function extract(array $keys = [], bool $includeEmpty = true): array
    {
        $reflection = new ReflectionObject($this);
        $result = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (count($method->getParameters()) !== 0) {
                continue;
            }

            $name = $method->getName();
            if (substr($name, 0, 2) === 'is') {
                $value = $this->{$name}();
                if (!$includeEmpty && $value === null) {
                    continue;
                }
                $prop = lcfirst(substr($name, 2));
                if (!empty($keys) && !in_array($prop, $keys, true)) {
                    continue;
                }

                $result[$prop] = $value;
            } else if (substr($name, 0, 3) === 'has' || substr($name, 0, 3) === 'get') {
                $value = $this->{$name}();
                if (!$includeEmpty && $value === null) {
                    continue;
                }
                $prop = lcfirst(substr($name, 3));
                if (!empty($keys) && !in_array($prop, $keys, true)) {
                    continue;
                }

                $result[$prop] = $this->{$name}();
            }
        }

        if (!$includeEmpty) {
            foreach ($result as $name => $value) {
                if ($value === null) {
                    unset($result[$value]);
                }
            }
        }

        return $result;
    }
}
