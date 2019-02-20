<?php declare(strict_types=1);
namespace Onion\Framework\Common;

if (!function_exists(__NAMESPACE__ . '\merge')) {
    function merge(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (array_key_exists($key, $array1)) {
                if (is_int($key)) {
                    $array1[] = $value;
                    continue;
                }

                if (is_array($value) && is_array($array1[$key])) {
                    $array1[$key] = merge($array1[$key], $value);
                    continue;
                }

                $array1[$key] = $value;
                continue;
            }

            $array1[$key] = $value;
        }
        return $array1;
    }
}

if (!function_exists(__NAMESPACE__ . '\normalize_tree_keys')) {
    /**
     * Builds a tree of normalized single-value keys from
     */
    function normalize_tree_keys(array $input, string $separator = '.') {
        $result = [];
        $temp = [];
        foreach ($input as $k => $value) {
            $pointer = &$temp;
            $keys = explode('.', $k);

            while (count($keys) > 1) {
                $key = array_shift($keys);

                if (!isset($pointer[$key])) {
                    $pointer = [];
                }

                $pointer = &$pointer[$key];
            }

            $pointer[array_shift($keys)] = is_array($value) ?
                normalize_tree_keys($value, $separator) : $value;

            $result = merge($result, $temp);
        }

        return $result;
    }
}

if (!function_exists(__NAMESPACE__ . '\find_by_separator')) {
    function find_by_separator(array $storage, string $key, string $separator = '.')
    {
        $fragments = explode($separator, $key);
        $index = &$storage;
        while ($fragments !== []) {
            $cursor = array_shift($fragments);
            if (!isset($index[$cursor])) {
                throw new \LogicException(
                    "Unable to resolve '{$cursor}' of '{$key}'"
                );
            }

            $index = &$index[$cursor];
        }

        return $index;
        //     $cursor .= '.' . array_shift($fragments);
        //     $cursor = ltrim($cursor, '.');

        //     while (!isset($storage[$cursor])) {
        //         if ($fragments === []) {
        //             $message = "Unable to resolve path '{$cursor}'";
        //             if ($cursor !== $key) {
        //                 $message .= " of {$key}";
        //             }

        //             throw new \LogicException($message);
        //         }

        //         $cursor .= '.' . array_shift($fragments);
        //     }

        //     $result = $storage[$cursor];

        //     if ($fragments !== [] && is_array($result)) {
        //         $path = implode('.', $fragments);
        //         try {
        //             return find_by_dots($result, $path);
        //         } catch (\LogicException $ex) {
        //             if ($fragments !== []) {
        //                 continue;
        //             }

        //             throw new \LogicException(
        //                 "Unable to resolve after '{$cursor}' of '{$key}'",
        //                 $ex->getCode(),
        //                 $ex
        //             );
        //         }
        //     }
        // }

        // return $result;
    }
}

if (!function_exists(__NAMESPACE__ . '\msort')) {
    function msort($items, \Closure $callable) {
        $size = count($items);
        if (1 >= $size) {
            return $items;
        }

        $merge = function ($left, $right) use ($callable) {
            $result = [];
            $leftTotal = count($left);
            $rightTotal = count($right);
            $leftIndex = $rightIndex = 0;

            while ($leftIndex < $leftTotal && $rightIndex < $rightTotal) {
                $position = $callable($left[$leftIndex], $right[$rightIndex]);

                switch ($position) {
                    case 1:
                        $result[] = $right[$rightIndex];
                        $rightIndex++;
                        break;
                    case -1:
                    default:
                        $result[] = $left[$leftIndex];
                        $leftIndex++;
                        break;
                }
            }

            while ($leftIndex<$leftTotal) {
                $result[] = $left[$leftIndex];
                $leftIndex++;
            }

            while ($rightIndex<$rightTotal) {
                $result[] = $right[$rightIndex];
                $rightIndex++;
            }

            return $result;
        };

        $middle = (int) round($size / 2, 0, PHP_ROUND_HALF_UP);
        $left = msort(array_slice($items, 0, $middle), $callable);
        $right = msort(array_slice($items, $middle), $callable);

        return $merge($left, $right);
    }
}

if (!function_exists(__NAMESPACE__ . '\isort')) {
    function isort($items, \Closure $callback) {
        $size = count($items);

        for ($i=0; $i<$size; $i++) {
            $val = $items[$i];
            $j = $i-1;

            while ($j>= 0 && $callback($items[$j], $val) === 1) {
                $items[$j+1] = $items[$j];
                $j--;
            }

            $items[$j+1] = $val;
        }

        return $items;
    }
}

