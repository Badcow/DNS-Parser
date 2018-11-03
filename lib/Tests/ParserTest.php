<?php

namespace Badcow\DNS\Parser\Tests;

use Badcow\DNS\Parser\Parser;
use Badcow\DNS\Zone;
use Badcow\DNS\Rdata\Factory;
use Badcow\DNS\ResourceRecord;
use Badcow\DNS\AlignedBuilder;
use Badcow\DNS\ZoneInterface;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    /**
     * @var ZoneInterface
     */
    private $zone;

    public function setUp()
    {
        $this->zone = new Zone('example.com.');
        $this->zone->setDefaultTtl(3600);

        $soa = new ResourceRecord();
        $soa->setName('@');
        $soa->setRdata(Factory::Soa(
            'example.com.',
            'post.example.com.',
            '2014110501',
            3600,
            14400,
            604800,
            3600
        ));

        $ns1 = new ResourceRecord();
        $ns1->setName('@');
        $ns1->setRdata(Factory::Ns('ns1.nameserver.com.'));

        $ns2 = new ResourceRecord();
        $ns2->setName('@');
        $ns2->setRdata(Factory::Ns('ns2.nameserver.com.'));

        $a = new ResourceRecord();
        $a->setName('sub.domain');
        $a->setRdata(Factory::A('192.168.1.42'));
        $a->setComment('This is a local ip.');

        $a6 = new ResourceRecord();
        $a6->setName('ipv6.domain');
        $a6->setRdata(Factory::Aaaa('::1'));
        $a6->setComment('This is an IPv6 domain.');

        $mx1 = new ResourceRecord();
        $mx1->setName('@');
        $mx1->setRdata(Factory::Mx(10, 'mail-gw1.example.net.'));

        $mx2 = new ResourceRecord();
        $mx2->setName('@');
        $mx2->setRdata(Factory::Mx(20, 'mail-gw2.example.net.'));

        $mx3 = new ResourceRecord();
        $mx3->setName('@');
        $mx3->setRdata(Factory::Mx(30, 'mail-gw3.example.net.'));

        $loc = new ResourceRecord();
        $loc->setName('canberra');
        $loc->setRdata(Factory::Loc(
            -35.3075,   //Lat
            149.1244,   //Lon
            500,        //Alt
            20.12,      //Size
            200.3,      //HP
            300.1       //VP
        ));
        $loc->setComment('This is Canberra');

        $this->zone->addResourceRecord($soa);
        $this->zone->addResourceRecord($ns1);
        $this->zone->addResourceRecord($ns2);
        $this->zone->addResourceRecord($a);
        $this->zone->addResourceRecord($a6);
        $this->zone->addResourceRecord($mx1);
        $this->zone->addResourceRecord($mx2);
        $this->zone->addResourceRecord($mx3);
        $this->zone->addResourceRecord($loc);
    }

    public function testParse()
    {
        $zoneBuilder = new AlignedBuilder();
        $zone = $zoneBuilder->build($this->zone);
        $this->setUp();
        $expectation = clone $this->zone;
        foreach ($expectation->getResourceRecords() as $rr) {
            $rr->setComment('');
        }

        $this->assertEquals($expectation, Parser::parse('example.com.', $zone));
    }
}
