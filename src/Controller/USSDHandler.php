<?php

namespace Src\Controller;

use Src\Controller\ExposeDataController;
use Src\System\DatabaseMethods;

class USSDHandler
{
    private $expose         = null;
    private $dm             = null;

    private $sessionId      = null;
    private $serviceCode    = null;
    private $phoneNumber    = null;
    private $msgType        = null;
    private $ussdBody       = null;
    private $networkCode    = null;

    private $payload        = array();
    private $payData        = array();

    public function __construct($data)
    {
        $this->sessionId    = $data["session_id"];
        $this->phoneNumber  = $data["msisdn"];
        $this->msgType      = $data["msg_type"];
        $this->serviceCode  = $data["service_code"];
        $this->ussdBody     = $data["ussd_body"];
        $this->networkCode  = $data["nw_code"];

        $this->expose = new ExposeDataController();
        $this->dm = new DatabaseMethods();

        $this->activityLogger();
    }

    public function run()
    {
        if (!isset($this->sessionId) || !isset($this->phoneNumber) || !isset($this->ussdBody) || !isset($this->networkCode)) {
            $this->ussdBody = "[01] Sorry, unable to process request at this time!";
            $this->msgType = "2";
        } else if (empty($this->sessionId) || empty($this->phoneNumber) || empty($this->ussdBody) || empty($this->networkCode)) {
            $this->ussdBody = "[02] Sorry, unable to process request at this time!";
            $this->msgType = "2";
        } else if ($this->networkCode  == "03" || $this->networkCode  == "04") {
            $this->unSupportedNetworksResponse();
        } else {

            switch ($this->msgType) {
                case '0':
                    $this->mainMenuResponse();
                    break;

                case '1':
                    $this->continueResponse();
                    break;

                default:
                    $this->ussdBody = "Sorry, your request couldn't be processed!";
                    $this->msgType = '2';
                    break;
            }
        }

        $this->payload = array(
            "session_id" => $this->sessionId,
            "service_code" => $this->serviceCode,
            "msisdn" => $this->phoneNumber,
            "msg_type" => $this->msgType,
            "ussd_body" => $this->ussdBody,
            "nw_code" => $this->networkCode,
        );

        if (!empty($this->payData)) $this->payload["data"] = $this->payData;

        if ($this->msgType == "2" || $this->msgType == "3") $this->removeUserSessionLogs();
        return $this->payload;
    }

    private function unSupportedNetworksResponse()
    {
        $this->ussdBody = "Sorry, network not supported! Please visit https://forms.rmuictonline.com to buy a form on all networks";
        $this->msgType = '2';
    }

    private function mainMenuResponse()
    {
        $response  = "RMU Forms Online - Select a form to buy.\n\n";
        $allForms = $this->expose->getAvailableForms();
        foreach ($allForms as $form) {
            $response .= $form['id'] . ". " . ucwords(strtolower($form['name'])) . "\n";
        }

        $this->ussdBody = $response;
        $this->msgType = "1";
    }

