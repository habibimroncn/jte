<?php
namespace TextOperation\Test;

use PHPUnit_Framework_TestCase;
use TextOperation\TextOperation;

function random_char() {
    return chr(random_int(97, 123));
}

function random_string($max_len=16) {
    $s = '';
    $s_len = random_int(0, $max_len);
    while ($s_len > 0) {
        $s .= random_char();
        $s_len -= 1;
    }
    return $s;
}

function random_operation($doc) {
    $op = new TextOperation();
    $i = 0;
    $gen_retain = function ($max_len) use ($op) {
        $r = random_int(1, $max_len);
        $op->retain($r);
        return $r;
    };
    $gen_insert = function () use ($op) {
        $op->insert(random_char() . random_string(9));
        return 0;
    };
    $gen_delete = function ($max_len) use ($op) {
        $d = random_int(1, $max_len);
        $op->delete($d);
        return $d;
    };
    $len = mb_strlen($doc);
    $gens = [$gen_retain, $gen_delete, $gen_insert];
    while ($i < $len) {
        $max_len = min(10, $len - $i);
        $k = array_rand($gens);
        $i += call_user_func($gens[$k], $max_len);
    }
    return $op;
}

class TextOperationTest extends PHPUnit_Framework_TestCase
{
    public function testAppend()
    {
        $op = new TextOperation();
        $op->delete(0);
        $op->insert('lorem');
        $op->retain(0);
        $op->insert(' ipsum');
        $op->retain(3);
        $op->retain('');
        $op->retain(5);
        $op->delete(8);

        $this->assertCount(3, $op);

        $ops = [];
        foreach ($op as $i) {
            $ops[] = $i;
        }
        $this->assertEquals(['lorem ipsum', 8, -8], $ops);
    }

    public function testApply()
    {
        $op = new TextOperation();
        $op->delete(1)
           ->insert('l')
           ->retain(4)
           ->delete(4)
           ->retain(2)
           ->insert('s');
        $this->assertEquals('loremums', $op->apply('Lorem ipsum'));
    }

    public function testJsonSerialize()
    {
        $op = new TextOperation();
        $op->delete(0);
        $op->insert('lorem');
        $op->retain(0);
        $op->insert(' ipsum');
        $op->retain(3);
        $op->retain('');
        $op->retain(5);
        $op->delete(8);

        $this->assertEquals(json_encode(['lorem ipsum', 8, -8]), json_encode($op));
    }
}