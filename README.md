# CurlLib
An asynchronous Curl library for PocketMine-MP

Don't do HTTP Request in an async task! It will block other async tasks like world population, package compression and others

## Usage
- Initialization
```php
$threads = 1; // Increase this if you have a lot of http requests
$curl = CurlLib::init($plugin, $threads);
```
- GET request
```php
$url = "";
$headers = [];
$curlOpts = [];
$curl->get($url, $headers, $curlOpts, function(CurlResponse $response) {
    echo $response->getStatusCode() . "\n";
    echo $response->getBody() . "\n";
    echo $response->getHeaders() . "\n";
});
```