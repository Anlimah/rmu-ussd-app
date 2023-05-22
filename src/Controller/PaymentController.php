<?php

namespace Src\Controller;

use Src\Gateway\OrchardPaymentGateway;
use Src\Controller\VoucherPurchase;

class PaymentController
{
    private $voucher;

    public function __construct()
    {
        $this->voucher = new VoucherPurchase();
    }

    public function vendorPaymentProcess($data)
    {
        if (!empty($data)) {
            $trans_id = time();
            if ($trans_id) {
                return $this->voucher->SaveFormPurchaseData($data, $trans_id);
            } else {
                return array("success" => false, "message" => "Transaction ID failed!");
            }
        }
    }

    public function verifyVendorPurchase(int $vendor_id, int $transaction_id)
    {
    }

    /**
     * @param int transaction_id //transaction_id
     */
    private function getTransactionStatusFromOrchard(int $transaction_id)
    {
        $service_id = getenv('ORCHARD_SERVID');

        $payload = json_encode(array(
            "exttrid" => $transaction_id,
            "trans_type" => "TSC",
            "service_id" => $service_id
        ));

        $client_id = getenv('ORCHARD_CLIENT');
        $client_secret = getenv('ORCHARD_SECRET');
        $signature = hash_hmac("sha256", $payload, $client_secret);

        $secretKey = $client_id . ":" . $signature;
        $payUrl = "https://orchard-api.anmgw.com/checkTransaction";
        $request_verb = 'POST';
        try {
            $pay = new OrchardPaymentGateway($secretKey, $payUrl, $request_verb, $payload);
            return $pay->initiatePayment();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function processTransaction(int $transaction_id)
    {
        // Fetch transaction ID AND STATUS from DB
        $data = $this->voucher->getTransactionStatusFromDB($transaction_id);

        if (empty($data)) return array("success" => false, "message" => "Invalid transaction ID! Code: -1");

        if (strtoupper($data[0]["status"]) != "PENDING") return array("success" => false, "message" => "Transaction already performed! Check mail and/or SMS inbox for login details. Code: 1");

        $response = json_decode($this->getTransactionStatusFromOrchard($transaction_id));

        if (empty($response)) return array("success" => false, "message" => "Invalid transaction Parameters! Code: -2");

        if (isset($response->trans_status)) {
            $status_code = substr($response->trans_status, 0, 3);
            if ($status_code == '000') return $this->voucher->genLoginsAndSend($transaction_id);
            $this->voucher->updateTransactionStatusInDB('FAILED', $transaction_id);
            return array("success" => false, "message" => "Payment failed! Code: " . $status_code);
        } elseif (isset($response->resp_code)) {
            if ($response->resp_code == '084') return array(
                "success" => false,
                "message" => "Payment pending! This might be due to insufficient fund in your mobile wallet or your payment session expired. Code: " . $response->resp_code
            );
            return array("success" => false, "message" => "Payment process failed! Code: " . $response->resp_code);
        }
        return array("success" => false, "message" => "Bad request: Payment process failed!");
    }

    public function orchardPaymentController($data)
    {
        if (!empty($data)) {

            $callback_url = "https://forms.rmuictonline.com/buy-online/confirm.php";
            $landing_page = "https://forms.rmuictonline.com/buy-online/confirm.php";
            $trans_id = time();
            $service_id = getenv('ORCHARD_SERVID');

            $payload = json_encode(array(
                "amount" => $data["amount"],
                "callback_url" => $callback_url,
                "exttrid" => $trans_id,
                "reference" => "RMU Forms Online",
                "service_id" => $service_id,
                "trans_type" => "CTM",
                "nickname" => "RMU",
                "landing_page" => $landing_page,
                "ts" => date("Y-m-d H:i:s"),
                "payment_mode" => $data["pay_method"],
                "currency_code" => "GHS",
                "currency_val" => $data["amount"]
            ));

            $client_id = getenv('ORCHARD_CLIENT');
            $client_secret = getenv('ORCHARD_SECRET');
            $signature = hash_hmac("sha256", $payload, $client_secret);

            $secretKey = $client_id . ":" . $signature;
            $request_verb = 'POST';
            $payUrl = "https://payments.anmgw.com/third_party_request";

            $pay = new OrchardPaymentGateway($secretKey, $payUrl, $request_verb, $payload);
            $response = json_decode($pay->initiatePayment());

            if ($response->resp_code == "000" && $response->resp_desc == "Passed") {
                //save Data to database
                $saved = $this->voucher->SaveFormPurchaseData($data, $trans_id);
                //if (!$saved["success"]) return array("success" => false, "message" => "Failed saving customer data");
                if (!$saved["success"]) return $saved;
                return array("success" => true, "status" => $response->resp_code, "message" => $response->redirect_url);
            }
            //echo $response->resp_desc;
            return array("success" => false, "status" => $response->resp_code, "message" => $response->resp_desc);
        }
    }

    public function orchardPaymentControllerB($data)
    {
        if (!empty($data)) {

            $trans_id = time();
            $service_id = getenv('ORCHARD_SERVID');

            $callback_url = "https://forms.rmuictonline.com/buy-online/confirm.php";
            $payload = json_encode(array(
                "amount" => $data["amount"],
                "callback_url" => $callback_url,
                "customer_number" => $data["phone_number"],
                "exttrid" => $trans_id,
                "nw" => $data["network"],
                "reference" => "RMU Forms Online",
                "service_id" => $service_id,
                "trans_type" => "CTM",
                "ts" => date("Y-m-d H:i:s")
            ));

            $client_id = getenv('ORCHARD_CLIENT');
            $client_secret = getenv('ORCHARD_SECRET');
            $signature = hash_hmac("sha256", $payload, $client_secret);

            $secretKey = $client_id . ":" . $signature;
            $request_verb = 'POST';
            $payUrl = "https://payments.anmgw.com/sendRequest";

            $pay = new OrchardPaymentGateway($secretKey, $payUrl, $request_verb, $payload);
            $response = json_decode($pay->initiatePayment());

            if ($response->resp_code == "000" || $response->resp_code == "015") {
                //save Data to database
                $saved = $this->voucher->SaveFormPurchaseData($data, $trans_id);
                return $saved;
            }

            //echo $response->resp_desc;
            return array("success" => false, "status" => $response->resp_code, "message" => $response->resp_desc);
        }
    }
}
