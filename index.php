<?php

require_once('bootstrap.php');

use Src\Controller\USSDHandler;

switch ($_SERVER["REQUEST_METHOD"]) {
    case 'POST':
        $_POST = json_decode(file_get_contents("php://input"), true);

        $ussd = new USSDHandler($_POST);
        $response = $ussd->control();

        header("Content-Type: application/json");
        echo json_encode($response);
        break;

    default:
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: text/html");
        break;
}
