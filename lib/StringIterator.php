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

    /**
     * @param int $value
     *
     * @return bool
     */
    public function is(int $value): bool
    {
        return $value === $this->ord();
    }

    /**
     * @param int $value
     *
     * @return bool
     */
    public function isNot(int $value): bool
    {
        return $value !== $this->ord();
    }
}
