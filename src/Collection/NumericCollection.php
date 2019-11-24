<?php
namespace Onion\Framework\Common\Collection;

use Onion\Framework\Common\Collection\Interfaces\CollectionInterface;

class NumericCollection extends Collection implements CollectionInterface
{
    public function sum(int $precision = 2, $mode = \PHP_ROUND_HALF_UP): float
    {
        return \round(\array_sum($this->raw()), $precision, $mode);
    }

    public function min(int $precision = 2, $mode = \PHP_ROUND_HALF_UP): float
    {
        return \round(\min(...$this->raw()), $precision, $mode);
    }

    public function max(int $precision = 2, $mode = \PHP_ROUND_HALF_UP): float
    {
        return \round(\max(...$this->raw()), $precision, $mode);
    }

    public function median(int $precision = 2, $mode = \PHP_ROUND_HALF_UP): float
    {
        $items = $this->sort(function ($left, $right) {
            return $left <=> $right;
        })->values();

        $count = \count($items);

        $middle = (int) $count / 2;
        if ($count % 2 === 0) {
            return \round($items[$middle], $precision, $mode);
        }

        return \round(
            $this->slice(--$middle, 2)->average(),
            $precision,
            $mode
        );
    }

    public function mode(int $precision = 2, $mode = \PHP_ROUND_HALF_UP): self
    {
        $results = [];
        foreach ($this as $number) {
            $results[$number] = isset($results[$number]) ?
                $results[$number] + 1 : 0;
        }

        return \round(\array_flip($results)[\max(...$results)]);
    }

    public function average(int $precision = 2, $mode = \PHP_ROUND_HALF_UP)
    {
        return \round($this->sum() / \count($this), $precision, $mode);
    }
}
