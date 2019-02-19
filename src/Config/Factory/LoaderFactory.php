<?php declare(strict_types=1);
namespace Onion\Common\Config\Factory;

use Onion\Framework\Dependency\Interfaces\FactoryInterface;
use Psr\Container\ContainerInterface;
use Onion\Common\Config\Loader;
use Onion\Common\Config\Reader\ReaderInterface;

class LoaderFactory implements FactoryInterface
{
    public function build(ContainerInterface $container)
    {
        $loader = new Loader(
            $container->has('config.separator') ? $container->get('config.separator') : '.'
        );

        if ($container->has('config.readers')) {
            foreach ($container->get('config.readers') as $name => $extensions) {
                $reader = $container->get($name);
                assert($reader instanceof ReaderInterface,
                    new \RuntimeException(sprintf(
                        'Invalid reader for extension(s): %s',
                        implode(', ', $extensions)
                    ))
                );

                $loader->registerReader($extensions, $reader);
            }
        }

        return $loader;
    }
}
