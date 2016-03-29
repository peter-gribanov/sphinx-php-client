[![Latest Stable Version](https://poser.pugx.org/sphinx/client/v/stable)](https://packagist.org/packages/sphinx/client)
[![Total Downloads](https://poser.pugx.org/sphinx/client/downloads)](https://packagist.org/packages/sphinx/client)
[![Latest Unstable Version](https://poser.pugx.org/sphinx/client/v/unstable)](https://packagist.org/packages/sphinx/client)
[![Build Status](https://travis-ci.org/peter-gribanov/sphinx-php-client.svg?branch=master)](https://travis-ci.org/sphinx-client)
[![Code Coverage](https://scrutinizer-ci.com/g/peter-gribanov/sphinx-php-client/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/peter-gribanov/sphinx-php-client/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/peter-gribanov/sphinx-php-client/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/peter-gribanov/sphinx-php-client/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/b370b11f-f70f-421c-b2d8-e7411b74bb33/mini.png)](https://insight.sensiolabs.com/projects/b370b11f-f70f-421c-b2d8-e7411b74bb33)
[![License](https://poser.pugx.org/sphinx/client/license.png)](https://packagist.org/packages/sphinx/client)

# Sphinx PHP client

Copy / paste from [sphinxsearch/sphinx](https://github.com/sphinxsearch/sphinx/blob/master/api/sphinxapi.php)

## Installation

Add the following to the `require` section of your composer.json file:

```
"sphinx/client": "dev-master"
```

## Usage

Search **test** word in Sphinx for **example_idx** index.

```php
$sphinx = new SphinxClient();
$sphinx->setServer('localhost', 6712);
$sphinx->setMatchMode(SPH_MATCH_ANY);
$sphinx->setMaxQueryTime(3);

$result = $sphinx->query('test', 'example_idx');

var_dump($result);
```

Printed result:

```
array(10) {
  ["error"]=>
  string(0) ""
  ["warning"]=>
  string(0) ""
  ["status"]=>
  int(0)
  ["fields"]=>
  array(3) {
    [0]=>
    string(7) "subject"
    [1]=>
    string(4) "body"
    [2]=>
    string(6) "author"
  }
  ["attrs"]=>
  array(0) {
  }
  ["matches"]=>
  array(1) {
    [3]=>
    array(2) {
      ["weight"]=>
      int(1)
      ["attrs"]=>
      array(0) {
      }
    }
  }
  ["total"]=>
  int(1)
  ["total_found"]=>
  int(1)
  ["time"]=>
  float(0)
  ["words"]=>
  array(1) {
    ["to"]=>
    array(2) {
      ["docs"]=>
      int(1)
      ["hits"]=>
      int(1)
    }
  }
}
```

## License

This bundle is under the [GPL-3.0 license](https://opensource.org/licenses/GPL-3.0).
See the complete license in the bundle: LICENSE
