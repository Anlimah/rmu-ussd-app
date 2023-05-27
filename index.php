<?php

require_once('bootstrap.php');

use Src\Controller\USSDHandler;

switch ($_SERVER["REQUEST_METHOD"]) {
    case 'POST':
        $_POST = json_decode(file_get_contents("php://input"), true);
        $response = array();
        if (!empty($_POST)) $response = (new USSDHandler($_POST))->run();

        header("Content-Type: application/json");
        echo json_encode($response);
        break;

    default:
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: text/html");
        break;
}
