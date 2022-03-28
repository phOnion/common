<?php

declare(strict_types=1);

namespace Onion\Framework\Hydrator;

use Onion\Framework\Hydrator\Interfaces\HydratableInterface;
use ReflectionObject;

trait PropertyHydrator
{
    public function hydrate(iterable $data): HydratableInterface
    {
        foreach ($data as $name => $value) {
            $this->{$name} = $value;
        }

        return $this;
    }

    public function extract(array $keys = [], bool $includeEmpty = true): array
    {
        $reflection = new ReflectionObject($this);
        $data = [];
        foreach ($reflection->getProperties() as $prop) {
            if (!empty($keys) && !in_array($prop->getName(), $keys)) {
                continue;
            }
            if (!$includeEmpty && $this->{$prop->getName()} === null) {
                continue;
            }

            $name = $prop->getName();

            $data[$name] = $this->{$name};
        }

        return $data;
    }
}
