<?php

namespace Badcow\DNS\Parser;

use Badcow\DNS\Classes;
use Badcow\DNS\ResourceRecord;
use Badcow\DNS\Zone;
use Badcow\DNS\Rdata;

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
     * @var Zone
     */
    private $zone;

    /**
     * @param string $name
     * @param string $zone
     *
     * @return Zone
     *
     * @throws ParseException
     */
    public static function parse(string $name, string $zone): Zone
    {
        $parser = new self();

        return $parser->makeZone($name, $zone);
    }

    /**
     * @param $name
     * @param $string
     *
     * @return Zone
     *
     * @throws ParseException
     */
    public function makeZone($name, $string): Zone
    {
        $this->zone = new Zone($name);
        $this->string = Normaliser::normalise($string);

        foreach (explode(Tokens::LINE_FEED, $this->string) as $line) {
            $this->processLine($line);
        }

        return $this->zone;
    }

    /**
     * @param string $line
     *
     * @throws ParseException
     */
    private function processLine(string $line)
    {
        $iterator = new \ArrayIterator(explode(Tokens::SPACE, $line));

        if ($this->isControlEntry($iterator)) {
            $this->processControlEntry($iterator);

            return;
        }

        $resourceRecord = new ResourceRecord();

        $this->processResourceName($iterator, $resourceRecord);
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
     * Processes a ResourceRecord name.
     *
     * @param \ArrayIterator $iterator
     * @param ResourceRecord $resourceRecord
     */
    private function processResourceName(\ArrayIterator $iterator, ResourceRecord $resourceRecord): void
    {
        if ($this->isResourceName($iterator)) {
            $this->previousName = $iterator->current();
            $iterator->next();
        }

        $resourceRecord->setName($this->previousName);
    }

    /**
     * Set RR's TTL if there is one.
     *
     * @param \ArrayIterator $iterator
     * @param ResourceRecord $resourceRecord
     */
    private function processTtl(\ArrayIterator $iterator, ResourceRecord $resourceRecord): void
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
    private function processClass(\ArrayIterator $iterator, ResourceRecord $resourceRecord): void
    {
        if (Classes::isValid(strtoupper($iterator->current()))) {
            $resourceRecord->setClass(strtoupper($iterator->current()));
            $iterator->next();
        }
    }

    /**
     * Determine if iterant is a resource name.
     *
     * @param \ArrayIterator $iterator
     *
     * @return bool
     */
    private function isResourceName(\ArrayIterator $iterator): bool
    {
        return !(
            preg_match('/^\d+$/', $iterator->current()) ||
            Classes::isValid(strtoupper($iterator->current())) ||
            RDataTypes::isValid(strtoupper($iterator->current()))
        );
    }

    /**
     * Determine if iterant is a control entry such as $TTL, $ORIGIN, $INCLUDE, etcetera.
     *
     * @param \ArrayIterator $iterator
     *
     * @return bool
     */
    private function isControlEntry(\ArrayIterator $iterator): bool
    {
        return 1 === preg_match('/^\$[A-Z0-9]+/i', $iterator->current());
    }

    /**
     * @param \ArrayIterator $iterator
     *
     * @return RData\RDataInterface
     *
     * @throws ParseException
     */
    private function extractRdata(\ArrayIterator $iterator): Rdata\RdataInterface
    {
        $type = strtoupper($iterator->current());
        $iterator->next();

        if (!Rdata\Factory::isTypeImplemented($type)) {
            return new PolymorphicRdata($type, implode(Tokens::SPACE, $this->getAllRemaining($iterator)));
        }

        switch ($type) {
            case Rdata\LOC::TYPE:
                return $this->handleLocRdata($iterator);
            case Rdata\TXT::TYPE:
                return $this->handleTxtRdata($iterator);
            case Rdata\APL::TYPE:
                return $this->handleAplRdata($iterator);
        }

        return call_user_func_array(['\\Badcow\\DNS\\Rdata\\Factory', $type], $this->getAllRemaining($iterator));
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
        $string = new StringIterator(implode(Tokens::SPACE, $this->getAllRemaining($iterator)));
        $txt = new StringIterator();
        $doubleQuotesOpen = false;

        while ($string->valid()) {
            switch ($string->current()) {
                case Tokens::BACKSLASH:
                    $string->next();
                    $txt->append($string->current());
                    $string->next();
                    break;
                case Tokens::DOUBLE_QUOTES:
                    $doubleQuotesOpen = !$doubleQuotesOpen;
                    $string->next();
                    break;
                default:
                    if ($doubleQuotesOpen) {
                        $txt->append($string->current());
                    }
                    $string->next();
                    break;
            }
        }

        if ($doubleQuotesOpen) {
            throw new ParseException('Unbalanced double quotation marks.');
        }

        return Rdata\Factory::txt((string) $txt);
    }

    /**
     * Return current entry and moves the iterator to the next entry.
     *
     * @param \ArrayIterator $iterator
     *
     * @return mixed
     */
    private function pop(\ArrayIterator $iterator)
    {
        $current = $iterator->current();
        $iterator->next();

        return $current;
    }

    /**
     * Get all the remaining values of an iterator as an array.
     *
     * @param \ArrayIterator $iterator
     *
     * @return array
     */
    private function getAllRemaining(\ArrayIterator $iterator): array
    {
        $values = [];
        while ($iterator->valid()) {
            $values[] = $iterator->current();
            $iterator->next();
        }

        return $values;
    }

    /**
     * Transform a DMS string to a decimal representation. Used for LOC records.
     *
     * @param int    $deg        Degrees
     * @param int    $min        Minutes
     * @param float  $sec        Seconds
     * @param string $hemisphere Either 'N', 'S', 'E', or 'W'
     *
     * @return float
     */
    private function dmsToDecimal(int $deg, int $min, float $sec, string $hemisphere): float
    {
        $multiplier = ('S' === $hemisphere || 'W' === $hemisphere) ? -1 : 1;

        return $multiplier * ($deg + ($min / 60) + ($sec / 3600));
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
