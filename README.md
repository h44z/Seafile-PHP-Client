# Seafile-PHP-Client

Seafile API Client written in PHP. The client is using https://github.com/rene-s/Seafile-PHP-SDK and offers basic file and folder operations.

## Installation

- Clone this repository
- Run composer install
- Run composer dump-autoload -o


## Basic usage
An example file can be found in the test folder.

```php
require_once __DIR__ . '/class.seafileapiclient.php';

use Seafile\SeafileAPIClient;

$client = new SeafileAPIClient("https://seacloud.cc");
$client->getAPIToken("test@test.com", "test12345");
$client->initClient();

$lsdata = $client->ls("/");
foreach($lsdata as $path => $details) {
	printf("%s \t-- Name: %s, ID: %s, is encrypted: %s<br>\n", $path, $details["displayname"], $details["id"], $details["encrypted"] ? 'YES' : 'NO');
}
```

## Contributing
Feel free to contribute some code ;)
