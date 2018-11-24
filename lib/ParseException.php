<?php

namespace Badcow\DNS\Parser;

class ParseException extends \Exception
{
    /**
     * @var StringIterator
     */
    private $stringIterator;

    /**
     * ParseException constructor.
     *
     * @param string              $message
     * @param StringIterator|null $stringIterator
     */
    public function __construct(string $message = '', StringIterator $stringIterator = null)
    {
        if (null !== $stringIterator) {
            $this->stringIterator = $stringIterator;
            $message .= sprintf(' [Line no: %d]', $this->getLineNumber());
        }

        parent::__construct($message);
    }

    /**
     * Get line number of current entry on the StringIterator.
     *
     * @return int
     */
    private function getLineNumber(): int
    {
        $pos = $this->stringIterator->key();
        $this->stringIterator->rewind();
        $lineNo = 1;

        while ($this->stringIterator->key() < $pos) {
            if ($this->stringIterator->is(Tokens::LINE_FEED)) {
                ++$lineNo;
            }
            $this->stringIterator->next();
        }

        return $lineNo;
    }
}
