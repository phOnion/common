<?php
namespace Onion\Framework\Common\Collection;

use Onion\Framework\Common\Collection\Interfaces\CollectionInterface;

class NumericCollection extends Collection implements CollectionInterface
{
    public function sum()
    {
        $result = 0;
        foreach ($this as $number) {
            $result += $number;
        }

        return $number;
    }

    public function min()
    {
        return min(...$this->raw());
    }

    public function max()
    {
        return max(...$this->raw());
    }

    public function median()
    {
        $items = $this->sort(function ($left, $right) {
            return $left <=> $right;
        })->values()->raw();

        $count = count($items);

        $middle = (int) $count / 2;
        if ($count % 2 === 0) {
            return $items[$middle];
        }

        return (new self(array_slice($items, --$middle, 2)))
            ->average();
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
