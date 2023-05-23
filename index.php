<?php
require_once('bootstrap.php');

use Src\Controller\USSDHandler;

//if ($_SERVER["REQUEST_METHOD"] != "POST") die("Invalid request!");

$sessionId      = $_POST["session_id"];     // Session ID
$phoneNumber    = $_POST["msisdn"];         // Phone number
$msgType        = $_POST["msg_type"];       // Message Type 0, 1, 2
$ussdBody       = $_POST["ussd_body"];      // response ussdBody
$networkCode    = $_POST["nw_code"];        // network code 01 > MTN, 02 > VODA, 
$serviceCode    = $_POST["service_code"];   // Service code

$ussd = new USSDHandler($sessionId, $serviceCode, $phoneNumber, $ussdBody, $networkCode, $msgType);
$ussd->requestLogger(json_encode($_GET));
$response = $ussd->control();

header("Content-Type: application/json");
echo json_encode($response);
