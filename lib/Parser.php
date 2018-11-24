<?php

namespace Badcow\DNS\Parser;

use Badcow\DNS\Classes;
use Badcow\DNS\ResourceRecord;
use Badcow\DNS\Zone;
use Badcow\DNS\Rdata;
use Badcow\DNS\ZoneInterface;
use Badcow\DNS\Rdata\UnsupportedTypeException;

class Parser
{
    /**
     * @var string
     */
    private $string;

    /**
     * @var string
     */
    private $previousName;

    /**
     * @var ZoneInterface
     */
    private $zone;

    /**
     * @param string $name
     * @param string $zone
     *
     * @return ZoneInterface
     *
     * @throws ParseException
     * @throws UnsupportedTypeException
     */
    public static function parse(string $name, string $zone): ZoneInterface
    {
        $parser = new self();

        return $parser->makeZone($name, $zone);
    }

    /**
     * @param $name
     * @param $string
     *
     * @return ZoneInterface
     *
     * @throws ParseException
     * @throws UnsupportedTypeException
     */
    public function makeZone($name, $string): ZoneInterface
    {
        $this->zone = new Zone($name);
        $this->string = Normaliser::normalise($string);

        foreach (explode("\n", $this->string) as $line) {
            $this->processLine($line);
        }

        return $this->zone;
    }

    /**
     * @param string $line
     *
     * @throws UnsupportedTypeException
     * @throws ParseException
     */
    private function processLine(string $line)
    {
        $iterator = new \ArrayIterator(explode(' ', $line));

        if (1 === preg_match('/^\$[A-Z0-9]+/i', $iterator->current())) {
            $this->processControlEntry($iterator);

            return;
        }

        $resourceRecord = new ResourceRecord();

        if (
            1 === preg_match('/^\d+$/', $iterator->current()) ||
            Classes::isValid(strtoupper($iterator->current())) ||
            RDataTypes::isValid(strtoupper($iterator->current()))
        ) {
            $resourceRecord->setName($this->previousName);
        } else {
            $resourceRecord->setName($iterator->current());
            $this->previousName = $iterator->current();
            $iterator->next();
        }

        $this->processTtl($iterator, $resourceRecord);
        $this->processClass($iterator, $resourceRecord);
        $resourceRecord->setRdata($this->extractRdata($iterator));

        $this->zone->addResourceRecord($resourceRecord);
    }

    /**
     * Processes control entries at the top of a BIND record, i.e. $ORIGIN, $TTL, $INCLUDE, etc.
     *
     * @param \ArrayIterator $iterator
     */
    private function processControlEntry(\ArrayIterator $iterator): void
    {
        if ('$TTL' === strtoupper($iterator->current())) {
            $iterator->next();
            $this->zone->setDefaultTtl((int) $iterator->current());
        }
    }

    /**
     * Set RR's TTL if there is one.
     *
     * @param \ArrayIterator $iterator
     * @param ResourceRecord $resourceRecord
     */
    private function processTtl(\ArrayIterator $iterator, ResourceRecord $resourceRecord)
    {
        if (1 === preg_match('/^\d+$/', $iterator->current())) {
            $resourceRecord->setTtl($iterator->current());
            $iterator->next();
        }
    }

    /**
     * Set RR's class if there is one.
     *
     * @param \ArrayIterator $iterator
     * @param ResourceRecord $resourceRecord
     */
    private function processClass(\ArrayIterator $iterator, ResourceRecord $resourceRecord)
    {
        if (Classes::isValid(strtoupper($iterator->current()))) {
            $resourceRecord->setClass(strtoupper($iterator->current()));
            $iterator->next();
        }
    }

