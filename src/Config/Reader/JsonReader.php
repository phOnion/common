<?php
namespace Onion\Framework\Common\Config\Reader;

use Onion\Framework\Common\Config\Interfaces\ReaderInterface;

class JsonReader implements ReaderInterface
{
    public function parse(string $filename): array
    {
        if (!file_exists($filename)) {
            throw new \InvalidArgumentException("Unable to parse {$file}");
        }

        return json_decode(file_get_contents($filename), true);
    }
}
