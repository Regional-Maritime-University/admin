<?php

namespace Src\Base;

use Src\Controller\ExposeDataController;
use Src\System\DatabaseMethods;

class PurchaseDetail
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
}
