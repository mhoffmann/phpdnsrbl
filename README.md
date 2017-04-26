# dnsrbl



Simple DNSRBL lookup forked from jbboehr/dnsbl.php

## Installation

With [composer](http://getcomposer.org)


```json
{
    "require": {
        "mhoffmann/dnsrbl": "0.1.*"
    }
}
```


## Usage

```php
$rbl = new DNSRBL(
    array(
        'dnsbl' => array(
            'sbl.spamhaus.org'
        ),
        'surbl' => array(
            'dbl.spamhaus.org'
        )
    )
);

//checks the surbl
var_export($rbl->isListed('dbltest.com')); echo ";\n";

//checks the dnsbl
var_export($rbl->getListingBlacklists('127.0.0.2')); echo ";\n";
```

```php
true;
array (
  0 => 'sbl.spamhaus.org',
);
```


## License

This project is licensed under the [PHP license](http://php.net/license/3_01.txt).
