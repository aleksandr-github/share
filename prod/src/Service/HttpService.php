<?php

namespace App\Service;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;

class HttpService
{
    protected $cacheService;
    protected $httpClientTimeoutSeconds = 60;

    public function __construct()
    {
        $this->cacheService = new LocalContentCacheService();
    }

    /**
     * @param string $url
     * @return \Generator|mixed|null
     * @throws \Exception
     */
    public function fetchURL(string $url)
    {
        $content = null;

        if ($this->cacheService->cacheExists($url)) {
            if ($this->cacheService->isCacheValid($url)) {
                return $this->cacheService->fetch($url);
            }
        } else {
            $client = HttpClientBuilder::buildDefault();
            $request = new Request($url);
            $request->setTransferTimeout($this->httpClientTimeoutSeconds * 1000);
            $request->setInactivityTimeout($this->httpClientTimeoutSeconds * 1000);

            try {
                $promise = $client->request($request);

                $response = yield $promise;
                $content = yield $response->getBody();
            } catch (\Throwable $e) {
                throw new \Exception("(－‸ლ) Request for URL: " . $url . " failed.");
            }
        }

        return $content;
    }
}