<?php

namespace App\Service;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use App\DataTransformer\MeetingsForDateRangeTransformer;
use App\Model\DateRange;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use DOMDocument;
use DOMXPath;
use mysqli;
use Symfony\Component\Config\Definition\Exception\Exception;
use Throwable;
use function Amp\Promise\wait;

class RacingZoneScraper
{
    protected $_base_url = "/form-guide/";
    /** @var mysqli */
    protected $_mysqli;
    protected $_cookiefile = "";
    protected $_ch;
    /** @var PrettyLogger */
    protected $logger;

    protected $base_url = "https://www.racingzone.com.au";
    protected $httpClientTimeoutSeconds = 60;

    /**
     * @var LocalContentCacheService
     */
    protected $cacheService;

    /**
     * RacingZoneScraper constructor.
     * @param $mysqli mysqli
     */
    public function __construct($mysqli) {
        $this->_mysqli = $mysqli;
        $this->logger = new PrettyLogger(__FILE__, 'main_log.txt');
        $this->_cookiefile = dirname(__FILE__) . '/cookies.txt';
        if (file_exists($this->_cookiefile)) {
            unlink($this->_cookiefile);
        }
        $this->_base_url = $this->base_url . $this->_base_url;

        $this->cacheService = new LocalContentCacheService();
    }

    /**
     * @param string $start_date
     * @param string $end_date
     * @return \App\Model\DateRange
     * @throws \Exception
     */
    public function createDateRange(string $start_date, string $end_date): DateRange
    {
        $dateRange = new DateRange();
        $format = 'Y-m-d';

        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $invert = $start > $end;
        $dates = [];
        $dates[] = $start->format($format);
        while ($start != $end) {
            $start->modify(($invert ? '-' : '+') . '1 day');
            $dates[] = $start->format($format);
        }

        foreach ($dates as $dateKey => $date) {
            $this->logger->log("Processing date range, row (" . $dateKey . ") out of (" . count($dates) . ")");
            $dateRange->add($date);
        }

        return $dateRange;
    }

    /**
     * @param string $date
     * @return array
     * @throws \Exception
     *
     * @todo should be refactored into task
     *
     */
    public function getMeetingsForDate(string $date): array
    {
        $start = microtime(true);
        $meetingsForDate = [];
        $url = $this->_base_url . $date . "/";
        $cached = "";
        if ($this->cacheService->cacheExists($url)) {
            if ($this->cacheService->isCacheValid($url)) {
                $content = $this->cacheService->fetch($url);
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

                $response = wait($promise);
                $content = wait($response->getBody()->buffer());
            } catch (Throwable $e) {
                throw new Exception("(－‸ლ) Request for URL: " . $url . " failed. " . $e->getMessage());
            }
        }

        $time_elapsed_secs = microtime(true) - $start;
        $this->logger->log('HTTPRequest for: ' . str_replace("https://www.racingzone.com.au", "{HOST}", $url) . ' took ' . number_format($time_elapsed_secs, 2) . ' seconds. ' . $cached);
        $this->cacheService->add($url, $content);

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($content, LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);
        foreach ($xpath->query('//table[contains(@class, "meeting")]//tr') as $row) {
            $meeting = array();
            $meeting["date"] = $date;
            $meeting["place"] = $xpath->evaluate('string(./td[1]/a/text())', $row);
            $meeting["url"] = $xpath->evaluate('string(./td[1]/a/@href)', $row);
            $meeting["url"] = $this->base_url . $meeting["url"];
            array_push($meetingsForDate, $meeting);
        }

        return $meetingsForDate;
    }

    /**
     * @param \App\Model\DateRange $dateRange
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function getMeetingsForDateRange(DateRange $dateRange): ArrayCollection
    {
        $meetings = [];
        foreach ($dateRange->getAll() as $date) {
            $meetings[] = $this->getMeetingsForDate($date);
        }

        return (new MeetingsForDateRangeTransformer($meetings))->transform();
    }

    private function get_post_fields($arrPostFields) {
        $strPostFields = "";
        $postFieldValues = array();
        foreach ($arrPostFields as $key => $value) {
            array_push($postFieldValues, urlencode($key) . "=" . urlencode($value));
        }
        $strPostFields = join("&", $postFieldValues);
        return $strPostFields;
    }

    public function initLockFile()
    {
        // @TODO @STUB
    }

    public function releaseLockFile()
    {
        // @TODO @STUB
    }

    /**
     * @deprecated refactor to use HttpClientBuilder with Cache
     *
     * @param $strSubmitURL
     * @param null $arrPostFields
     * @param string $strReferrer
     * @param string $strCookieFile
     * @param string $strProxy
     * @param null $arrCustomHeaders
     * @return bool|string
     */
    private function CallPage($strSubmitURL, $arrPostFields = null, $strReferrer = "", $strCookieFile = "", $strProxy = "", $arrCustomHeaders = null)
    {
        $header[0] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
        //$header[] = "Accept-Encoding: gzip, deflate, br";
        $header[] = "Cache-Control: no-cache";
        $header[] = "Connection: keep-alive";
        $header[] = "Accept-Language: en-us,en;q=0.5";
        $header[] = "User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36";
        if ($arrCustomHeaders) {
            foreach ($arrCustomHeaders as $customHeader) {
                $header[] = $customHeader;
            }
        }
        $cookie_jar = $strCookieFile;
        if (!$this->_ch) {
            $this->_ch = curl_init();
        }
        curl_setopt($this->_ch, CURLOPT_ENCODING, 'gzip, deflate');
        curl_setopt($this->_ch, CURLOPT_VERBOSE, false);
        if ($strProxy != "") {
            curl_setopt($this->_ch, CURLOPT_PROXY, $strProxy);
        }
        if ($cookie_jar != "") {
            curl_setopt($this->_ch, CURLOPT_COOKIEJAR, $cookie_jar);
            curl_setopt($this->_ch, CURLOPT_COOKIEFILE, $cookie_jar);
        }
        if ($strReferrer != "") {
            curl_setopt($this->_ch, CURLOPT_REFERER, "$strReferrer");
        }
        //curl_setopt($this->_ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->_ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($this->_ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($this->_ch, CURLOPT_TIMEOUT, 140);
        curl_setopt($this->_ch, CURLOPT_URL, $strSubmitURL);
        if ($arrPostFields != null) {
            //set type as an post
            //curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($this->_ch, CURLOPT_HEADER, true);
            curl_setopt($this->_ch, CURLOPT_CUSTOMREQUEST, 'POST');
            $strPostFields = $this->get_post_fields($arrPostFields);
            //field name
            $header[] = "Content-length: " . strlen($strPostFields);
            $header[] = 'Content-Type: application/x-www-form-urlencoded';
            $header[] = "method:POST";
            //$header[] = 'X-Requested-With: XMLHttpRequest';
            curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $strPostFields);
            //echo "posting $strPostFields";

        } else {
            curl_setopt($this->_ch, CURLOPT_HEADER, false);
        }
        // don' verify ssl host
        curl_setopt($this->_ch, CURLOPT_SSL_VERIFYPEER, true);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->_ch, CURLOPT_HTTPHEADER, $header);
        //curl_setopt($this->_ch, CURLOPT_VERBOSE, true);
        $strData = curl_exec($this->_ch);
        if (!$strData) {
            die("cURL error: " . curl_error($this->_ch) . "\n");
            return '';
        }
        //curl_close($ch);
        //unset($ch);

        return $strData;
    }
}
