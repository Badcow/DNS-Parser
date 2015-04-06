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

class Lines implements \Iterator
{
    /**
     * @var int
     */
    private $position = 0;

    /**
     * @var Line[]
     */
    private $lines;

    /**
     * @param Line[] $lines
     */
    public function __construct(array $lines = [])
    {
        foreach ($lines as $line) {
            $this->addLine($line);
        }
    }

    /**
     * @param Line $line
     */
    public function addLine(Line $line)
    {
        $this->lines[] = $line;
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return $this->lines[$this->position];
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return array_key_exists($this->position, $this->lines);
    }
}
