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

class GetHorsesForRacesTask extends AbstractHttpTask implements Task
{
    /**
     * @var array
     */
    protected $data;

    public function __construct(array $race)
    {
        parent::__construct();

        $this->data = $race;
    }

    /**
     * @param Environment $environment
     * @return array
     * @throws Exception
     */
    public function run(Environment $environment)
    {
        $horsesArray = [];
        $algStart = microtime(true);
        $this->logger->log("(╯°□°）╯ ︵ ┻━┻ Starting worker for: " . $this->data["race_title"] . '@' . $this->data["race_schedule_time"], __FILE__);

        if (preg_match('/\/(\d+)-[^\/]+\/\s*$/', $this->data["race_url"], $matches)) {
            $race_site_id = $matches[1];
        }

        if (!isset($race_site_id)) {
            $this->logger->log("No race ID found.", __FILE__);
            throw new Exception("No race ID found.");
        }

        $url = $this->base_url . "/formguide-detail.php?race_id=" . $race_site_id;

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
        foreach ($xpath->query('//table[contains(@class, "formguide")][1]//tr[contains(@class, "row-vm")]') as $row) {
            $horse = array();
            $horse["race_id"] = $this->data["race_id"];
            $horse["name"] = $xpath->evaluate('string(./td[4]/a/text())', $row);
            $horse["horse_name"] = $horse["name"];
            $horse["horse_number"] = $xpath->evaluate('string(./td[1]/text())', $row);
            $horse["horse_weight"] = $xpath->evaluate('string(./td[6]/span/text())', $row);
            $horse["horse_fixed_odds"] = $xpath->evaluate('string(./td[11]/span/a/text())', $row);
            //$horse["horse_h2h"] = $xpath->evaluate('string(./td[4]/span[contains(@class, "h2h")]/text())', $row);

            $horse["horse_win"] = $xpath->evaluate('string(./td[8]/span/text())', $row);
            $horse["horse_plc"] = $xpath->evaluate('string(./td[9]/span/text())', $row);
            $horse["horse_avg"] = $xpath->evaluate('string(./td[10]/span/text())', $row);

            $horse["horse_latest_results"] = $xpath->evaluate('string(./td[3]/@title)', $row);
            $horse["horse_latest_results"] = str_replace(array('<b>', '</b>'), '', $horse["horse_latest_results"]);

            $class = $xpath->evaluate('string(./@class)', $row);
            if (preg_match('/^(\d+)/', $class, $matches)) {
                $horse["id"] = $matches[1];
            }

            if ( preg_match( '/\$\("span.horse' . $horse["id"] . '"\)\.text\("([^"]*)"\)/', $content, $matches ) ) {
                $horse["horse_h2h"] = $matches[1];
            }
            $horse["field_id"] = $xpath->evaluate('string(./@rel)', $row);
            $horsesArray[$race_site_id][] = $horse;
        }

        $time_elapsed_secs = microtime(true) - $algStart;
        $this->logger->log("┏━┓ ︵  /(^.^/) Worker for: " . $this->data["race_title"] . '@' . $this->data["race_schedule_time"] . " finished in " . number_format($time_elapsed_secs, 2) . ' seconds', __FILE__);

        return $horsesArray;
    }
}