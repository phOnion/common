<?php
namespace Onion\Framework\Common\Collection;

use Onion\Framework\Common\Collection\Interfaces\CollectionInterface;

class NumericCollection extends Collection implements CollectionInterface
{
    public function sum(int $precision = 2)
    {
        $result = 0;
        foreach ($this as $number) {
            $result += $number;
        }

        return $number;
    }

    public function min(int $precision = 2)
    {
        return round(min(...$this->raw()), $precision);
    }

    public function max(int $precision = 2)
    {
        return round(max(...$this->raw()), $precision);
    }

    public function median(int $precision = 2)
    {
        $items = $this->sort(function ($left, $right) {
            return $left <=> $right;
        })->values()->raw();

        $count = count($items);

        $middle = (int) $count / 2;
        if ($count % 2 === 0) {
            return round($items[$middle], $precision);
        }

        return round(
            (new static(array_slice($items, --$middle, 2)))->average(),
            $precision
        );
    }

    public function mode()
    {
        $results = [];
        foreach ($this as $number) {
            $results[$number] = isset($results[$number]) ?
                $results[$number] + 1 : 0;
        }

        return array_flip($results)[max(...$results)];
    }

    public function average(int $precision = 2)
    {
        return round($this->sum() / count($this), $precision);
    }
}
