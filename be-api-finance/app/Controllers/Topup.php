<?php

namespace App\Controllers;

class Topup extends BaseController
{
    public function index()
    {
        echo('welcome!');
    }

    public function postList()
    {   
        $request = request();
        $db = db_connect();
        $dataPost = $request->getPost();
        $dataRequest = cek_token_login($dataPost);
        $id_user = $db->table('bestpva.app_users')->where('token_login', $dataPost['token_login'])->limit(1)->get()->getRow()->id_user;
        $q = 'SELECT 
        bftu.*,
        bau.username,
        bau.email,
        bbpm.code,
        bbpm.name,
        bbc.currency_symbol,
        bbc.currency_code,
        bbc.currency_name
        from bestpva_finance.topup_users bftu
        left join bestpva.app_users bau on bau.id_user = bftu.id_user
        left join bestpva.base_payment_methods bbpm on bbpm.id = bftu.id_base_payment_method
        left join bestpva.base_currencies bbc on bbc.id = bftu.id_currency 
        where bftu.id_user = '.$id_user.'
        order by bftu.id desc;
        ';
        $query = $db->query($q);
        $dataFinal = $query->getResult();
        $db->close();
        $finalData = json_encode($dataFinal);
        echo '{
            "code": 0,
            "error": "",
            "message": "",
            "data": '.$finalData.'
        }';
    }

    public function postCreate()
    {   
        $request = request();
        $dataPost = $request->getPost();
        $dataRequest = cek_token_login($dataPost);
        $db = db_connect();
        $builder = $db->table('bestpva_finance.topup_users');
        $query = $builder->ignore()->insert($dataRequest);
        $dataFinal = $builder->getResult();
        $db->close();
        $finalData = json_encode($dataFinal);
        echo '{
            "code": 0,
            "error": "",
            "message": "",
            "data": '.$finalData.'
        }';
    }


    public function generate_random_string()
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 32; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function postCreate_bonus_topup()
    {
        $request = request();
        $db = db_connect();
        $dataPost = $request->getPost();
        $dataRequest = cek_token_login($dataPost);

        $exp = date("Y-m-d H:i:s");
        $insert['id_currency'] = 2;
        $insert['amount'] = $dataRequest['amount'];
        $insert['id_base_payment_method'] = 5;
        $insert['id_user'] = $dataRequest['id_user'];
        $insert['expired_date'] = $exp;
        $insert['status'] = 'success';
        $insert['invoice_number '] = 'INV/TOPUP/BONUS/'.$dataRequest['id_user'].'/'.date('Y').'/'.date('m').'/'.date('s');

        $builder = $db->table('bestpva_finance.topup_users');
        $query = $builder->ignore()->insert($insert);

        $db->close();

        echo '{
            "code": 0,
            "error": "",
            "message": "",
            "data": "'.$dataRequest['amount'].'"
        }';


        // $responseBody = wp_remote_retrieve_body($response);
        // error_log("binance response " . $responseBody);
        // return json_decode($responseBody, true);
    }

    public function postCreate_binance_order()
    {
        $request = request();
        $db = db_connect();
        $dataPost = $request->getPost();
        $dataRequest = cek_token_login($dataPost);
        $id_user = $db->table('bestpva.app_users')->where('token_login', $dataPost['token_login'])->limit(1)->get()->getRow()->id_user;
        $postData['invoice_number'] = 'INV/TOPUP/BINANCE/'.$id_user.'/'.date('Y').'/'.date('m').'/'.date('s');
        $order_id = md5($postData['invoice_number']);
        // $order_id = '7e3c5a48b6f04dd5a682b7a9aaff8d8f';
        // $order = new WC_Order($order_id);
        $req = array(
            'env' => array('terminalType' => 'WEB'),
            'merchantTradeNo' => $order_id,
            'orderAmount' => $dataRequest['amount'],
            'currency' => 'BUSD');
        $req['goods'] = array();
        $req['passThroughInfo'] = "wooCommerce-1.0";
        $req['goods']['goodsType'] = "02";
        $req['goods']['goodsCategory'] = "Z000";
        $req['goods']['referenceGoodsId'] = '1';
        $req['goods']['goodsName'] = 'BestPVA';
        // $req['returnUrl'] = $this->get_return_url($order);
        // $req['cancelUrl'] = $order->get_cancel_order_url();
        // $req['webhookUrl'] = esc_url(home_url('/')) . '?wc-api=wc_gateway_binance';
        $nonce = $this->generate_random_string();
        $body = json_encode($req);
        $timestamp = round(microtime(true) * 1000);
        $payload = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
        $secretKey = getenv('BINANCE_SEKRET_KEY');
        $signature = strtoupper(hash_hmac('sha512', $payload, $secretKey));
        $apiKey = getenv('BINANCE_API_KEY');

        $headers = array(
            'Content-Type: application/json',
            "BinancePay-Timestamp: ".$timestamp,
            "BinancePay-Nonce: ".$nonce,
            "BinancePay-Certificate-SN: ".$apiKey,
            "BinancePay-Signature: ".$signature
        );
        $response = json_decode(curl(getenv('BINANCE_PAY'), 1, json_encode($req), $headers));

        $exchange_rate = $db->table('bestpva.setting_exchange_rate')->where('id_base_currency_from', 1)->where('id_base_currency_to', 2)->limit(1)->get()->getRow()->exchange_rate;
        $amount = $exchange_rate * $dataRequest['amount'];
        // $dt = new DateTime("@$response->data->expireTime");
        $exp = gmdate("Y-m-d H:i:s", ($response->data->expireTime/1000)+(7*3600));
        $insert['id_currency'] = 2;
        $insert['amount'] = $amount;
        $insert['id_base_payment_method'] = 1;
        $insert['id_user'] = $id_user;
        $insert['expired_date'] = $exp;
        $insert['invoice_number '] = $postData['invoice_number'];

        $builder = $db->table('bestpva_finance.topup_users');
        $query = $builder->ignore()->insert($insert);
        
        $q = 'SELECT 
        bftu.*,
        bau.username,
        bau.email,
        bbpm.code,
        bbpm.name,
        bbc.currency_symbol,
        bbc.currency_code,
        bbc.currency_name
        from bestpva_finance.topup_users bftu
        left join bestpva.app_users bau on bau.id_user = bftu.id_user
        left join bestpva.base_payment_methods bbpm on bbpm.id = bftu.id_base_payment_method
        left join bestpva.base_currencies bbc on bbc.id = bftu.id_currency 
        where bftu.id_user = '.$id_user.'
        order by bftu.id desc;
        ';
        $query = $db->query($q);
        $dataFinal = $query->getResult();
        $db->close();
        $finalData = json_encode($dataFinal);

        $db->close();

        echo '{
            "code": 0,
            "error": "",
            "message": "",
            "data": '.$finalData.',
            "url": "'.str_ireplace(".com", ".me", $response->data->universalUrl).'"
        }';


        // $responseBody = wp_remote_retrieve_body($response);
        // error_log("binance response " . $responseBody);
        // return json_decode($responseBody, true);
    }

    public function postCheck_binance_order()
    {
        $request = request();
        $db = db_connect();
        $dataPost = $request->getPost();
        $dataRequest = cek_token_login($dataPost);
        $id_user = $db->table('bestpva.app_users')->where('token_login', $dataPost['token_login'])->limit(1)->get()->getRow()->id_user;
        $pendingPayments = $db->table('bestpva_finance.topup_users')->where('status', 'Waiting for Payment')->where('id_base_payment_method ', '1')->get()->getResult();
        // $postData['invoice_number'] = 'INV/TOPUP/'.$id_user.'/'.date('Y').'/'.date('m').'/'.date('s');
        foreach ($pendingPayments as $val) {
            $order_id = md5($val->invoice_number);
            
            $req['merchantTradeNo'] = $order_id;
    
            $nonce = $this->generate_random_string();
            $body = json_encode($req);
            $timestamp = round(microtime(true) * 1000);
            $payload = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
            $secretKey = getenv('BINANCE_SEKRET_KEY');
            $signature = strtoupper(hash_hmac('sha512', $payload, $secretKey));
            $apiKey = getenv('BINANCE_API_KEY');
    
            $headers = array(
                'Content-Type: application/json',
                "BinancePay-Timestamp: ".$timestamp,
                "BinancePay-Nonce: ".$nonce,
                "BinancePay-Certificate-SN: ".$apiKey,
                "BinancePay-Signature: ".$signature
            );
            // $response = json_decode(curl(getenv('BINANCE_PAY'), 1, json_encode($req), $headers));
            $response = json_decode(curl(getenv('BINANCE_PAY').'/query', 1, json_encode($req), $headers));
            print_r($response);

            if ($response->data->status == 'EXPIRED' || $response->data->status == 'PAID' || $response->data->status == 'CANCELED'){
                $status = 'Expired';
                if ($response->data->status == 'PAID') {
                    $status = 'success';
                } elseif ($response->data->status == 'CANCELED') {
                    $status = 'Canceled';
                }
                $update['status'] = $status;
                $db->table('bestpva_finance.topup_users')->where('status', 'Waiting for Payment')->where('invoice_number', $val->invoice_number)->ignore()->update($update);
                // echo $val->invoice_number . ' -> ';
                // echo "\n";
            }
            // print_r($response);
            // echo "\n";
            // echo "\n";
        }

        // $exchange_rate = $db->table('bestpva.setting_exchange_rate')->where('id_base_currency_from', 1)->where('id_base_currency_to', 2)->limit(1)->get()->getRow()->exchange_rate;
        // $amount = $exchange_rate * $dataRequest['amount'];
        // $insert['id_currency'] = 2;
        // $insert['amount'] = $amount;
        // $insert['id_base_payment_method'] = 1;
        // $insert['id_user'] = $id_user;
        // $insert['invoice_number '] = $postData['invoice_number'];

        // $builder = $db->table('bestpva_finance.topup_users');
        // $query = $builder->ignore()->insert($insert);
        
        // $q = 'SELECT 
        // bftu.*,
        // bau.username,
        // bau.email,
        // bbpm.code,
        // bbpm.name,
        // bbc.currency_symbol,
        // bbc.currency_code,
        // bbc.currency_name
        // from bestpva_finance.topup_users bftu
        // left join bestpva.app_users bau on bau.id_user = bftu.id_user
        // left join bestpva.base_payment_methods bbpm on bbpm.id = bftu.id_base_payment_method
        // left join bestpva.base_currencies bbc on bbc.id = bftu.id_currency 
        // where bftu.id_user = '.$id_user.'
        // order by bftu.id desc;
        // ';
        // $query = $db->query($q);
        // $dataFinal = $query->getResult();
        // $db->close();
        // $finalData = json_encode($dataFinal);

        // $db->close();

        // echo '{
        //     "code": 0,
        //     "error": "",
        //     "message": "",
        //     "data": '.$finalData.',
        //     "url": "'.$response->data->universalUrl.'"
        // }';


        // $responseBody = wp_remote_retrieve_body($response);
        // error_log("binance response " . $responseBody);
        // return json_decode($responseBody, true);
    }
}
