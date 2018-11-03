<?php

namespace Badcow\DNS\Parser;

class StringIterator extends \ArrayIterator
{
    /**
     * StringIterator constructor.
     *
     * @param string $string
     */
    public function __construct(string $string)
    {
        parent::__construct(str_split($string));
    }

    /**
     * @return int
     */
    public function ord(): int
    {
        return ord($this->current());
    }
}
