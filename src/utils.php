<?php
namespace TextOperation;

function is_retain($s) {
  return is_int($s) && $s > 0;
}

function is_delete($s) {
  return is_int($s) && $s < 0;
}

function is_insert($s) {
  return is_string($s);
}

function oplength($s) {
  if (is_int($s)) {
    if ($s < 0) return -$s;
    return $s;
  }
  return mb_strlen($s);
}

function opshorten($op, $by) {
    if (is_string($op)) return mb_substr($op, $by);
    if ($op < 0) return $op + $by;
    return $op - $by;
}

function opshorten_pair($a, $b) {
    $len_a = oplength($a);
    $len_b = oplength($b);

    if ($len_a === $len_b) return [null, null];
    if ($len_a > $len_b) return [opshorten($a, $len_b), null];
    return [null, opshorten($b, $len_a)];
}

function opFromJSON($json) {
    $ops = json_decode($json);
    return new TextOperation($ops);
}

function iter($iterable) {
    if ($iterable instanceof \Iterator) {
        return $iterable;
    }
    if ($iterable instanceof \IteratorAggregate) {
        return $iterable->getIterator();
    }
    if (is_array($iterable)) {
        return new \ArrayIterator($iterable);
    }
    throw new \InvalidArgumentException('Argument must be iterable');
}

function forward($iterator, $default = null) {
    if (! $iterator instanceof \Iterator) {
        throw new \InvalidArgumentException(sprintf(
            'Argument 1 must be an iterator, %s give', gettype($iterator)
        ));
    }
    if ($iterator->valid()) {
        $value = $iterator->current();
        $iterator->next();
        return $value;
    }
    if ($default !== null) {
        return $default;
    }
    throw new \RuntimeException(sprintf(
        '%s iterator no longer valid to iterate',
        get_class($iterator)
    ));
}