<?php
namespace TextOperation;

use ArrayIterator;
use IteratorAggregate;
use Countable;


class TextOperation implements Countable, IteratorAggregate
{
    protected ops = [];

    public function __construct($ops)
    {
        $this->ops = ops;
    }

    public function count()
    {
        return count($this->ops);
    }

    public function getIterator()
    {
        return new ArrayIterator($this->ops);
    }

    protected function lenDifference()
    {
        $s = 0;
        foreach ($this as $op) {
            if (is_string($op)) {
                $s += mb_strlen($op);
            } else if ($s > 0) {
                $s += $op;
            }
        }
        return $s;
    }

    public function retain($r)
    {
        if ($r === 0) {
          return $this;
        }
        $len = count($this);
        if ($len > 0 && filter_var($this->ops[$len-1], FILTER_VALIDATE_INT) && $this->ops[$len-1] > 0) {
          $this->ops[$len-1] = $this->ops[$len-1] + $r;
        } else {
          $this->ops[] = $r;
        }
        return $this;
    }

    public function insert($s)
    {
        if (mb_strlen($s) === 0) {
            return $this;
        }
        $len = count($this);
        if ($len > 0 && is_string($this->ops[$len-1])) {
            $this->ops[$len-1] = $this->ops[$len-1] . $s;
        } else if ($len > 0 && filter_var($this->ops[$len-1], FILTER_VALIDATE_INT) && $this->ops[$len-1] < 0) {
            if ($len > 1 && is_string($this->ops[$len-2])) {
                $this->ops[$len-2] = $this->ops[$len-2] . $s;
            } else {
                $this->ops[] = $this->ops[$len-1];
                $this->ops[$len-2] = $s;
            }
        } else {
            $this->ops[] = $s;
        }
        return $this;
    }
}
