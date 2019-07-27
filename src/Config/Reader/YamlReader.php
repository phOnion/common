<?php
namespace Onion\Framework\Common\Config\Reader;

use Onion\Framework\Common\Config\Interfaces\ReaderInterface;
use Symfony\Component\Yaml\Yaml;

class YamlReader implements ReaderInterface
{
    const DEFAULT_OPTIONS = Yaml::PARSE_DATETIME |
        Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE |
        Yaml::PARSE_CUSTOM_TAGS;

    public function parse(string $filename): array
    {
        if (!file_exists($filename)) {
            throw new \InvalidArgumentException("Unable to parse {$filename}");
        }

        return Yaml::parseFile($filename, static::DEFAULT_OPTIONS);
    }
}
