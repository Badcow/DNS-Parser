!! Code-base has been moved !!
==============================
The DNS Parser has been incorporated into [Badcow DNS Zone Library](https://github.com/Badcow/DNS) to enable a better release cycle.
The namespaces remain unchanged. You only need to include `badcow/dns` in your composer config.

Badcow DNS Zone Parser
======================

This library parses DNS zone files and outputs DNS objects (see [Badcow DNS Zone Library](https://github.com/Badcow/DNS))

## Build Status
[![Build Status](https://travis-ci.org/Badcow/DNS-Parser.svg?branch=master)](https://travis-ci.org/Badcow/DNS-Parser)
[![Code Coverage](https://scrutinizer-ci.com/g/Badcow/DNS-Parser/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Badcow/DNS-Parser/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Badcow/DNS-Parser/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Badcow/DNS-Parser/?branch=master)

## Usage

```php
$file = file_get_contents('/path/to/example.com.txt');

$zone = Badcow\DNS\Parser\Parser::parse('example.com.', $file);
```

Simple as that.

## Installation
Using composer...
```json
"require": {
        "badcow/dns-parser": "~1.0"
    }
```

## Example

### BIND Record
```text
$ORIGIN example.com.
$TTL 3600
@            IN SOA  (
                     example.com.       ; MNAME
                     post.example.com.  ; RNAME
                     2014110501         ; SERIAL
                     3600               ; REFRESH
                     14400              ; RETRY
                     604800             ; EXPIRE
                     3600               ; MINIMUM
                     )

 ; NS RECORDS
@               NS   ns1.nameserver.com.
@               NS   ns2.nameserver.com.

info            TXT "This is some additional \"information\""

 ; A RECORDS
sub.domain      A    192.168.1.42 ; This is a local ip.

 ; AAAA RECORDS
ipv6.domain    AAAA ::1 ; This is an IPv6 domain.

 ; MX RECORDS
@               MX   10 mail-gw1.example.net.
@               MX   20 mail-gw2.example.net.
@               MX   30 mail-gw3.example.net.

mail     IN     TXT  "THIS IS SOME TEXT; WITH A SEMICOLON"
```

### Processing the record
```php
<?php

require_once '/path/to/vendor/autoload.php';

$file = file_get_contents('/path/to/example.com.txt');
$zone = Badcow\DNS\Parser\Parser::parse('example.com.', $file);

$zone->getName(); //Returns example.com.
foreach ($zone->getResourceRecords() as $record) {
    $record->getName();
    $record->getClass();
    $record->getTtl();
    $record->getRdata()->output();
}
```

## Using Custom RData Handlers
Out-of-the-box, the library will handle most RData types that are regularly encountered. Occasionally, you may encounter
an unsupported type. You can add your own RData handler method for the record type. For example, you may want to support
the non-standard `SPF` record type, and return a `TXT` instance.
```php
$spf = function (\ArrayIterator $iterator): Badcow\DNS\Rdata\TXT {
    $string = '';
    while ($iterator->valid()) {
        $string .= $iterator->current() . ' ';
        $iterator->next();
    }
    $string = trim($string, ' "'); //Remove whitespace and quotes

    $spf = new Badcow\DNS\Rdata\TXT;
    $spf->setText($string);

    return $spf;
};

$customHandlers = ['SPF' => $spf];

$record = 'example.com. 7200 IN SPF "v=spf1 a mx ip4:69.64.153.131 include:_spf.google.com ~all"';
$parser = new \Badcow\DNS\Parser\Parser($customHandlers);
$zone = $parser->makeZone('example.com.', $record);
```

You can also overwrite the default handlers if you wish, as long as your handler method returns an instance of
`Badcow\DNS\Rdata\RdataInterface`.
