<?php

namespace Badcow\DNS\Parser;

use Badcow\DNS\Classes;
use Badcow\DNS\ResourceRecord;
use Badcow\DNS\Zone;
use Badcow\DNS\Parser\RData as RDataEnum;
use Badcow\DNS\Rdata;
use Badcow\DNS\ZoneInterface;
use LTDBeget\ascii\AsciiChar;

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
        $lines = explode("\n", $this->string);

        foreach ($lines as $line) {
            $this->processLine($line);
        }

        return $this->zone;
    }

    /**
     * @param string $line
     *
     * @throws UnsupportedTypeException
     */
    private function processLine(string $line)
    {
        if (1 === preg_match('/^\$ORIGIN/i', strtoupper($line))) {
            return;
        }

        $matches = [];
        if (1 === preg_match('/^\$TTL (\d+)/i', strtoupper($line), $matches)) {
            $ttl = (int) $matches[1];
            $this->zone->setDefaultTtl($ttl);

            return;
        }

        $parts = explode(' ', $line);
        $iterator = new \ArrayIterator($parts);
        $record = new ResourceRecord();

        // Is it a TTL?
        if (1 === preg_match('/^\d+$/', $iterator->current())) {
            $record->setName($this->previousName);
            goto ttl;
        }

        // Is it a valid class?
        if (Classes::isValid(strtoupper($iterator->current()))) {
            $record->setName($this->previousName);
            goto _class;
        }

        // Is it a valid RDATA type?
        if (RDataEnum::isValid(strtoupper($iterator->current()))) {
            $record->setName($this->previousName);
            goto type;
        }

        $record->setName($iterator->current());
        $this->previousName = $iterator->current();
        $iterator->next();

        ttl:
        $matches = [];
        if (1 === preg_match('/^(\d+)$/', $iterator->current(), $matches)) {
            $ttl = (int) $matches[1];
            $record->setTtl($ttl);
            $iterator->next();
        }

        _class:
        if (Classes::isValid(strtoupper($iterator->current()))) {
            $record->setClass(strtoupper($iterator->current()));
            $iterator->next();
        }

        type:
        if (!RDataEnum::isValid(strtoupper($iterator->current()))) {
            throw new UnsupportedTypeException($iterator->current());
        }

        $rdata = $this->extractRdata($iterator);
        $record->setRdata($rdata);

        $this->zone->addResourceRecord($record);
    }

    /**
     * @param \ArrayIterator $a
     *
     * @return RData\RDataInterface
     *
     * @throws UnsupportedTypeException
     */
    private function extractRdata(\ArrayIterator $a): Rdata\RdataInterface
    {
        $type = strtoupper($a->current());
        $a->next();
        switch ($type) {
            case Rdata\A::TYPE:
                return Rdata\Factory::A($this->pop($a));
            case Rdata\AAAA::TYPE:
                return Rdata\Factory::Aaaa($this->pop($a));
            case Rdata\CNAME::TYPE:
                return Rdata\Factory::Cname($this->pop($a));
            case Rdata\DNAME::TYPE:
                return Rdata\Factory::Dname($this->pop($a));
            case Rdata\HINFO::TYPE:
                return Rdata\Factory::Hinfo($this->pop($a), $this->pop($a));
            case Rdata\LOC::TYPE:
                $lat = $this->dmsToDecimal($this->pop($a), $this->pop($a), $this->pop($a), $this->pop($a));
                $lon = $this->dmsToDecimal($this->pop($a), $this->pop($a), $this->pop($a), $this->pop($a));

                return Rdata\Factory::Loc(
                    $lat,
                    $lon,
                    (float) $this->pop($a),
                    (float) $this->pop($a),
                    (float) $this->pop($a),
                    (float) $this->pop($a)
                );
            case Rdata\MX::TYPE:
                return Rdata\Factory::Mx($this->pop($a), $this->pop($a));
            case Rdata\NS::TYPE:
                return Rdata\Factory::Ns($this->pop($a));
            case Rdata\PTR::TYPE:
                return Rdata\Factory::Ptr($this->pop($a));
            case Rdata\SOA::TYPE:
                return Rdata\Factory::Soa(
                    $this->pop($a),
                    $this->pop($a),
                    $this->pop($a),
                    $this->pop($a),
                    $this->pop($a),
                    $this->pop($a),
                    $this->pop($a)
                );
            case Rdata\SRV::TYPE:
                return Rdata\Factory::Srv($this->pop($a), $this->pop($a), $this->pop($a), $this->pop($a));
            case Rdata\TXT::TYPE:
                return $this->extractTxtRecord($a);
            default:
                throw new UnsupportedTypeException($type);
        }
    }

    /**
     * @param \ArrayIterator $a
     *
     * @return Rdata\TXT
     *
     * @throws ParseException
     */
    private function extractTxtRecord(\ArrayIterator $a): Rdata\TXT
    {
        $txt = '';
        while ($a->valid()) {
            $txt .= $this->pop($a).' ';
        }
        $txt = substr($txt, 0, -1);

        $string = new StringIterator($txt);
        $txt = '';
        $doubleQuotesOpen = false;

        while ($string->valid()) {
            switch ($string->ord()) {
                case AsciiChar::BACKSLASH:
                    $string->next();
                    $txt .= $string->current();
                    $string->next();
                    break;
                case AsciiChar::DOUBLE_QUOTES:
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
}
