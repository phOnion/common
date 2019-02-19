<?php
namespace Onion\Framework\Common\Config\Reader;

use Onion\Framework\Common\Config\Interfaces\ReaderInterface;


class PhpReader implements ReaderInterface
{
    public function parse(string $filename): array
    {
        return include $filename;
    }
}
