Badcow DNS Zone Parser
======================

This library parses DNS zone files and outputs DNS objects (see [Badcow DNS Zone Library](https://github.com/Badcow/DNS))

## Usage

```php
$file = file_get_contents(__DIR__.'/example.com.txt');

$zone = Badcow\DNS\Parser\Parser::parse('example.com.', $file);
```

Simple as that.