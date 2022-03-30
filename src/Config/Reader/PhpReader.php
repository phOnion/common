<?php

declare(strict_types=1);

namespace Onion\Framework\Config\Reader;

use Onion\Framework\Config\Interfaces\ReaderInterface;

class PhpReader implements ReaderInterface
{
    public function parse(string $filename): array
    {
        if (!file_exists($filename)) {
            throw new \InvalidArgumentException("Unable to parse {$filename}");
        }

        return include $filename;
    }
}
