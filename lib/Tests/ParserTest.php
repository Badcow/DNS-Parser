<?php

namespace Badcow\DNS\Parser\Tests;

use Badcow\DNS\Classes;
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
     * @return ZoneInterface
     */
    private function getTestZone(): ZoneInterface
    {
        $zone = new Zone('example.com.');
        $zone->setDefaultTtl(3600);

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

        $zone->addResourceRecord($soa);
        $zone->addResourceRecord($ns1);
        $zone->addResourceRecord($ns2);
        $zone->addResourceRecord($a);
        $zone->addResourceRecord($a6);
        $zone->addResourceRecord($mx1);
        $zone->addResourceRecord($mx2);
        $zone->addResourceRecord($mx3);
        $zone->addResourceRecord($loc);

        return $zone;
    }

    /**
     * @throws \Badcow\DNS\Parser\ParseException
     * @throws \Badcow\DNS\Parser\UnsupportedTypeException
     */
    public function testParse()
    {
        $zoneBuilder = new AlignedBuilder();
        $zone = $zoneBuilder->build($this->getTestZone());
        $this->setUp();
        $expectation = $this->getTestZone();
        foreach ($expectation->getResourceRecords() as $rr) {
            $rr->setComment('');
        }

        $this->assertEquals($expectation, Parser::parse('example.com.', $zone));
    }

    /**
     * @throws \Badcow\DNS\Parser\ParseException
     * @throws \Badcow\DNS\Parser\UnsupportedTypeException
     */
    public function testConvoluted()
    {
        $file = file_get_contents(__DIR__.'/Resources/testConvolutedZone_sample.txt');
        $zone = Parser::parse('example.com.', $file);
        $this->assertEquals(3600, $zone->getDefaultTtl());
        $this->assertCount(25, $zone->getResourceRecords());

        $txt = new ResourceRecord(
            'testtxt',
            Factory::txt('v=DKIM1; k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBg'.
                'QDZKI3U+9acu3NfEy0NJHIPydxnPLPpnAJ7k2JdrsLqAK1uouMudHI20pgE8RMldB/TeW'.
                'KXYoRidcGCZWXleUzldDTwZAMDQNpdH1uuxym0VhoZpPbI1RXwpgHRTbCk49VqlC'),
            600,
            Classes::INTERNET
        );

        $txt2 = 'Some text another Some text';

        $this->assertEquals($txt, $this->findRecord($txt->getName(), $zone)[0]);
        $this->assertEquals($txt2, $this->findRecord('test', $zone)[0]->getRdata()->getText());
        $this->assertCount(1, $this->findRecord('xn----7sbfndkfpirgcajeli2a4pnc.xn----7sbbfcqfo2cfcagacemif0ap5q', $zone));
    }

    /**
     * @param string $name
     * @param ZoneInterface $zone
     * @return array
     */
    private function findRecord(string $name, ZoneInterface $zone): array
    {
        $records = [];

        foreach ($zone->getResourceRecords() as $resourceRecord) {
            if ($name === $resourceRecord->getName()) {
                $records[] = $resourceRecord;
            }
        }

        return $records;
    }
}