    /**
     * @param \ArrayIterator $iterator
     *
     * @return RData\RDataInterface
     *
     * @throws UnsupportedTypeException
     * @throws ParseException
     */
    private function extractRdata(\ArrayIterator $iterator): Rdata\RdataInterface
    {
        $type = strtoupper($iterator->current());
        $iterator->next();

        if (!Rdata\Factory::isTypeImplemented($type)) {
            throw new UnsupportedTypeException($type);
        }

        if (Rdata\LOC::TYPE === $type) {
            return $this->handleLocRdata($iterator);
        }

        if (Rdata\TXT::TYPE === $type) {
            return $this->handleTxtRdata($iterator);
        }

        if (Rdata\APL::TYPE === $type) {
            return $this->handleAplRdata($iterator);
        }

        $parameters = [];

        while ($iterator->valid()) {
            $parameters[] = $this->pop($iterator);
        }

        return call_user_func_array(['\\Badcow\\DNS\\Rdata\\Factory', $type], $parameters);
    }

    /**
     * @param \ArrayIterator $iterator
     *
     * @return Rdata\TXT
     *
     * @throws ParseException
     */
    private function handleTxtRdata(\ArrayIterator $iterator): Rdata\TXT
    {
        $txt = '';
        while ($iterator->valid()) {
            $txt .= $this->pop($iterator).' ';
        }
        $txt = substr($txt, 0, -1);

        $string = new StringIterator($txt);
        $txt = '';
        $doubleQuotesOpen = false;

        while ($string->valid()) {
            switch ($string->current()) {
                case Tokens::BACKSLASH:
                    $string->next();
                    $txt .= $string->current();
                    $string->next();
                    break;
                case Tokens::DOUBLE_QUOTES:
                    $doubleQuotesOpen = !$doubleQuotesOpen;
                    $string->next();
                    break;
                default:
                    if ($doubleQuotesOpen) {
                        $txt .= $string->current();
                    }
                    $string->next();
                    break;
            }
        }

        if ($doubleQuotesOpen) {
            throw new ParseException('Unbalanced double quotation marks.');
        }

        return Rdata\Factory::txt($txt);
    }

    /**
     * Return current entry and moves the iterator to the next entry.
     *
     * @param \ArrayIterator $arrayIterator
     *
     * @return mixed
     */
    private function pop(\ArrayIterator $arrayIterator)
    {
        $current = $arrayIterator->current();
        $arrayIterator->next();

        return $current;
    }

    /**
     * Transform a DMS string to a decimal representation. Used for LOC records.
     *
     * @param int    $deg
     * @param int    $m
     * @param float  $s
     * @param string $hemi
     *
     * @return float
     */
    private function dmsToDecimal(int $deg, int $m, float $s, string $hemi): float
    {
        $multiplier = ('S' === $hemi || 'W' === $hemi) ? -1 : 1;

        return $multiplier * ($deg + ($m / 60) + ($s / 3600));
    }

    /**
     * @param \ArrayIterator $iterator
     *
     * @return Rdata\LOC
     */
    private function handleLocRdata(\ArrayIterator $iterator): Rdata\LOC
    {
        $lat = $this->dmsToDecimal($this->pop($iterator), $this->pop($iterator), $this->pop($iterator), $this->pop($iterator));
        $lon = $this->dmsToDecimal($this->pop($iterator), $this->pop($iterator), $this->pop($iterator), $this->pop($iterator));

        return Rdata\Factory::Loc(
            $lat,
            $lon,
            (float) $this->pop($iterator),
            (float) $this->pop($iterator),
            (float) $this->pop($iterator),
            (float) $this->pop($iterator)
        );
    }

    /**
     * @param \ArrayIterator $iterator
     *
     * @return Rdata\APL
     *
     * @throws ParseException
     */
    private function handleAplRdata(\ArrayIterator $iterator): Rdata\APL
    {
        $rdata = new Rdata\APL();

        while ($iterator->valid()) {
            $matches = [];
            if (1 !== preg_match('/^(?<negate>!)?[1-2]:(?<block>.+)$/i', $iterator->current(), $matches)) {
                throw new ParseException(sprintf('"%s" is not a valid IP range.', $iterator->current()));
            }

            $ipBlock = \IPBlock::create($matches['block']);
            $rdata->addAddressRange($ipBlock, '!' !== $matches['negate']);
            $iterator->next();
        }

        return $rdata;
    }
}
