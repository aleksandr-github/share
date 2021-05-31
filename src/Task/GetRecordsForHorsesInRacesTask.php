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

class GetRecordsForHorsesInRacesTask extends AbstractHttpTask implements Task
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @var string
     */
    protected $raceKeyId;

    public function __construct(array $horseRaceResult, string $raceKeyId)
    {
        parent::__construct();

        $this->data = $horseRaceResult;
        $this->raceKeyId = $raceKeyId;
    }

    /**
     * @param Environment $environment
     * @return array|\Generator
     * @throws \Exception
     */
    public function run(Environment $environment)
    {
        /**
         * $this->data
        (
        [race_id] => 1
        [name] => Head Legislator
        [horse_name] => Head Legislator
        [horse_number] => 1
        [horse_weight] => 58
        [horse_fixed_odds] => $2.35
        [horse_win] => 0%
        [horse_plc] => 50%
        [horse_avg] => 4k
        [horse_latest_results] => 7 x 292
        [id] => 921952
        [horse_h2h] => 0-1
        [field_id] => 11627183
        )

         */
        $horseRaceResult = $this->data;
        $raceKeyId = $this->raceKeyId;
        $recordsArray = [];
        $cached = "";
        $algStart = microtime(true);

        $this->logger->log("(╯°□°）╯ ︵ ┻━┻ Starting worker for: " . $this->data["name"] . '@' . $this->data["race_id"] . '@' . $this->data["id"], __FILE__);

        $url = $this->base_url . "/past-form-from-results2.php?horse=" . $horseRaceResult["id"] . "&race_id=" . $raceKeyId . "&field_id=" . $horseRaceResult["field_id"];

        if ($this->cacheService->cacheExists($url)) {
            if ($this->cacheService->isCacheValid($url)) {
                $content =  $this->cacheService->fetch($url);
                $cached = "[CACHED]";
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

        $records = array();
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($content, LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);
        foreach ($xpath->query('//table[@class="pastform"]//tr[starts-with(@class, "result")]') as $row) {
            $record = array();
            $record["horse_id"] = $horseRaceResult["id"];
            $record["name"] = $horseRaceResult["name"];
            $record["race_date"] = $xpath->evaluate('string(./td[1]/text())', $row);
            $record["race_name"] = $xpath->evaluate('string(./td[7]/text())', $row);
            $record["track"] = $xpath->evaluate('string(./td[2]/text())', $row);
            $record["track_name"] = $xpath->evaluate('string(./td[2]/@title)', $row);
            $record["distance"] = $xpath->evaluate('string(./td[3]/text()[1])', $row);
            $record["pos"] = explode('/', $xpath->evaluate('string(./td[4]/strong/text())', $row))[0];
            $record["mrg"] = $xpath->evaluate('string(./td[5]/text())', $row);
            $record["condition"] = $xpath->evaluate('string(./td[6]/text())', $row);
            $record["weight"] = $xpath->evaluate('string(./td[9]/text())', $row);
            $record["prize"] = $xpath->evaluate('string(./td[12]/text()[1])', $row);
            $record["time"] = $this->convertTimeToMinutes($xpath->evaluate('string(./td[13]/text())', $row));
            $record["race_old_id"] = $this->raceKeyId;

            if ( $record["time"] != 0 ) {
                $record["sectional"] = $xpath->evaluate('string(./td[14]/text())', $row);
                $record["time2"] = $this->calculateModifiedTime($record["time"], $record["mrg"]);
                array_push($records, $record);
            }
        }

        $recordsArray[] = $records;

        $time_elapsed_secs = microtime(true) - $algStart;
        $this->logger->log("┏━┓ ︵  /(^.^/) Worker for: " . $this->data["name"] . '@' . $this->data["race_id"] . '@' . $this->data["id"] . " finished in " . number_format($time_elapsed_secs, 2) . ' seconds ' . $cached, __FILE__);

        return $recordsArray;
    }

    /**
     * @param $time
     * @return string
     */
    private function convertTimeToMinutes($time): string
    {
        if (preg_match('/(\d+):/', $time, $matches)) {
            $minutes = $matches[1];
        } else {
            $minutes = 0;
        }
        if (preg_match('/([\d.]+)$/', $time, $matches)) {
            $seconds = $matches[1];
        } else {
            $seconds = 0;
        }
        $result = $minutes + $seconds / 60;

        return number_format((float)$result, 2, '.', '');
    }

    /**
     * @param $original_time
     * @param $length
     * @return string
     */
    private function calculateModifiedTime($original_time, $length): string
    {
        if ($original_time <= 1.19) {
            $modified_time = $original_time + ($length * 0.04);
        } else {
            $modified_time = $original_time + ($length * 0.03);
        }
        return number_format($modified_time, 2, '.', '');
    }
}