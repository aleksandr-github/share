<?php

namespace App\Service;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Exception;
use Throwable;
use function Amp\Promise\wait;

class MeetingsDownloadService
{
    protected $base_url = "https://www.racingzone.com.au";
    protected $_base_url = "/form-guide/";

    protected $cacheService;
    protected $logger;
    protected $domParserService;
    protected $httpClientTimeoutSeconds = 60;

    public function __construct(LocalContentCacheService $cacheService)
    {
        $this->logger = new PrettyLogger(__FILE__, 'cache_download_log.txt');
        $this->cacheService = $cacheService;
        $this->_base_url = $this->base_url . $this->_base_url;
    }

    public function downloadMeetingsForDate(string $date): bool
    {
        $url = $this->_base_url . $date . "/";
        if ($this->cacheService->cacheExists($url)) {
            if ($this->cacheService->isCacheValid($url)) {
                $content = $this->cacheService->fetch($url);
            } else {
                throw new Exception("Something wrong with content");
            }
        } else {
            $client = HttpClientBuilder::buildDefault();
            $request = new Request($url);
            $request->setTransferTimeout($this->httpClientTimeoutSeconds * 1000);
            $request->setInactivityTimeout($this->httpClientTimeoutSeconds * 1000);

            try {
                $promise = $client->request($request);

                $response = wait($promise);
                $content = wait($response->getBody()->buffer());
            } catch (Throwable $e) {
                throw new Exception("(－‸ლ) Request for URL: " . $url . " failed. " . $e->getMessage());
            }
        }
        $this->cacheService->add($url, $content);
        $this->logger->log("Meetings link: " . $url . " added to cache files.");

        return true;
    }
}