<?php
/*
 * This file is part of Badcow DNS Library.
 *
 * (c) Samuel Williams <sam@badcow.co>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Badcow\DNS\Parser;

use Badcow\DNS\Zone;
use Badcow\DNS\ResourceRecord;
use Badcow\DNS\Parser\Definition\DefinitionInterface;
use Badcow\DNS\Parser\Definition\ARdataDefinition;
use Badcow\DNS\Parser\Definition\CnameRdataDefinition;
use Badcow\DNS\Parser\Definition\SoaRdataDefinition;
use Badcow\DNS\ZoneInterface;

class Parser implements ParserInterface
{
    /**
     * @var array
     */
    protected $definitions = array();

    /**
     * @var ZoneInterface
     */
    protected $zone;

    /**
     * @param DefinitionInterface[] $definitions
     * @param ZoneInterface $zone
     */
    public function __construct(array $definitions = array(), ZoneInterface $zone = null)
    {
        $definitions = array_merge($definitions, array(
            new ARdataDefinition(),
            new CnameRdataDefinition(),
            new SoaRdataDefinition(),
        ));

        foreach ($definitions as $definition) {
            /** @var DefinitionInterface $definition */
            $this->addDefinition($definition);
        }

        $this->zone = (null === $zone) ? new Zone : $zone;
    }

    /**
     * {@inheritdoc}
     */
    public function addDefinition(DefinitionInterface $definition)
    {
        $this->definitions[] = $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function parse($zoneName, $zone)
    {
        $this->zone->setZoneName($zoneName);

        foreach (Interpreter::expand($zone) as $line) {
            /** @var Line $line */
            $this->zone->addResourceRecord($this->lineToRr($line));
        }

        return $zone;
    }

    /**
     * Create a resource record from a Line
     *
     * @param Line $line
     * @return ResourceRecord
     */
    private function lineToRr(Line $line)
    {
        $rr = new ResourceRecord();

        if (null !== $class = Interpreter::getClassFromLine($line->getData())) {
            $rr->setClass($class);
        }

        $rr->setRdata($this->getRData($line->getData()));
        $rr->setComment($line->getComment());
        $rr->setName(Interpreter::getResourceNameFromLine($line->getData()));

        return $rr;
    }

    /**
     * Creates an RR corresponding to the $rdata definition
     *
     * @param $rdata
     * @return \Badcow\DNS\Rdata\RdataInterface|null
     */
    private function getRData($rdata)
    {
        foreach ($this->definitions as $definition) {
            /** @var DefinitionInterface $definition */
            if ($definition->isValid($definition)) {
                return $definition->parse($rdata);
            }
        }

        return null;
    }
}