    private function continueResponse()
    {
        $msgType = '1';
        $response = "";

        $text = $this->arrangeTextOrder();

        $level = explode("*", $text);

        if (isset($level[0]) && !empty($level[0]) && !isset($level[1])) {
            if ($this->validateSelectedFormOption($level[0])) {
                $formInfo = $this->expose->getFormPriceA($level[0]);
                $response = $formInfo[0]["name"] . " forms cost GHc" . $formInfo[0]["amount"] . ".  Enter 1 to buy.\n";
                $response .= "1. Buy";
                $msgType = '1';
            } else {
                $response = "Sorry you've entered an invalid option.";
                $msgType = '2';
            }
        }
        //
        else if (isset($level[1]) && !empty($level[1]) && !isset($level[2])) {
            if ($this->validateIntegerInputs($level[1])) {
                $response = "Enter Mobile Money number to buy. eg 024XXXXXXX";
                $msgType = '1';
            } else {
                $response = "Sorry you've entered an invalid input.";
                $msgType = '2';
            }
        }
        //
        else if (isset($level[2]) && !empty($level[2])) {
            if ($this->validateIntegerInputs($level[1])) {
                $phlen = strlen($level[2]);
                $networks_codes = array(
                    "24" => "MTN", "25" => "MTN", "53" => "MTN", "54" => "MTN", "55" => "MTN", "59" => "MTN", "20" => "VOD", "50" => "VOD",
                );
                $phone_number = "";

                if ($phlen == 9) {
                    $net_code = substr($level[2], 0, 2); // 555351068 /55
                    $phone_number_start = 0;
                } elseif ($phlen == 10) {
                    $net_code = substr($level[2], 1, 2); // 0555351068 /55
                    $phone_number_start = 1;
                } elseif ($phlen == 13) {
                    $net_code = substr($level[2], 4, 2); // +233555351068 /55
                    $phone_number_start = 4;
                } elseif ($phlen == 14) {
                    $net_code = substr($level[2], 5, 2); //+2330555351068 /55
                    $phone_number_start = 5;
                }

                $network = $networks_codes[$net_code];

                if (!$network) {
                    $response = "Sorry, network not supported. Visit https://forms.rmuictonline.com, to buy RMU forms with all networks";
                } else {
                    $vendor_id = "1665605087";
                    $phone_number = "0" . substr($level[2], $phone_number_start, 9);
                    $formInfo = $this->expose->getFormPriceA($level[0]);
                    $admin_period = $this->expose->getCurrentAdmissionPeriodID();

                    $data = array(
                        "first_name" => "USSD",
                        "last_name" => $this->phoneNumber,
                        "email_address" => "",
                        "country_name" => "Ghana",
                        "country_code" => '+233',
                        "phone_number" => $phone_number,
                        "form_id" => $level[0],
                        "pay_method" => "USSD",
                        "network" => $network,
                        "amount" => $formInfo[0]["amount"],
                        "vendor_id" => $vendor_id,
                        "admin_period" => $admin_period
                    );

                    $this->payData = $data;
                    $response = "Thank you! Payment prompt will be sent to {$level[2]} shortly.";
                }
            } else {
                $response = "Sorry you've entered an invalid phone number.";
            }
            $msgType = '2';
        } else {
            $response = "Sorry, unable to process your request.";
            $msgType = '2';
        }

        $this->ussdBody = $response;
        $this->msgType = $msgType;
    }

    private function validateSelectedFormOption($input)
    {
        $userSelectedForm = (int) $this->validateSelectedOption($input);
        if ($userSelectedForm > 0 && $userSelectedForm <= count($this->expose->getAvailableForms())) return true;
        return false;
    }

    private function validateSelectedOption($input)
    {
        if (empty($input)) return false;
        $user_input = htmlentities(htmlspecialchars($input));
        $validated_input = (bool) preg_match('/^[1-9]/', $user_input);
        if ($validated_input) return $user_input;
        return false;
    }

    private function validateIntegerInputs($input)
    {
        if (empty($input)) return false;
        $user_input = htmlentities(htmlspecialchars($input));
        $validated_input = (bool) preg_match('/^[0-9]/', $user_input);
        if ($validated_input) return true;
        return false;
    }

    public function activityLogger()
    {
        $query = "INSERT INTO `ussd_activity_logs` (`session_id`, `service_code`, `msisdn`, `msg_type`, `ussd_body`, `nw_code`) 
                    VALUES(:si, :sc, :ms, :mt, :ub, :nc)";
        $params = array(
            ":si" => $this->sessionId, ":sc" => $this->serviceCode, ":ms" => $this->phoneNumber,
            ":mt" => $this->msgType, ":ub" => $this->ussdBody, ":nc" => $this->networkCode
        );
        $this->dm->inputData($query, $params);
    }

    private function fetchSessionLogs()
    {
        $query = "SELECT * FROM `ussd_activity_logs` WHERE `session_id`=:s AND `msisdn`=:p AND `msg_type` <> 0 ORDER BY `timestamp` ASC";
        $params = array(":s" => $this->sessionId, ":p" => $this->phoneNumber);
        return $this->dm->inputData($query, $params);
    }

    private function arrangeTextOrder()
    {
        $text = "";
        $requestSessions = $this->fetchSessionLogs();
        if (!empty($requestSessions)) {
            $i = 0;
            foreach ($requestSessions as $rSession) {
                if ($i == 0) $text .= $rSession["ussd_body"];
                else $text .= "*" . $rSession["ussd_body"];
                $i += 1;
            }
        }
        return $text;
    }

    private function removeUserSessionLogs()
    {
        $query = "DELETE FROM `ussd_activity_logs` WHERE `session_id`=:s AND `msisdn`=:p";
        $params = array(":s" => $this->sessionId, ":p" => $this->phoneNumber);
        $this->dm->inputData($query, $params);
    }
}
