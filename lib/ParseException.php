<?php

namespace Badcow\DNS\Parser;

use LTDBeget\ascii\AsciiChar;
use LTDBeget\stringstream\StringStream;

class ParseException extends \Exception
{
    /**
     * @var StringStream
     */
    private $stringSteam;

    public function __construct(string $message = '', StringStream $stringSteam = null)
    {
        if (null !== $stringSteam) {
            $this->stringSteam = $stringSteam;
            $message .= sprintf(' [Line no: %d]', $this->getLineNumber());
        }

        parent::__construct($message);
    }

    private function getLineNumber()
    {
        $pos = $this->stringSteam->position();
        $this->stringSteam->start();
        $lineNo = 1;

        while ($this->stringSteam->position() < $pos) {
            if (AsciiChar::LINE_FEED === $this->stringSteam->ord()) {
                ++$lineNo;
                $this->stringSteam->next();
            }
        }

        return $lineNo;
    }
}
