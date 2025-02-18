<?php

namespace Src\Controller;

use Exception;
use PhpOffice\PhpWord\TemplateProcessor;
use Src\Base\DBCommons;

class GenerateAdLetter
{
    private int $appID;
    private int $prog_id;
    private string $stream_applied;
    private bool $email_letter;
    private bool $sms_notify;

    public function __construct(int $appID, int $prog_id, string $stream_applied, bool $email_letter = false, bool $sms_notify = false)
    {
        $this->$appID = $appID;
        $this->$prog_id = $prog_id;
        $this->$stream_applied = $stream_applied;
        $this->$email_letter = $email_letter;
        $this->$sms_notify = $sms_notify;
    }

    public function run(): array
    {
        $l_res = $this->loadLetterData($this->appID, $$this->prog_id, $$this->stream_applied);
        if (!$l_res["success"]) return $l_res;

        $g_res = $this->generateLetter($l_res["data"], $l_res["type"], $l_res["period"]);
        if (!$g_res["success"]) return $g_res;
    }

    public function emailLetter(): void
    {
        if ($this->email_letter) $this->email($this->appID);
    }

    public function notifyApplicant(): void
    {
        if ($this->sms_notify) $this->notify($this->appID);
    }

    private function email(): void
    {
        if ($this->email_letter) $this->email($this->appID);
    }

    private function notify(): void
    {
        if ($this->sms_notify) $this->notify($this->appID);
    }


