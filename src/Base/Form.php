<?php

namespace Src\Base;

use Src\Controller\ExposeDataController;
use Src\System\DatabaseMethods;

class Form
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

    public function fetchAllFormPriceDetails()
    {
        $query = "SELECT f.id, f.name AS form_name, fc.name AS form_type_name, f.amount 
                FROM form_categories AS fc, forms AS f WHERE fc.id = f.form_category";
        return $this->dm->getData($query);
    }

    public function fetchFormPrice($form_price_id)
    {
        $query = "SELECT fp.id AS fp_id, ft.id AS ft_id, ft.name AS ft_name, fp.name AS fp_name, fp.amount 
                FROM form_categories AS ft, forms AS fp WHERE ft.id = fp.form_category AND fp.id = :i";
        return $this->dm->getData($query, array(":i" => $form_price_id));
    }

    public function addFormPrice($form_category, $form_name, $form_price)
    {
        $query = "INSERT INTO forms (form_category, `name`, amount) VALUES(:ft, :fn, :fp)";
        $params = array(":ft" => $form_category, ":fn" => $form_name, ":fp" => $form_price);
        $query_result = $this->dm->inputData($query, $params);

        if ($query_result)
            $this->log->activity(
                $_SESSION["user"],
                "INSERT",
                "Added new {$form_name} form costing {$form_price} to form type {$form_category}"
            );
        return $query_result;
    }

    public function updateFormPrice(int $form_id, $form_category, $form_name, $form_price)
    {
        $query = "UPDATE forms SET amount = :fp, form_category = :ft, `name` = :fn WHERE id = :i";
        $params = array(":i" => $form_id, ":ft" => $form_category, ":fn" => $form_name, ":fp" => $form_price);
        $query_result = $this->dm->inputData($query, $params);

        if ($query_result)
            $this->log->activity(
                $_SESSION["user"],
                "UPDATE",
                "Updated {$form_name} form costing {$form_price} to form type {$form_category}"
            );
        return $query_result;
    }

    public function deleteFormPrice($form_price_id)
    {
        $query = "DELETE FROM forms WHERE id = :i";
        $params = array(":i" => $form_price_id);
        $query_result = $this->dm->inputData($query, $params);

        if ($query_result)
            $this->log->activity(
                $_SESSION["user"],
                "DELETE",
                "Deleted form with id {$form_price_id}"
            );
        return $query_result;
    }

    /**
     * Fetching forms sale data totals
     */

    public function fetchTotalFormsSold($admin_period)
    {
        $query = "SELECT COUNT(pd.id) AS total 
                 FROM purchase_detail AS pd, forms AS ft, admission_period AS ap, vendor_details AS v 
                 WHERE pd.form_id = ft.id AND pd.admission_period = ap.id AND pd.vendor = v.id AND ap.id = :ai";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchTotalPostgradsFormsSold($admin_period)
    {
        $query = "SELECT COUNT(pd.id) AS total 
         FROM 
             purchase_detail AS pd, forms AS ft, admission_period AS ap, vendor_details AS v  
         WHERE
             pd.form_id = ft.id AND pd.admission_period = ap.id AND 
             pd.vendor = v.id AND ap.id = :ai AND ft.name LIKE '%Post%' OR ft.name LIKE '%Master%'";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchTotalUdergradsFormsSold($admin_period)
    {
        $query = "SELECT COUNT(pd.id) AS total 
         FROM 
             purchase_detail AS pd, forms AS ft, admission_period AS ap, vendor_details AS v  
         WHERE
             pd.form_id = ft.id AND pd.admission_period = ap.id AND 
             pd.vendor = v.id AND ap.id = :ai AND (ft.name LIKE '%Degree%' OR ft.name LIKE '%Diploma%')";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchTotalShortCoursesFormsSold($admin_period)
    {
        $query = "SELECT COUNT(pd.id) AS total 
         FROM 
             purchase_detail AS pd, forms AS ft, admission_period AS ap, vendor_details AS v  
         WHERE
             pd.form_id = ft.id AND pd.admission_period = ap.id AND 
             pd.vendor = v.id AND ap.id = :ai AND ft.name LIKE '%Short%'";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchTotalVendorsFormsSold($admin_period)
    {
        $query = "SELECT COUNT(pd.id) AS total 
         FROM 
             purchase_detail AS pd, forms AS ft, 
             admission_period AS ap, vendor_details AS v  
         WHERE
             pd.form_id = ft.id AND pd.admission_period = ap.id AND 
             pd.vendor = v.id AND ap.id = :ai AND v.vendor_name NOT LIKE '%ONLINE%'";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchTotalOnlineFormsSold($admin_period)
    {
        $query = "SELECT COUNT(pd.id) AS total 
         FROM 
             purchase_detail AS pd, forms AS ft, 
             admission_period AS ap, vendor_details AS v  
         WHERE
             pd.form_id = ft.id AND pd.admission_period = ap.id AND 
             pd.vendor = v.id AND ap.id = :ai AND v.vendor_name LIKE '%ONLINE%'";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    /**
     * Fetching form sales data by statistics
     */

    public function fetchFormsSoldStatsByVendor($admin_period)
    {
        $query = "SELECT 
                     v.vendor_name, COUNT(pd.id) AS total, SUM(ft.amount) AS amount 
                 FROM 
                     purchase_detail AS pd, forms AS ft, 
                     admission_period AS ap, vendor_details AS v  
                 WHERE
                     pd.form_id = ft.id AND pd.admission_period = ap.id AND 
                     pd.vendor = v.id AND ap.id = :ai 
                 GROUP BY pd.vendor";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchFormsSoldStatsByPaymentMethod($admin_period)
    {
        $query = "SELECT 
                     pd.payment_method, COUNT(pd.id) AS total, SUM(ft.amount) AS amount 
                 FROM 
                     purchase_detail AS pd, forms AS ft, 
                     admission_period AS ap, vendor_details AS v  
                 WHERE
                     pd.form_id = ft.id AND pd.admission_period = ap.id AND 
                     pd.vendor = v.id AND ap.id = :ai 
                 GROUP BY pd.payment_method";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchFormsSoldStatsByFormType($admin_period)
    {
        $query = "SELECT 
                     ft.name, COUNT(pd.id) AS total, SUM(ft.amount) AS amount 
                 FROM 
                     purchase_detail AS pd, forms AS ft, 
                     admission_period AS ap, vendor_details AS v  
                 WHERE
                     pd.form_id = ft.id AND pd.admission_period = ap.id AND 
                     pd.vendor = v.id AND ap.id = :ai 
                 GROUP BY pd.form_id";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchFormsSoldStatsByCountry($admin_period)
    {
        $query = "SELECT 
                     pd.country_name, pd.country_code, COUNT(pd.id) AS total, SUM(ft.amount) AS amount 
                 FROM 
                     purchase_detail AS pd, forms AS ft, 
                     admission_period AS ap, vendor_details AS v  
                 WHERE
                     pd.form_id = ft.id AND pd.admission_period = ap.id AND 
                     pd.vendor = v.id AND ap.id = :ai 
                 GROUP BY pd.country_code";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchFormsSoldStatsByPurchaseStatus($admin_period)
    {
        $query = "SELECT 
                     pd.status, COUNT(pd.id) AS total, SUM(ft.amount) AS amount 
                 FROM 
                     purchase_detail AS pd, forms AS ft, 
                     admission_period AS ap, vendor_details AS v  
                 WHERE
                     pd.form_id = ft.id AND pd.admission_period = ap.id AND 
                     pd.vendor = v.id AND ap.id = :ai 
                 GROUP BY pd.status";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }
}
