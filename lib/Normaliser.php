<?php

namespace Badcow\DNS\Parser;

use LTDBeget\ascii\AsciiChar as Char;

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
                case Char::DOUBLE_QUOTES:
                    $this->handleTxt();
                    break;
                case Char::SEMICOLON:
                    $this->handleComment();
                    break;
                case Char::OPEN_BRACKET:
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
        if ($this->string->isNot(Char::SEMICOLON)) {
            throw new ParseException(sprintf('Semicolon (;) expected as current entry, character "%s" instead.',
                $this->string->current()),
                $this->string
            );
        }

        while ($this->string->isNot(Char::LINE_FEED) && $this->string->valid()) {
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
        if ($this->string->isNot(Char::DOUBLE_QUOTES)) {
            throw new ParseException(sprintf('Double Quotes (") expected as current entry, character "%s" instead.',
                $this->string->current()),
                $this->string
            );
        }

        $this->append();

        while ($this->string->isNot(Char::DOUBLE_QUOTES)) {
            if (!$this->string->valid()) {
                throw new ParseException('Unbalanced double quotation marks. End of file reached.');
            }

            //If escape character
            if ($this->string->is(Char::BACKSLASH)) {
                $this->append();
            }

            if ($this->string->is(Char::LINE_FEED)) {
                throw new ParseException('Line Feed found within double quotation marks context.', $this->string);
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
        if ($this->string->isNot(Char::OPEN_BRACKET)) {
            throw new ParseException(sprintf('Open bracket "(" expected as current entry, character "%s" instead.',
                $this->string->current()),
                $this->string
            );
        }

        $openBracket = true;
        $this->string->next();
        while ($openBracket) {
            switch ($this->string->ord()) {
                case Char::DOUBLE_QUOTES:
                    $this->handleTxt();
                    $this->append();
                    break;
                case Char::SEMICOLON:
                    $this->handleComment();
                    break;
                case Char::LINE_FEED:
                    $this->string->next();
                    break;
                case Char::CLOSE_BRACKET:
                    $openBracket = false;
                    $this->string->next();
                    break;
                case Char::NULL:
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
