<?php

namespace Src\Controller;

session_start();

use Src\Controller\ExposeDataController;
use Src\Controller\PaymentController;
use Src\System\DatabaseMethods;

class USSDHandler
{
    private $expose = null;
    private $dm = null;

    private $sessionId      = null;
    private $serviceCode    = null;
    private $phoneNumber    = null;
    private $msgType        = null;
    private $ussdBody        = null;
    private $networkCode    = null;
    private $payload        = array();

    private $selectedFormID = null;
    private $userFirstName  = null;
    private $userLastName   = null;
    private $userMoMoNum    = null;

    public function __construct($sessionId, $serviceCode, $phoneNumber, $ussdBody, $networkCode, $msgType)
    {
        $this->sessionId    = $sessionId;
        $this->serviceCode  = $serviceCode;
        $this->phoneNumber  = $phoneNumber;
        $this->msgType      = $msgType;
        $this->ussdBody      = $ussdBody;
        $this->networkCode  = $networkCode;

        $this->expose = new ExposeDataController();
        $this->dm = new DatabaseMethods();
    }

    public function control()
    {
        $this->activityLogger();
        /*if (!isset($this->sessionId) || !isset($this->serviceCode) || !isset($phoneNumber) || !isset($ussdBody) || !isset($networkCode))
            $this->ussdBody = "[01] Invalid request!";
        if (empty($this->sessionId) || empty($this->serviceCode) || empty($phoneNumber) || empty($ussdBody) || empty($networkCode))
            $this->ussdBody = "[02] Invalid request!";*/

        // Service unavailable for networks other than MTN & VODA
        if ($this->networkCode  == "03" && $this->networkCode  == "04") {
            $this->unSupportedNetworksResponse();
        }

        //
        else {
            $this->setSessionLevels();

            switch ($this->msgType) {
                case '0':
                    $user = $this->sessionId . ":" . $this->phoneNumber;
                    $_SESSION["ussd_start"] = base64_encode($user);
                    $_SESSION["level"] = array();
                    $this->mainMenuResponse();
                    break;

                case '1':

                    if (!empty($this->ussdBody)) {
                        // Set session levels
                        $this->setSessionLevels();
                    }

                    $this->continueResponse();
                    break;

                default:
                    # code...
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

        return $this->payload;
    }

    private function unSupportedNetworksResponse()
    {
        $this->ussdBody = "This service is available for only MTN and VODAFONE users. Please visit https://forms.rmuictonline.com to buy a form on all networks";
        $this->msgType = '2';
    }

    private function mainMenuResponse()
    {
        $response  = "Welcome to RMU Online Forms Purchase platform. Select a form to buy.\n";
        $allForms = $this->expose->getAvailableForms();

        foreach ($allForms as $form) {
            $response .= $form['id'] . ". " . ucwords(strtolower($form['name'])) . "\n";
        }

        $this->ussdBody = $response;
        $this->msgType = '1';
    }

    private function setSessionLevels()
    {
        if (!isset($_SESSION["level"][0])) $_SESSION["level"][0] = true;
        elseif (isset($_SESSION["level"][0]) && !$_SESSION["level"][1]) $_SESSION["level"][1] = true;
        elseif (isset($_SESSION["level"][1]) && !$_SESSION["level"][2]) $_SESSION["level"][2] = true;
        elseif (isset($_SESSION["level"][2]) && !$_SESSION["level"][3]) $_SESSION["level"][3] = true;
        elseif (isset($_SESSION["level"][3]) && !$_SESSION["level"][4]) $_SESSION["level"][4] = true;
        elseif (isset($_SESSION["level"][4]) && !$_SESSION["level"][5]) $_SESSION["level"][5] = true;
    }

    private function continueResponse()
    {
        $expose = new ExposeDataController();

        if (isset($_SESSION["level"][0]) && !$_SESSION["level"][1]) {
            $this->msgType = '1';
            $formInfo = $expose->getFormPriceA($_SESSION["level"][0]);
            $response = $formInfo[0]["name"] . " forms cost GHc " . $formInfo[0]["amount"] . ".  Enter 1 to continue.\n";
            $response .= "1. Buy";
        }
        //
        elseif (isset($_SESSION["level"][1]) && !$_SESSION["level"][2]) {
            $this->msgType = '1';
            $response = "Enter your first name.";
        }
        //
        else if (isset($_SESSION["level"][2]) && !$_SESSION["level"][3]) {
            $this->msgType = '1';
            $response = "Enter your last name.";
        }
        //
        else if (isset($_SESSION["level"][3]) && !$_SESSION["level"][4]) {
            $this->msgType = '1';
            $response = "Enter the Mobile Money number to buy the form. eg 024XXXXXXX";
        }
        //
        else if (isset($_SESSION["level"][4]) && !$_SESSION["level"][5]) {
            $this->msgType = '2';
            $phlen = strlen($_SESSION["level"][4]);
            $networks_codes = array(
                "24" => "MTN", "25" => "MTN", "53" => "MTN", "54" => "MTN", "55" => "MTN", "59" => "MTN", "20" => "VOD", "50" => "VOD",
            );
            $phone_number = "";

            if ($phlen == 9) {
                $net_code = substr($_SESSION["level"][4], 0, 2); // 555351068 /55
                $phone_number_start = 0;
            } elseif ($phlen == 10) {
                $net_code = substr($_SESSION["level"][4], 1, 2); // 0555351068 /55
                $phone_number_start = 1;
            } elseif ($phlen == 13) {
                $net_code = substr($_SESSION["level"][4], 4, 2); // +233555351068 /55
                $phone_number_start = 4;
            } elseif ($phlen == 14) {
                $net_code = substr($_SESSION["level"][4], 5, 2); //+2330555351068 /55
                $phone_number_start = 5;
            }

            $network = $networks_codes[$net_code];

            if (!$network) {
                $response = "This service is only available for MTN and VODAFONE users. To buy RMU forms with all networks, visit https://forms.rmuictonline.com";
            } else {
                $vendor_id = "1665605087";
                $phone_number = "0" . substr($_SESSION["level"][4], $phone_number_start, 9);
                $formInfo = $expose->getFormPriceA($_SESSION["level"][0]);
                $admin_period = $expose->getCurrentAdmissionPeriodID();

                $data = array(
                    "first_name" => $_SESSION["level"][2],
                    "last_name" => $_SESSION["level"][3],
                    "email_address" => "",
                    "country_name" => "Ghana",
                    "country_code" => '+233',
                    "phone_number" => $phone_number,
                    "form_id" => $_SESSION["level"][0],
                    "pay_method" => "USSD",
                    "network" => $network,
                    "amount" => $formInfo[0]["amount"],
                    "vendor_id" => $vendor_id,
                    "admin_period" => $admin_period
                );

                $pay = new PaymentController();
                $result = $pay->orchardPaymentControllerB($data);

                if (!$result["success"]) {
                    $response = "Process failed! {$result["status"]} {$result["message"]}";
                } else {
                    $response = "Thank you! Payment prompt will be sent to {$_SESSION["level"][4]} shortly.";
                }
            }
        } else {
            $this->msgType = '2';
            $response = "Sorry, the input is not valid.";
        }

        $this->ussdBody = $response;
    }

    private function validateIntInputs($input)
    {
        if (empty($input)) return false;
        $user_input = htmlentities(htmlspecialchars($input));
        $validated_input = (bool) preg_match('/^[0-9]/', $user_input);
        if ($validated_input) return $user_input;
        return false;
    }

    private function validateTextInputs($input)
    {
        if (empty($input)) return false;
        $user_input = htmlentities(htmlspecialchars($input));
        $validated_input = (bool) preg_match('/^[A-Za-z-]/', $user_input);
        if ($validated_input) return $user_input;
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
}
