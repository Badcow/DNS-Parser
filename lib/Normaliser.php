<?php

namespace Badcow\DNS\Parser;

use LTDBeget\ascii\AsciiChar;

class Normaliser
{
    /**
     * @var StringIterator
     */
    private $string;

    /**
     * @var string
     */
    private $normalisedString = '';

    /**
     * Normaliser constructor.
     *
     * @param string $zone
     */
    public function __construct(string $zone)
    {
        //Remove Windows line feeds and tabs
        $zone = str_replace(["\r\n", "\t"], ["\n", ' '], $zone);

        $this->string = new StringIterator($zone);
    }

    /**
     * @param string $zone
     *
     * @return string
     *
     * @throws ParseException
     */
    public static function normalise(string $zone): string
    {
        $normaliser = new self($zone);

        return $normaliser->process();
    }

    /**
     * @return string
     *
     * @throws ParseException
     */
    public function process(): string
    {
        while ($this->string->valid()) {
            switch ($this->string->ord()) {
                case AsciiChar::DOUBLE_QUOTES:
                    $this->handleTxt();
                    break;
                case AsciiChar::SEMICOLON:
                    $this->handleComment();
                    break;
                case AsciiChar::OPEN_BRACKET:
                    $this->handleMultiline();
                    break;
            }

            $this->append();
        }

        $this->removeWhitespace();

        return $this->normalisedString;
    }

    /**
     * Ignores the comment section.
     *
     * @throws ParseException
     */
    private function handleComment(): void
    {
        if (AsciiChar::SEMICOLON !== $this->string->ord()) {
            throw new ParseException(sprintf('Semicolon (;) expected as current entry, character "%s" instead.',
                $this->string->current()),
                $this->string
            );
        }

        while (AsciiChar::LINE_FEED !== $this->string->ord() && $this->string->valid()) {
            $this->string->next();
        }
    }

    /**
     * Handle text inside of double quotations. When this function is called, the String pointer MUST be at the
     * double quotation mark.
     *
     * @throws ParseException
     */
    private function handleTxt(): void
    {
        if (AsciiChar::DOUBLE_QUOTES !== $this->string->ord()) {
            throw new ParseException(sprintf('Double Quotes (") expected as current entry, character "%s" instead.',
                $this->string->current()),
                $this->string
            );
        }

        $this->append();

        while (AsciiChar::DOUBLE_QUOTES !== $this->string->ord()) {
            if (!$this->string->valid()) {
                throw new ParseException('Unbalanced double quotation marks. End of file reached.');
            }

            //If escape character
            if (AsciiChar::BACKSLASH === $this->string->ord()) {
                $this->append();
            }

            if (AsciiChar::LINE_FEED === $this->string->ord()) {
                throw new ParseException('Line Feed found within double quotation marks context', $this->string);
            }

            $this->append();
        }
    }

    /**
     * Move multi-line records onto single line.
     *
     * @throws ParseException
     */
    private function handleMultiline(): void
    {
        if (AsciiChar::OPEN_BRACKET !== $this->string->ord()) {
            throw new ParseException(sprintf('Open bracket "(" expected as current entry, character "%s" instead.',
                $this->string->current()),
                $this->string
            );
        }

        $openBracket = true;
        $this->string->next();
        while ($openBracket) {
            switch ($this->string->ord()) {
                case AsciiChar::DOUBLE_QUOTES:
                    $this->handleTxt();
                    $this->append();
                    break;
                case AsciiChar::SEMICOLON:
                    $this->handleComment();
                    break;
                case AsciiChar::LINE_FEED:
                    $this->string->next();
                    break;
                case AsciiChar::CLOSE_BRACKET:
                    $openBracket = false;
                    $this->string->next();
                    break;
                case AsciiChar::NULL:
                    throw new ParseException('End of file reached. Unclosed bracket.');
                default:
                    $this->append();
            }
        }
    }

    /**
     * Remove superfluous whitespace characters from string.
     */
    private function removeWhitespace(): void
    {
        $this->normalisedString = preg_replace('/ {2,}/', ' ', $this->normalisedString);
        $lines = explode("\n", $this->normalisedString);
        $this->normalisedString = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ('' !== $line) {
                $this->normalisedString .= $line."\n";
            }
        }
        $this->normalisedString = rtrim($this->normalisedString);
    }

    /**
     * Add current entry to nomalisedString and moves to next entry.
     */
    private function append()
    {
        $this->normalisedString .= $this->string->current();
        $this->string->next();
    }
}
