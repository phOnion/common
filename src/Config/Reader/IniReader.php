<?php
namespace Onion\Framework\Common\Config\Reader;

use Onion\Framework\Common\Config\Interfaces\ReaderInterface;


class IniReader implements ReaderInterface
{
    public function parse(string $filename): array
    {
        if (!file_exists($filename)) {
            throw new \InvalidArgumentException("Unable to parse {$filename}");
        }

        return parse_ini_file($filename, true, INI_SCANNER_TYPED);
    }
}
