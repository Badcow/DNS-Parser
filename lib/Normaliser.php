<?php
/**
 * Created by PhpStorm.
 * User: Samuel Williams
 * Date: 30/10/2018
 * Time: 8:58 PM
 */

namespace Badcow\DNS\Parser;


use LTDBeget\ascii\AsciiChar;
use LTDBeget\stringstream\StringStream;

class Normaliser
{
    /**
     * @var StringStream
     */
    private $string;

    /**
     * @var string
     */
    private $normalisedString = '';

    /**
     * Normaliser constructor.
     * @param string $zone
     * @throws \Hoa\Ustring\Exception
     */
    public function __construct(string $zone)
    {
        //Remove Windows line feeds and tabs
        $zone = str_replace(["\r\n", "\t"], ["\n", " "], $zone);
        $this->string = new StringStream($zone);
    }

    /**
     * @param string $zone
     * @return string
     * @throws \Hoa\Ustring\Exception
     */
    public static function normalise(string $zone): string
    {
        $normaliser = new self($zone);
        return $normaliser->process();
    }

    private function removeWhitespace()
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
     * @return string
     * @throws \Exception
     */
    public function process(): string
    {
        while (!$this->string->isEnd()) {
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
     * @throws \Exception
     */
    private function handleComment()
    {
        if (AsciiChar::SEMICOLON !== $this->string->ord()) {
            throw new \Exception();
        }

        while (AsciiChar::LINE_FEED !== $this->string->ord()) {
            $this->string->next();
        }
    }

    /**
     * Handle text inside of double quotations. When this function is called, the String pointer MUST be at the
     * double quotation mark.
     *
     * @throws \Exception
     */
    private function handleTxt()
    {
        if (AsciiChar::DOUBLE_QUOTES !== $this->string->ord()) {
            throw new \Exception();
        }

        $this->append();

        while (AsciiChar::DOUBLE_QUOTES !== $this->string->ord()) {
            if (AsciiChar::LINE_FEED === $this->string->ord()) {
                throw new \Exception("Line feed contained within quotation context.");
            }

            //If escape character
            if (AsciiChar::BACKSLASH === $this->string->ord()) {
                $this->append();
            }

            $this->append();
        }
    }

    private function handleMultiline()
    {
        if (AsciiChar::OPEN_BRACKET !== $this->string->ord()) {
            throw new \Exception();
        }

        $openBracket = true;
        $this->string->next();
        while ($openBracket) {
            switch ($this->string->ord()) {
                case AsciiChar::DOUBLE_QUOTES:
                    $this->handleTxt();
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
                    throw new \Exception();
                default:
                    $this->append();
            }
        }
    }

    private function append()
    {
        $this->normalisedString .= $this->string->current();
        $this->string->next();
    }
}