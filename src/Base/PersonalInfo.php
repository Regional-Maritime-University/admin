<?php

namespace Src\Base;

use Src\Controller\ExposeDataController;
use Src\System\DatabaseMethods;

class PersonalInfo
{

    private $dm = null;
    private $expose = null;
    private $log = null;

    public function __construct($db, $user, $pass)
    {
        $this->dm = new DatabaseMethods($db, $user, $pass);
        $this->expose = new ExposeDataController($db, $user, $pass);
        $this->log = new Log($db, $user, $pass);
    }

    public function fetchApplicantPersInfoByAppID(int $appID): mixed
    {
        return $this->dm->getData("SELECT * FROM `personal_information` WHERE app_login = :i", array(":i" => $appID));
    }

    public function fetchApplicantAppNumber(int $appID): mixed
    {
        return $this->dm->getData("SELECT pd.`app_number` FROM `purchase_detail` AS pd, applicants_login AS al 
        WHERE al.purchase_id = pd.id AND al.id = :i", array(":i" => $appID));
    }
}
