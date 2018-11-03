<?php

namespace Badcow\DNS\Parser;

class UnsupportedTypeException extends \Exception
{
    public function __construct(string $type)
    {
        $message = sprintf('The RDATA type "%s" is not supported.', $type);
        parent::__construct($message);
    }
}
