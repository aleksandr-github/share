<?php

namespace App\Task;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

class ResetHandicapTask extends AbstractMySQLTask implements Task
{
    public function run(Environment $environment): bool
    {
        $mysqli = $this->initMultiSessionDatabase();

        return $mysqli->query("UPDATE `tbl_hist_results` SET `handicap`='0' WHERE `handicap`!='0'");
    }
}