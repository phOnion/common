<?php
namespace Onion\Framework\Common\Collection;

use function Onion\Framework\Common\generator;
use Onion\Framework\Common\Collection\Interfaces\CollectionInterface;

class StringCollection extends Collection implements CollectionInterface
{
    public function lowercase(int $mode = self::USE_VALUES_ONLY)
    {
        $self = clone $this;
        return new self(generator(function () use ($self, $mode) {
            foreach ($self as $key => $value) {
                if (($mode & self::USE_KEYS_ONLY) === self::USE_KEYS_ONLY) {
                    $key = strtolower($key);
                }

                if (($mode & self::USE_VALUES_ONLY) === self::USE_VALUES_ONLY) {
                    $value = strtolower($value);
                }

                yield $key => $value;
            }
        }));
    }

    public function uppercase(int $mode = self::USE_VALUES_ONLY): self
    {
        $self = clone $this;
        return new self(generator(function () use ($self, $mode) {
            foreach ($self as $key => $value) {
                if (($mode & self::USE_KEYS_ONLY) === self::USE_KEYS_ONLY) {
                    $key = strtoupper($key);
                }

                if (($mode & self::USE_VALUES_ONLY) === self::USE_VALUES_ONLY) {
                    $value = strtoupper($value);
                }

                yield $key => $value;
            }
        }));
    }

    public function words(int $mode = self::USE_VALUES_ONLY, string $delimiter = " \t\r\n\f\v"): self
    {
        $self = clone $this;
        return new self(generator(function () use ($self, $mode, $delimiter) {
            foreach ($self as $key => $value) {
                if (($mode & self::USE_KEYS_ONLY) === self::USE_KEYS_ONLY && is_string($key)) {
                    $key = ucwords($key, $delimiter);
                }

                if (($mode & self::USE_VALUES_ONLY) === self::USE_VALUES_ONLY) {
                    $value = ucwords($value, $delimiter);
                }

                yield $key => $value;
            }
        }));
    }

    public function soundex(): self
    {
        $self = clone $this;
        return new self(generator(function () use ($self) {
            foreach ($self as $key => $value) {
                yield $key => soundex($value);
            }
        }));
    }

    public function metaphone(int $phonemes = 0): self
    {
        $self = clone $this;
        return new self(generator(function () use ($self, $phonemes) {
            foreach ($self as $key => $value) {
                yield $key => metaphone($value, $phonemes);
            }
        }));
    }

    public function encode(callable $encoder, array $args = []): self
    {
        $self = clone $this;
        return self(generator(function () use ($self, $encoder, $args) {
            foreach ($self as $key => $value) {
                yield $key => call_user_func($encoder, $value, ...$args);
            }
        }));
    }

    public function decode(callable $decoder, array $args = []): self
    {
        $self = clone $this;
        return self(generator(function () use ($self, $decoder, $args) {
            foreach ($self as $key => $value) {
                yield $key => call_user_func($decoder, $value, ...$args);
            }
        }));
    }

    public function convert(string $targetEncoding, string $sourceEncoding = null): self
    {
        $self = clone $this;
        return self(generator(function () use ($self, $targetEncoding, $sourceEncoding) {
            foreach ($self as $key => $value) {
                if ($sourceEncoding === null) {
                    if (!extension_loaded('mbstring')) {
                        throw new \RuntimeException("Please enable 'mbstring' extension");
                    }

                    $sourceEncoding = mb_detect_encoding($value, mb_detect_order(), true);
                }

                if (!$sourceEncoding) {
                    yield $key => false;
                }

                if ($sourceEncoding) {
                    yield $key => iconv($sourceEncoding, $targetEncoding, $value);
                }
            }
        }));
    }

    public function match(string $regex): CollectionInterface
    {
        return parent::map(function ($value) use ($regex) {
            preg_match($regex, $value, $matches);

            return $matches ?? [];
        });
    }
}
