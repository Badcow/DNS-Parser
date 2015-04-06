<?php
/*
 * This file is part of Badcow DNS Library.
 *
 * (c) Samuel Williams <sam@badcow.co>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Badcow\DNS\Tests\Parser\Definition;

use Badcow\DNS\Parser\Definition\CnameRdataDefinition;
 
class CnameRdataDefinitionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CnameRdataDefinition
     */
    private $cnameDefinition;

    private $valid1 = 'hello IN CNAME world';

    private $valid2 = 'hello IN CNAME world.example.com.';

    private $valid3 = 'hello.world IN CNAME www';

    private $valid4 = 'hello-world IN CNAME www';

    private $valid5 = 'helloworld IN CNAME www-2';

    private $valid6 = '@ IN CNAME example.com.';

    private $invalid1 = '-hello IN CNAME www';

    public function __construct()
    {
        $this->cnameDefinition = new CnameRdataDefinition;
    }

    public function testIsValid()
    {
        $this->assertTrue($this->cnameDefinition->isValid($this->valid1));
        $this->assertTrue($this->cnameDefinition->isValid($this->valid2));
        $this->assertTrue($this->cnameDefinition->isValid($this->valid3));
        $this->assertTrue($this->cnameDefinition->isValid($this->valid4));
        $this->assertTrue($this->cnameDefinition->isValid($this->valid5));
        $this->assertTrue($this->cnameDefinition->isValid($this->valid6));

        //$this->assertFalse($this->cnameDefinition->isValid($this->invalid1));
    }
/*
    public function testParse()
    {
        $cnameRdata = $this->cnameDefinition->parse($this->valid1);

        $this->assertEquals('world', $cnameRdata->getCname());
    }
*/
}