    private function generateLetter($letter_data, $letter_type = "undergrade", $admission_period = []): array
    {
        try {
            $dir_path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'admission_letters' . DIRECTORY_SEPARATOR;

            // Create directories recursively
            $academic_year_path = $dir_path . strtolower($admission_period["academic_year"]) . DIRECTORY_SEPARATOR;
            $intake_path = $academic_year_path . strtolower($admission_period["intake"]) . DIRECTORY_SEPARATOR;
            $program_category = $intake_path . strtolower($letter_data['Program_Type']) . DIRECTORY_SEPARATOR;
            $stream_applied = $program_category . strtolower($letter_data['Program_Stream']) . DIRECTORY_SEPARATOR;
            $program_applied = $stream_applied . strtolower($letter_data['Program_Stream']) . DIRECTORY_SEPARATOR;

            foreach ([$academic_year_path, $intake_path, $program_category, $stream_applied, $program_applied] as $path) {
                if (!is_dir($path)) mkdir($path, 0755, true);
            }

            $template_processor = new TemplateProcessor($dir_path . $letter_type . '_template.docx');
            $output_path = $program_applied . "{$letter_data['app_number']}.docx";

            $template_processor->setValues($letter_data);
            $template_processor->saveAs($output_path);

            return ["success" => true, "message" => "Admission letter successfully generated!"];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    private function loadLetterData(): array
    {
        $db = "";
        $user = "";
        $pass = "";
        $app_pers_info = (new DBCommons($db, $user, $pass))->fetchApplicantPersInfoByAppID($this->appID)[0];
        $app_app_number = (new DBCommons($db, $user, $pass))->fetchApplicantAppNumber($this->appID)[0];
        $static_letter_data = (new DBCommons($db, $user, $pass))->fetchCurrentAdmissionLetterData()[0];
        $admission_period = (new DBCommons($db, $user, $pass))->fetchCurrentAdmissionPeriod()[0];
        $prog_info = (new DBCommons($db, $user, $pass))->fetchAllFromProgramByID($this->prog_id)[0];

        $letter_data = [];

        switch ($prog_info["program_code"]) {
            case 'BSC':
            case 'DIPLOMA':

                $letter_data = [
                    "success" => true,
                    "type" => "undergrade",
                    "period" => $admission_period,
                    "data" => [
                        'app_number' => $app_app_number["app_number"],
                        'Full_Name' => ucwords(strtolower(!empty($app_pers_info["middle_name"]) ? $app_pers_info["first_name"] . " " . $app_pers_info["middle_name"] . " " .  $app_pers_info["last_name"] : $app_pers_info["first_name"] . " " . $app_pers_info["last_name"])),
                        'Box_Location' => ucwords(strtolower($app_pers_info["postal_town"] . $app_pers_info["postal_spr"])),
                        'Box_Address' => ucwords(strtolower($app_pers_info["postal_addr"])),
                        'Location' => ucwords(strtolower($app_pers_info["postal_country"])),

                        'Year_of_Admission' => $admission_period["academic_year"],

                        'Program_Length_1' => strtoupper(strtolower($prog_info["duration"] . "-" . $prog_info["dur_format"])),
                        'Program_Offered_1' => strtoupper(strtolower($prog_info["name"])),
                        'Program_Length_2' => strtolower($prog_info["duration"] . "-" . $prog_info["dur_format"]),
                        'Program_Offered_2' => ucwords(strtolower((($prog_info["category"] === "DIPLOMA") ? str_replace("Diploma IN ", "", $prog_info["name"]) : $prog_info["name"]))),
                        'Program_Type' => ucwords(strtolower($prog_info["category"])),
                        'Program_Stream' => strtolower($this->stream_applied),
                        'No_of_Semesters' => $prog_info["num_of_semesters"] . " semesters",

                        'Commencement_Date' => (new \DateTime($static_letter_data["commencement_date"]))->format("l F j, Y"),
                        'Initial_Fees_in_Words' => $static_letter_data["initial_fees_in_words"],
                        'Initial_Fees_in_Figures' => $static_letter_data["initial_fees_in_figures"],
                        'Tel_Number_1' => $static_letter_data["tel_number_1"],
                        'Tel_Number_2' => $static_letter_data["tel_number_2"],
                        'Closing_Date' => (new \DateTime($static_letter_data["closing_date"]))->format("l F j, Y"),
                        'Orientation_Date' => (new \DateTime($static_letter_data["orientation_date"]))->format("l F j, Y"),
                        'Deadline_Date' => (new \DateTime($static_letter_data["deadline_date"]))->format("l F j, Y"),
                        'Registration_Fees_in_Words' => $static_letter_data["registration_fees_in_words"],
                        'Registration_Fees_in_Figures' => $static_letter_data["registration_fees_in_figures"],
                        'University_Registrar' => $static_letter_data["university_registrar"]
                    ]
                ];
                break;

            case 'MSC':
            case 'MA':
                $letter_data = [
                    "success" => true,
                    "type" => "postgrade",
                    "period" => $admission_period,
                    "data" => [
                        'app_number' => $app_app_number["app_number"],
                        'Full_Name' => !empty($app_pers_info["middle_name"]) ? $app_pers_info["first_name"] . " " . $app_pers_info["middle_name"] .  " " .  $app_pers_info["last_name"] : $app_pers_info["first_name"] . " " . $app_pers_info["last_name"],
                        'Box_Location' => $app_pers_info["postal_town"] . " " . $app_pers_info["postal_spr"],
                        'Box_Address' => $app_pers_info["postal_addr"],
                        'Location' => $app_pers_info["postal_country"],

                        'Year_of_Admission' => $admission_period["academic_year"],

                        'Program_Length_1' => $prog_info["duration"] . "-" . $prog_info["dur_format"],
                        'Program_Offered_1' => $prog_info["name"],
                        'Program_Length_2' => $prog_info["duration"] . "-" . $prog_info["dur_format"],
                        'Program_Offered_2' => ucwords($prog_info["name"]),
                        'Program_Type' => ucwords($prog_info["form_type"]),
                        'Program_Stream' => strtolower($this->stream_applied),
                        'No_of_Semesters' => $prog_info["num_of_semesters"] . " semesters",

                        'Commencement_Date' => (new \DateTime($letter_data["commencement_date"]))->format("l F j, Y"),
                        'Initial_Fees_in_Words' => $letter_data["initial_fees_in_words"],
                        'Initial_Fees_in_Figures' => $letter_data["initial_fees_in_figures"],
                        'Tel_Number_1' => $letter_data["tel_number_1"],
                        'Tel_Number_2' => $letter_data["tel_number_2"],
                        'Closing_Date' => (new \DateTime($letter_data["closing_date"]))->format("l F j, Y"),
                        'Orientation_Date' => (new \DateTime($letter_data["orientation_date"]))->format("l F j, Y"),
                        'Deadline_Date' => (new \DateTime($letter_data["deadline_date"]))->format("l F j, Y"),
                        'Registration_Fees_in_Words' => $letter_data["registration_fees_in_words"],
                        'Registration_Fees_in_Figures' => $letter_data["registration_fees_in_figures"],
                        'University_Registrar' => $letter_data["university_registrar"]
                    ]
                ];
                break;

            case 'UPGRADE':
                $letter_data = [
                    "success" => true,
                    "type" => "upgrade",
                    "period" => $admission_period,
                    "data" => [
                        'app_number' => $app_app_number["app_number"],
                        'Full_Name' => !empty($app_pers_info["middle_name"]) ? $app_pers_info["first_name"] . " " . $app_pers_info["middle_name"] .  " " .  $app_pers_info["last_name"] : $app_pers_info["first_name"] . " " . $app_pers_info["last_name"],
                        'Box_Location' => $app_pers_info["postal_town"] . " " . $app_pers_info["postal_spr"],
                        'Box_Address' => $app_pers_info["postal_addr"],
                        'Location' => $app_pers_info["postal_country"],

                        'Year_of_Admission' => $admission_period["academic_year"],

                        'Program_Length_1' => $prog_info["duration"] . "-" . $prog_info["dur_format"],
                        'Program_Offered_1' => $prog_info["name"],
                        'Program_Length_2' => $prog_info["duration"] . "-" . $prog_info["dur_format"],
                        'Program_Offered_2' => ucwords($prog_info["name"]),
                        'Program_Type' => ucwords($prog_info["form_type"]),
                        'Program_Stream' => strtolower($this->stream_applied),
                        'No_of_Semesters' => $prog_info["num_of_semesters"] . " semesters",

                        'Commencement_Date' => (new \DateTime($letter_data["commencement_date"]))->format("l F j, Y"),
                        'Initial_Fees_in_Words' => $letter_data["initial_fees_in_words"],
                        'Initial_Fees_in_Figures' => $letter_data["initial_fees_in_figures"],
                        'Tel_Number_1' => $letter_data["tel_number_1"],
                        'Tel_Number_2' => $letter_data["tel_number_2"],
                        'Closing_Date' => (new \DateTime($letter_data["closing_date"]))->format("l F j, Y"),
                        'Orientation_Date' => (new \DateTime($letter_data["orientation_date"]))->format("l F j, Y"),
                        'Deadline_Date' => (new \DateTime($letter_data["deadline_date"]))->format("l F j, Y"),
                        'Registration_Fees_in_Words' => $letter_data["registration_fees_in_words"],
                        'Registration_Fees_in_Figures' => $letter_data["registration_fees_in_figures"],
                        'University_Registrar' => $letter_data["university_registrar"]
                    ]
                ];
                break;

            default:
                $letter_data = [
                    "success" => false,
                    "message" => ["There's no letter format set for the applied program category!"]
                ];
                break;
        }
        return $letter_data;
    }
}
