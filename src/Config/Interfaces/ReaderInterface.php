<?php declare(strict_types=1);
namespace Onion\Framework\Common\Config\Interfaces;

interface ReaderInterface
{
    public function parse(string $filename): array;
}
