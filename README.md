Cohere PHP SDK [WIP]

This SDK is a light wrapper for Cohere API. See example below for usage. Eventually, this project will follow semantic versioning.

### Available Endpoints

- Summarize
- Generate
- Rerank
- Embed
- Classify
- Chat

### Example usaage.
```
<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/env.php';

use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use GuzzleHttp\Client as GuzzleClient;
use Cohere\Client;
use Cohere\Config;
use Cohere\Payload;
use Monolog\Logger;

$httpClient = new GuzzleClient();
$requestFactory = new RequestFactory(); 
$streamFactory = new StreamFactory();
$config = new Config();
$logger = new Logger('cohere');
$payload = new Payload();
$client = new Client($httpClient, $requestFactory, $streamFactory, $config, $payload, $logger);

$res = $client->summarize("Here are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form, by injected humour, or randomised words which don't look even slightly believable. If you are going to use a passage of Lorem Ipsum, you need to be sure there isn't anything embarrassing hidden in the middle of text. All the Lorem Ipsum generators on the Internet tend to repeat predefined chunks as necessary, making this the first true generator on the Internet. It uses a dictionary of over 200 Latin words, combined with a handful of model sentence structures, to generate Lorem Ipsum which looks reasonable. The generated Lorem Ipsum is therefore always free from repetition, injected humour, or non-characteristic words etc.");

print_r($res);
```
