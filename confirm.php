<?php

require_once('bootstrap.php');

use Src\Controller\ExposeDataController;

switch ($_SERVER["REQUEST_METHOD"]) {
    case 'POST':
        $_POST = json_decode(file_get_contents("php://input"), true);
        $response = array();

        $expose = new ExposeDataController();
        $expose->requestLogger(json_encode($_POST));

        if (!empty($_POST)) {
            $transaction_id = $expose->validatePhone($_POST["trans_ref"]);
            $data = $expose->confirmPurchase($transaction_id);
            $expose->requestLogger($transaction_id . " - " . $data[0]["message"]);
        }

        break;

    default:
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: text/html");
        break;
}
