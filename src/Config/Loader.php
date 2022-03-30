<?php

declare(strict_types=1);

namespace Onion\Framework\Config;

use Onion\Framework\Collection\Collection;
use Onion\Framework\Config\Interfaces\{LoaderInterface, ReaderInterface};

use function Onion\Framework\{merge, normalize_tree_keys};

class Loader implements LoaderInterface
{
    private const DEFAULT_KEY_SEPARATOR = '.';

    private array $readers = [];

    public function __construct(private readonly string $separator = self::DEFAULT_KEY_SEPARATOR)
    {
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

        $registeredExtensions = array_keys($this->readers);
        $collection = new Collection(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $directory,
                    \RecursiveDirectoryIterator::FOLLOW_SYMLINKS | \RecursiveDirectoryIterator::SKIP_DOTS,
                ),
            )
        );

        return merge(
            [],
            ...$collection
                ->filter(
                    fn (\SplFileInfo $item) =>
                    in_array($item->getExtension(), $registeredExtensions) &&
                        preg_match("/\.(global|local|{$environment})\./i", $item->getFilename()) === 1
                )
                ->map(fn (\SplFileInfo $item) => $this->loadFile($item->getRealPath()))

        );
    }

    public function loadDirectories(string $environment, array $directories): array
    {
        return merge(
            [],
            ...array_map(
                fn (string $directory): array => $this->loadDirectory($environment, $directory),
                $directories
            )
        );
    }

    public function loadFile(string $filename): array
    {
        if (!file_exists($filename)) {
            throw new \InvalidArgumentException(
                "Provided file '{$filename}' does not exist or is not readable"
            );
        }

        $file = new \SplFileInfo($filename);

        if (!isset($this->readers[$file->getExtension()])) {
            throw new \RuntimeException("No reader registered for '{$file->getExtension()}'");
        }

        return normalize_tree_keys(
            $this->readers[$file->getExtension()]->parse(
                $file->getRealPath()
            ) ?? [],
            $this->separator
        );
    }
}
