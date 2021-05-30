<?php

namespace App\Task;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

class UpdateSectionalTask extends AbstractMySQLTask implements Task
{
    public function run(Environment $environment): bool
    {
        $mysqli = $this->initMultiSessionDatabase();
        $sectionalQuery = $mysqli->query("SELECT `hist_id`, `sectional` FROM `tbl_hist_results` WHERE `sectional`!='0' AND `avgsec`=''");

        if ($sectionalQuery->num_rows > 0) {
            while ($histRecord = $sectionalQuery->fetch_object()) {
                $sectional = explode("/", $histRecord->sectional);
                if ($sectional[0] < 651) {
                    $avgvalue = $sectional[1];
                    $mysqli->query("UPDATE `tbl_hist_results` SET `avgsec`='$avgvalue' WHERE `hist_id`='$histRecord->hist_id'");
                }
            }
        }

        return true;
    }
}