Cohere PHP SDK [WIP]
Inspired by Cohere, Aidan Gomez, and https://github.com/cohere-ai/cohere-python

### Example usaage.
```
<?php

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use GuzzleHttp\Client as GuzzleClient;
use Cohere\Client;
use Cohere\Config;


$httpClient = new GuzzleClient();
$requestFactory = new RequestFactory(); 
$streamFactory = new StreamFactory();
$config = new Config();
$client = new Client($httpClient, $requestFactory, $streamFactory, $config);
$res = $client->embed(['Hello','world!']);
print_r($res);
```
