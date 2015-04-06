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
 
class Line
{
    /**
     * @var string
     */
    private $data;

    /**
     * @var string
     */
    private $comment;

    /**
     * @param string $data
     * @param string $comment
     */
    public function __construct($data = null, $comment = null)
    {
        $this->data = $data;
        $this->comment = $comment;
    }

    /**
     * @param string $comment
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param string $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->data . ' ; ' . $this->comment;
    }
}
