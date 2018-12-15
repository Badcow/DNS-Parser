<?php

namespace Badcow\DNS\Parser;

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
        $zone = str_replace([Tokens::CARRIAGE_RETURN, Tokens::TAB], ['', Tokens::SPACE], $zone);

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
            switch ($this->string->current()) {
                case Tokens::DOUBLE_QUOTES:
                    $this->handleTxt();
                    break;
                case Tokens::SEMICOLON:
                    $this->handleComment();
                    break;
                case Tokens::OPEN_BRACKET:
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
     */
    private function handleComment(): void
    {
        while ($this->string->isNot(Tokens::LINE_FEED) && $this->string->valid()) {
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
        $this->append();

        while ($this->string->isNot(Tokens::DOUBLE_QUOTES)) {
            if (!$this->string->valid()) {
                throw new ParseException('Unbalanced double quotation marks. End of file reached.');
            }

            //If escape character
            if ($this->string->is(Tokens::BACKSLASH)) {
                $this->append();
            }

            if ($this->string->is(Tokens::LINE_FEED)) {
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
        $openBracket = true;
        $this->string->next();
        while ($openBracket && $this->string->valid()) {
            switch ($this->string->current()) {
                case Tokens::DOUBLE_QUOTES:
                    $this->handleTxt();
                    $this->append();
                    break;
                case Tokens::SEMICOLON:
                    $this->handleComment();
                    break;
                case Tokens::LINE_FEED:
                    $this->string->next();
                    break;
                case Tokens::CLOSE_BRACKET:
                    $openBracket = false;
                    $this->string->next();
                    break;
                default:
                    $this->append();
            }
        }

        if ($openBracket) {
            throw new ParseException('End of file reached. Unclosed bracket.');
        }
    }

    /**
     * Remove superfluous whitespace characters from string.
     */
    private function removeWhitespace(): void
    {
        $string = preg_replace('/ {2,}/', Tokens::SPACE, $this->normalisedString);
        $lines = [];

        foreach (explode(Tokens::LINE_FEED, $string) as $line) {
            if ('' !== $line = trim($line)) {
                $lines[] = $line;
            }
        }
        $this->normalisedString = implode(Tokens::LINE_FEED, $lines);
    }

    /**
     * Add current entry to normalisedString and moves iterator to next entry.
     */
    private function append()
    {
        $this->normalisedString .= $this->string->current();
        $this->string->next();
    }
}
