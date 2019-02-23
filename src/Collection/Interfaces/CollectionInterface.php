<?php
namespace Onion\Framework\Common\Collection\Interfaces;

use Closure;
use Iterator;

interface CollectionInterface extends Iterator
{
    public function map(\Closure $callback): CollectionInterface;
    public function filter(\Closure $callback): CollectionInterface;
    public function sort(\Closure $callback): CollectionInterface;
    public function reduce(\Closure $callback);
    public function slice(int $offset, int $limit = -1): CollectionInterface;
    public function find($item);
    public function keys(): CollectionInterface;
}
