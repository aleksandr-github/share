<?php

namespace App\Twig;

use App\Service\DBConnector;
use DateTime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Carbon\Carbon;

class UIFooterExtension extends AbstractExtension
{
    /**
     * @var DBConnector
     */
    protected $dbConnector;

    public function __construct()
    {
        $this->dbConnector = new DBConnector();
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('getHorsesCount', [$this, 'getHorsesCount']),
            new TwigFunction('getRacesCount', [$this, 'getRacesCount']),
            new TwigFunction('getMeetingsCount', [$this, 'getMeetingsCount']),
            new TwigFunction('getHistoricResultsCount', [$this, 'getHistoricResultsCount']),
            new TwigFunction('getResultsCount', [$this, 'getResultsCount']),
            new TwigFunction('getLastUpdateDate', [$this, 'getLastUpdateDate'])
        ];
    }

    public function getHorsesCount(): int
    {
        return $this->dbConnector->getHorsesCount();
    }

    public function getRacesCount(): int
    {
        return $this->dbConnector->getRacesCount();
    }

    public function getMeetingsCount(): int
    {
        return $this->dbConnector->getMeetingsCount();
    }

    public function getHistoricResultsCount(): int
    {
        return $this->dbConnector->getHistoricResultsCount();
    }

    public function getResultsCount(): int
    {
        return $this->dbConnector->getResultsCount();
    }

    public function getLastUpdateDate(): string
    {
        $gitLastCommit = new Carbon(exec('git log -1 --pretty="format:%ci"'));

        return $gitLastCommit->diffForHumans();
    }
}