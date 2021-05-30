<?php

namespace App\Task;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use DOMDocument;
use DOMXPath;
use Exception;
use Throwable;

class GetRacesForMeetingsTask extends AbstractHttpTask implements Task
{
    /**
     * @var array
     */
    protected $data;

    public function __construct(array $meeting)
    {
        parent::__construct();

        $this->data = $meeting;
    }

    /**
     * @param Environment $environment
     * @return array|\Generator
     * @throws \Exception
     */
    public function run(Environment $environment)
    {
        $racesArray = [];
        $algStart = microtime(true);

        $this->logger->log("(╯°□°）╯ ︵ ┻━┻ Starting worker for: " . $this->data["meeting_name"], __FILE__);
        $url = $this->data["meeting_url"];
        if ($this->cacheService->cacheExists($url)) {
            if ($this->cacheService->isCacheValid($url)) {
                $content =  $this->cacheService->fetch($url);
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

                $response = yield $promise;
                $content = yield $response->getBody()->buffer();
            } catch (Throwable $e) {
                throw new Exception("(－‸ლ) Request for URL: " . $url . " failed.");
            }
        }
        $this->cacheService->add($url, $content);

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($content, LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);
        foreach ($xpath->query('(//h3[.="Race Schedule"]/following-sibling::table[contains(@class, "info")])[1]//tr') as $row) {
            $race = array();
            $race["meeting_id"] = $this->data["meeting_id"];
            $race["title"] = $xpath->evaluate('string(./td[3]/a/text())', $row);
            $race["number"] = $xpath->evaluate('string(./td[1]/text())', $row);
            $race["schedule_time"] = $xpath->evaluate('string(./td[2]/text())', $row);
            $race["url"] = $xpath->evaluate('string(./td[3]/a/@href)', $row);
            $race["url"] = $this->base_url . $race["url"];
            $race["distance"] = $xpath->evaluate('string(./td[6]/text())', $row);
            $race["distance"] = (int) str_replace('m', '', $race["distance"]);
            array_push($racesArray, $race);
        }

        $time_elapsed_secs = microtime(true) - $algStart;
        $this->logger->log("┏━┓ ︵  /(^.^/) Worker for: " . $this->data["meeting_name"] . " finished in " . number_format($time_elapsed_secs, 2) . ' seconds', __FILE__);

        return $racesArray;
    }
}