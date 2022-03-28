<?php

namespace Onion\Framework\Collection;

use Closure;
use Onion\Framework\Collection\Interfaces\CollectionInterface;

use function Onion\Framework\generator;

class StringCollection extends Collection implements CollectionInterface
{
    /**
     * @return static
     */
    public function lowercase(int $mode = self::USE_VALUES_ONLY): static
    {
        $self = clone $this;
        return match ($mode) {
            self::USE_KEYS_ONLY => new static(generator(function () use ($self) {
                foreach ($self as $key => $value) {
                    yield \strtolower((string) $key) => $value;
                }
            })),
            self::USE_VALUES_ONLY => new static(generator(function () use ($self) {
                foreach ($self as $key => $value) {
                    yield $key => \strtolower((string) $value);
                }
            })),
            self::USE_BOTH => new static(generator(function () use ($self) {
                foreach ($self as $key => $value) {
                    yield \strtolower((string) $key) => \strtolower((string) $value);
                }
            })),
        };
    }

    public function uppercase(int $mode = self::USE_BOTH): static
    {
        $self = clone $this;
        return match ($mode) {
            self::USE_KEYS_ONLY => new static(generator(function () use ($self) {
                foreach ($self as $key => $value) {
                    yield \strtoupper((string) $key) => $value;
                }
            })),
            self::USE_VALUES_ONLY => new static(generator(function () use ($self) {
                foreach ($self as $key => $value) {
                    yield $key => \strtoupper((string) $value);
                }
            })),
            self::USE_BOTH => new static(generator(function () use ($self) {
                foreach ($self as $key => $value) {
                    yield \strtoupper((string) $key) => \strtoupper((string) $value);
                }
            })),
        };
    }

    public function words(int $mode = self::USE_VALUES_ONLY, string $delimiter = " \t\r\n\f\v"): static
    {
        $self = clone $this;
        return match ($mode) {
            self::USE_KEYS_ONLY => new static(generator(function () use ($self, $delimiter) {
                foreach ($self as $key => $value) {
                    yield \ucwords((string) $key, $delimiter) => $value;
                }
            })),
            self::USE_VALUES_ONLY => new static(generator(function () use ($self, $delimiter) {
                foreach ($self as $key => $value) {
                    yield $key => \ucwords((string) $value, $delimiter);
                }
            })),
            self::USE_BOTH => new static(generator(function () use ($self, $delimiter) {
                foreach ($self as $key => $value) {
                    yield \ucwords((string) $key, $delimiter) => \ucwords((string) $value);
                }
            })),
        };
    }

    public function soundex(): static
    {
        $self = clone $this;
        return new static(generator(function () use ($self) {
            foreach ($self as $key => $value) {
                yield $key => \soundex($value);
            }
        }));
    }

    public function metaphone(int $phonemes = 0): static
    {
        $self = clone $this;
        return new static(generator(function () use ($self, $phonemes) {
            foreach ($self as $key => $value) {
                yield $key => \metaphone($value, $phonemes);
            }
        }));
    }

    public function encode(callable $encoder, array $args = []): static
    {
        $self = clone $this;
        return new static(generator(function () use ($self, $encoder, $args) {
            foreach ($self as $key => $value) {
                yield $key => \Closure::fromCallable($encoder)($value, ...$args);
            }
        }));
    }

    public function decode(callable $decoder, array $args = []): static
    {
        $self = clone $this;
        return new static(generator(function () use ($self, $decoder, $args) {
            foreach ($self as $key => $value) {
                yield $key => Closure::fromCallable($decoder)($value, ...$args);
            }
        }));
    }

    public function convert(string $targetEncoding, string $sourceEncoding = null): static
    {
        $self = clone $this;
        return new static(generator(function () use ($self, $targetEncoding, $sourceEncoding) {
            foreach ($self as $key => $value) {
                if ($sourceEncoding === null) {
                    if (!\extension_loaded('mbstring')) {
                        throw new \RuntimeException("Please enable 'mbstring' extension");
                    }

                    $sourceEncoding = \mb_detect_encoding($value, \mb_detect_order(), true);
                }

                if (!$sourceEncoding) {
                    yield $key => false;
                }

                if ($sourceEncoding) {
                    yield $key => \iconv($sourceEncoding, $targetEncoding, $value);
                }
            }
        }));
    }

    public function match(string $regex): static
    {
        return $this->map(function ($value) use ($regex) {
            $matches = [];
            return \preg_match($regex, $value, $matches);

            return $matches;
        });
    }
}
