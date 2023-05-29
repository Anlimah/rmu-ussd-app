<?php

require_once('bootstrap.php');

use Src\Controller\ExposeDataController;

switch ($_SERVER["REQUEST_METHOD"]) {
    case 'POST':
        $_POST = json_decode(file_get_contents("php://input"), true);
        $response = array();

        $expose = new ExposeDataController();
        $expose->requestLogger(json_encode($_POST));
        $expose->requestLogger("FROm post");

        if (!empty($_POST)) {
            $transaction_id = $expose->validatePhone($_POST["trans_id"]);
            $data = $expose->confirmPurchase($transaction_id);
        }

        break;

    case 'GET':
        $_GET["getter"] = "From get part";
        $expose->requestLogger(json_encode($_GET));
        $expose->requestLogger("FROm get");
        break;

    default:
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: text/html");
        break;
}
