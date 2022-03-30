<?php

namespace Onion\Framework\Dependency\Traits;

use Onion\Framework\Dependency\Interfaces\AttachableContainer;
use Onion\Framework\Dependency\Interfaces\DelegateContainerInterface;
use Psr\Container\ContainerInterface;

trait DelegateContainerTrait
{
    private $containers = [];

    public function attach(AttachableContainer $attachable): void
    {
        if ($attachable instanceof DelegateContainerInterface) {
            throw new \InvalidArgumentException(
                "Attachable containers can't be delegates"
            );
        }

        $attachable->attach($this);
        $this->containers[] = $attachable;
    }

    public function getAttachedContainers(): array
    {
        return $this->containers;
    }

    public function has($id): bool
    {
        foreach ($this->getAttachedContainers() as $container) {
            /** @var ContainerInterface $container */
            if ($container->has($id)) {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        return count($this->getAttachedContainers());
    }
}
