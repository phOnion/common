<?php declare(strict_types=1);
namespace Onion\Framework\Common\Config;

use function Onion\Framework\Common\merge;
use function Onion\Framework\Common\normalize_tree_keys;
use Onion\Framework\Common\Config\Interfaces\LoaderInterface;
use Onion\Framework\Common\Config\Interfaces\ReaderInterface;

class Loader implements LoaderInterface
{
    private const DEFAULT_KEY_SEPARATOR = '.';

    private $readers = [];
    private $separator;

    public function __construct(string $defaultSeparator = self::DEFAULT_KEY_SEPARATOR)
    {
        $this->separator = $defaultSeparator;
    }

    public function registerReader(array $extensions, ReaderInterface $reader): void
    {
        foreach ($extensions as $extension) {
            $this->readers[$extension] = $reader;
        }
    }

    public function loadDirectory(string $environment, string $directory): array
    {
        if (!is_dir($directory)) {
            throw new \InvalidArgumentException(
                "Provided directory '{$directory}' does not exist or it is not readable"
            );
        }

        $iteratorOptions = \RecursiveDirectoryIterator::FOLLOW_SYMLINKS | \RecursiveDirectoryIterator::SKIP_DOTS;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, $iteratorOptions)
        );

        $registeredExtensions = array_keys($this->readers);
        $iterator = new \CallbackFilterIterator(
            $iterator,
            function (\SplFileInfo $item) use ($registeredExtensions, $environment) {
                return in_array($item->getExtension(), $registeredExtensions) &&
                    (
                        stripos($item->getFilename(), ".{$environment}.") !== false ||
                        stripos($item->getFilename(), '.global.') !== false ||
                        stripos($item->getFilename(), '.local.') !== false
                    );
            });

        $configuration = [];
        foreach ($iterator as $item) {
            if (!isset($this->readers[$item->getExtension()])) {
                throw new \RuntimeException("No reader registered for extension '{$item->getExtension()}'");
            }

            $configuration = merge($configuration, normalize_tree_keys($this->readers[$item->getExtension()]->parse(
                $item->getRealPath()
            )), $this->separator);
        }

        return $configuration;
    }

    public function loadDirectories(string $environment, array $directories): array
    {
        $result = [];

        foreach ($directories as $directory) {
            $result = merge($result, $this->loadDirectory($environment, $directory));
        }

        return $result;
    }

    public function loadFile(string $filename): array
    {
        if (!file_exists($filename)) {
            throw new \InvalidArgumentException(
                "Provided file '{$file}' does not exist or is not readable"
            );
        }

        $file = new \SplFileInfo($filename);

        if (!isset($this->readers[$file->getExtension()])) {
            throw new \RuntimeException("No reader registered for '{$file->getExtension()}'");
        }

        return normalize_tree_keys(
            $this->readers[$file->getExtension()]->parseFile(
                $file->getRealPath()
            ),
            $this->separator
        );
    }
}
