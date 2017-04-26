# dnsrbl


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
$dnsbl = new \DNSBL\DNSBL(array(
    'blacklists' => array(
        'bl.spamcop.net'
    )
));
var_export($dnsbl->isListed('127.0.0.2')); echo ";\n";
var_export($dnsbl->getListingBlacklists('127.0.0.2')); echo ";\n";
```

```php
true;
array (
  0 => 'bl.spamcop.net',
);
```


## License

This project is licensed under the [PHP license](http://php.net/license/3_01.txt).
