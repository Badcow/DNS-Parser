<?php
/*
 * This file is part of Badcow DNS Library.
 *
 * (c) Samuel Williams <sam@badcow.co>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
