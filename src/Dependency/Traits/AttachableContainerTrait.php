<?php
namespace Onion\Framework\Common\Dependency\Traits;

use Onion\Framework\Dependency\Container;
use Onion\Framework\Dependency\Interfaces\AttachableContainer;
use Onion\Framework\Dependency\Interfaces\DelegateContainerInterface;
use Psr\Container\ContainerInterface;

trait AttachableContainerTrait
{
    private $delegate;

    public function attach(DelegateContainerInterface $delegate): void
    {
        if ($delegate instanceof AttachableContainer) {
            throw new \InvalidArgumentException(
                "Delegate containers can't be attachable"
            );
        }

        $this->delegate = $delegate;
    }

    protected function getDelegate(): ContainerInterface
    {
        return $this->delegate ?? new Container([]);
    }
}
