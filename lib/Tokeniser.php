<?php

namespace Badcow\DNS\Parser;


use Badcow\DNS\Classes;
use Badcow\DNS\ResourceRecord;
use Badcow\DNS\Zone;
use Badcow\DNS\Parser\Rdata as RdataEnum;

use Badcow\DNS\Rdata;
use Badcow\DNS\ZoneInterface;

class Tokeniser
{
    private $string = '';

    private $previousName;

    /**
     * @param ZoneInterface
     */
    private $zone;

    /**
     * @param $name
     * @param $string
     * @return ZoneInterface
     * @throws \Hoa\Ustring\Exception
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

    private function processLine(string $line)
    {
        if (1 === preg_match('/^\$ORIGIN/', strtoupper($line))) {
            return;
        }

        $matches = [];
        if (1 === preg_match('/^\$TTL (\d+)/', strtoupper($line), $matches)) {
            $ttl = (int) $matches[1][0];
            $this->zone->setDefaultTtl($ttl);
            return;
        }

        $parts = (new \ArrayObject(explode(' ', $line)))->getIterator();
        $record = new ResourceRecord();

        // Is it a TTL?
        if (1 === preg_match('/$\d+^/', $parts->current())) {
            $record->setName($this->previousName);
            goto ttl;
        }

        // Is it a valid class?
        if (Classes::isValid(strtoupper($parts->current()))) {
            $record->setName($this->previousName);
            goto _class;
        }

        // Is it a valid RDATA type?
        if (RdataEnum::isValid(strtoupper($parts->current()))) {
            $record->setName($this->previousName);
            goto type;
        }

        $record->setName($parts->current());
        $this->previousName = $parts->current();
        $parts->next();

        ttl:
        $matches = [];
        if (1 === preg_match('/$\d+^/', $parts->current(), $matches)) {
            $ttl = (int) $matches[1][0];
            $record->setTtl($ttl);
            $parts->next();
        }

        _class:
        if (Classes::isValid(strtoupper($parts->current()))) {
            $record->setClass(strtoupper($parts->current()));
            $parts->next();
        }

        type:
        if (!RdataEnum::isValid(strtoupper($parts->current()))) {
            $current = $parts->current();
            throw new \Exception();
        }

        $rdata = $this->extractRdata($parts);
        $record->setRdata($rdata);

        $this->zone->addResourceRecord($record);
    }

    private function extractRdata(\ArrayIterator $a): Rdata\RdataInterface
    {
        $type = strtoupper($a->current());
        $a->next();
        switch ($type) {
            case Rdata\A::TYPE:
                return Rdata\Factory::A($this->pop($a));
                break;
            case Rdata\AAAA::TYPE:
                return Rdata\Factory::Aaaa($this->pop($a));
                break;
            case Rdata\CNAME::TYPE:
                return Rdata\Factory::Cname($this->pop($a));
                break;
            case Rdata\HINFO::TYPE:
                return Rdata\Factory::Hinfo($this->pop($a), $this->pop($a));
                break;
            case Rdata\MX::TYPE:
                return Rdata\Factory::Mx($this->pop($a), $this->pop($a));
                break;
            case Rdata\SOA::TYPE:
                return Rdata\Factory::Soa($this->pop($a), $this->pop($a), $this->pop($a), $this->pop($a), $this->pop($a), $this->pop($a), $this->pop($a));
                break;
            case Rdata\NS::TYPE:
                return Rdata\Factory::Ns($this->pop($a));
                break;
            case Rdata\TXT::TYPE:
                return Rdata\Factory::txt($this->pop($a));
                break;
            case Rdata\DNAME::TYPE:
                return Rdata\Factory::Cname($this->pop($a));
                break;
            default:
                throw new \Exception();
        }
    }

    private function pop(\ArrayIterator $arrayIterator)
    {
        $current = $arrayIterator->current();
        $arrayIterator->next();
        return $current;
    }
}