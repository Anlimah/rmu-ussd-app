<?php
require_once('bootstrap.php');

use Src\Controller\USSDHandler;

$response;

switch ($_SERVER["REQUEST_METHOD"]) {
    case 'POST':
        parse_str(file_get_contents("php://input"), $_POST);
        $sessionId      = $_POST["session_id"];     // Session ID
        $phoneNumber    = $_POST["msisdn"];         // Phone number
        $msgType        = $_POST["msg_type"];       // Message Type 0, 1, 2
        $ussdBody       = $_POST["ussd_body"];      // response ussdBody
        $networkCode    = $_POST["nw_code"];        // network code 01 > MTN, 02 > VODA, 
        $serviceCode    = $_POST["service_code"];   // Service code

        $ussd = new USSDHandler($sessionId, $phoneNumber, $msgType, $serviceCode, $ussdBody, $networkCode);
        $ussd->requestLogger(json_encode($_POST));
        $response = $ussd->control();

        header("Content-Type: application/json");
        echo $response;
        break;

    default:
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: text/html");
        break;
}
