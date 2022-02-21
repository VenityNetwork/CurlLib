# CurlLib
An asynchronous Curl library for PocketMine-MP

Don't do HTTP Request in an async task! It will block other async tasks like world population, package compression and others

## Usage
- Initialization
```php
$threads = 1; // Increase this if you have a lot of http requests
/** @var CurlLib $curl */
$curl = CurlLib::init($plugin, $threads);
```
- GET request
```php
// example to get IP information
$url = "http://ip-api.com/json/24.48.0.1";
$headers = [];
$curlOpts = [];
/** @var CurlLib $curl */
$curl->get($url, $headers, $curlOpts, function(CurlResponse $response) {
    echo $response->getStatusCode() . "\n";
    echo $response->getBody() . "\n";
    echo $response->getHeaders() . "\n";
});
```
- POST request
```php
// example to send message via discord webhook
$url = "https://discord.com/api/webhooks/12345/XXXXX";
$postField = json_encode(["content" => "Hello World"]);
$headers = [
    "Content-Type: application/json"
];
$curlOpts = [];
/** @var CurlLib $curl */
$curl->post($url, $postField, $headers, $curlOpts, function(CurlResponse $response) {
    echo $response->getStatusCode() . "\n";
    echo $response->getBody() . "\n";
    echo $response->getHeaders() . "\n";
});
```