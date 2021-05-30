<?php

namespace App\Task;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

class ResetRankTask extends AbstractMySQLTask implements Task
{
    public function run(Environment $environment): bool
    {
        $mysqli = $this->initMultiSessionDatabase();
        $mysqli->query("UPDATE `tbl_races` SET `rank_status`='0' WHERE `rank_status`!='0'");

        return $mysqli->query("Update `tbl_hist_results` SET `rank`='0.00' WHERE `rank`!='0.00'");
    }
}