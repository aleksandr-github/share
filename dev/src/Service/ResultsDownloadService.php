<?php

namespace App\Service;

use App\Model\SimpleHTMLDOM;
use Exception;

class ResultsDownloadService
{
    protected $cacheService;
    protected $logger;
    protected $domParserService;

    public function __construct(LocalContentCacheService $cacheService, SimpleHTMLDomService $domParserService)
    {
        $this->logger = new PrettyLogger(__FILE__, 'cache_download_log.txt');
        $this->cacheService = $cacheService;
        $this->domParserService = $domParserService;
    }

    public function downloadResultsForDate(string $date): bool
    {
        $formattedDate = $date;
        $this->logger->log('Downloader for date ' . $formattedDate . ' is starting');

        $base_url = 'https://www.racingzone.com.au';
        $part_url = '/results/' . $formattedDate . '/';
        $parse_url = $base_url . $part_url;

        $cachedMain = false;
        if ($this->cacheService->cacheExists($parse_url)) {
            if ($this->cacheService->isCacheValid($parse_url)) {
                $html = $this->cacheService->fetch($parse_url);
                $cachedMain = true;
            } else {
                throw new Exception("Something wrong with content");
            }
        } else {
            $html = $this->domParserService->file_get_html($parse_url);
        }
        $this->cacheService->add($parse_url, $html);
        $this->logger->log("Results link: " . $parse_url . " added to cache files.");
        if ($cachedMain) {
            $dom = new SimpleHTMLDOM(null, $lowercase = true, true, 'UTF-8', $stripRN = true, "\r\n", ' ');
            $html = $dom->load($html, $lowercase, $stripRN);
        }

        $this->logger->log("[" . date("Y-m-d H:i:s") . "] Main parse url is $parse_url");
        $tables = $html->find('table.meeting');
        $this->logger->log("Fetching meeting tables of count: " . count($tables));
        foreach ($tables as $key => $table) {
            $this->logger->log("Parsing meeting table (" . $key . ") out of (" . count($tables) . ")");
            $rows = $table->find('tr');
            foreach ($rows as $rowKey => $row) {
                $this->logger->log("Parsing row (" . $rowKey . ") out of (" . count($rows) . ")");

                $meeting_name = $row->find('td', 0)->find('a', 0)->plaintext;
                $this->logger->log("Fetching meeting name: " . $meeting_name);
                $tds = $row->find('td.popup-race');
                foreach ($tds as $raceRow => $td) {
                    $this->logger->log("Parsing race row (" . $raceRow . ") out of (" . count($tds) . ")");
                    if (empty($td->title)) {
                        continue;
                    }
                    $link = $td->find('a', 0)->href;

                    $race_link = $base_url . $link;
                    if ($this->cacheService->cacheExists($race_link)) {
                        if ($this->cacheService->isCacheValid($race_link)) {
                            $race_html = $this->cacheService->fetch($race_link);
                        } else {
                            throw new Exception("Something wrong with content");
                        }
                    } else {
                        $race_html = $this->domParserService->file_get_html($race_link);
                    }
                    $this->cacheService->add($race_link, $race_html);
                    $this->logger->log("Race link: " . $race_link . " added to cache files.");
                }
            }
        }

        return true;
    }
}