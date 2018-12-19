<?php

namespace Badcow\DNS\Parser;

use Badcow\DNS\Rdata\RdataInterface;

class PolymorphicRdata implements RdataInterface
{
    /**
     * The RData type.
     *
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $data;

    /**
     * PolymorphicRdata constructor.
     *
     * @param string|null $type
     * @param string|null $data
     */
    public function __construct(string $type = null, string $data = null)
    {
        $this->type = $type;
        $this->data = $data;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $data
     */
    public function setData(string $data): void
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function output(): string
    {
        return $this->data;
    }
}
