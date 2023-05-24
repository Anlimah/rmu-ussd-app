<?php
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    parse_str(file_get_contents("php://input"), $_POST);
    echo json_encode($_POST);
}
