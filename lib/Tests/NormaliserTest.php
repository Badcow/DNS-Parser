<?php

namespace Badcow\DNS\Parser\Tests;

use Badcow\DNS\Parser\Normaliser;
use PHPUnit\Framework\TestCase;

class NormaliserTest extends TestCase
{
    public function testClearComments()
    {
        $zone = file_get_contents(__DIR__.'/Resources/testClearComments_sample.txt');
        $expectation = str_replace("\r\n", "\n", file_get_contents(__DIR__.'/Resources/testClearComments_expectation.txt'));
        $nomaliser = new Normaliser();
        $this->assertEquals($expectation, $nomaliser->normalise($zone));
    }

    public function testCollapseMultilines()
    {
        $zone = file_get_contents(__DIR__.'/Resources/testCollapseMultilines_sample.txt');
        $expectation = str_replace("\r\n", "\n", file_get_contents(__DIR__.'/Resources/testCollapseMultilines_expectation.txt'));
        $nomaliser = new Normaliser();
        $this->assertEquals($expectation, $nomaliser->normalise($zone));
    }
}