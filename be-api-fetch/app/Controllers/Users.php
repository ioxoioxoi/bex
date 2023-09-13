<?php

namespace App\Controllers;
use Config\Services;
use CodeIgniter\Files\File;

class Users extends BaseController
{
    public function index()
    {
        echo('welcome!');
    }

    public function postLogout()
    {   
        $session = session();
        $session->remove('login');
        $session->remove('token_login');
    }

    public function get_client_ip() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }

    public function postLogin()
    {   
        $request = request();
        $response = response();
        $db = db_connect();
        $json = $request->getJSON();
        $email = $json->email;
        $user_role = $json->user_role;
        $password = hash('sha256', $json->password);
        $builder = $db->table('app_users')->where('email', $email)->where('password', $password)->where('user_role', $user_role)->where('user_status', 'ACTIVE')->where('is_active', 1);
        $query   = $builder->get();
        $dataFinal = $query->getRow();
        
        if ($dataFinal) {
            $session = session();
            
            $loc = json_decode(curl(getenv('API_LOGS').'logs/create_log_login', 1, 'ip='.$this->get_client_ip()));
            $update["token_login"] = hash('sha256', $email.date('YmdHis'));
            $update["last_ip_address"] = $this->get_client_ip();
            if (isset($loc['city'])) {
                $update["last_ip_location"] = $loc['city'].', '.$loc['region_name'].', '.$loc['country_name'];
            }
            $session->set('login', $dataFinal);
            $session->set('token_login', $update["token_login"]);
            $builder->where('email', $email);
            $builder->update($update);
            $builder->where('email', $email);
            $query   = $builder->get();
            $dataFinal2 = $query->getRow();
            
            $builder3 = $db->table('app_operators');
            $builder3->select('app_operators.*, base_countries.country');
            $builder3->join('base_countries', 'base_countries.id = app_operators.id_country', 'left');
            $builder3->where('app_operators.operator_name <> \'\'');
            $dataFinal3 = $builder3->get()->getResult();
            
            $builder4 = $db->table('setting_banner')->where('is_active', '1')->orderBy('id', 'desc');
            $query4   = $builder4->get();
            $dataFinal4 = $query4->getResult();

            if ($user_role === 1) {
                $api_key = $db->table('token_api')->orderBy('id', 'DESC')->limit(1)->get()->getRow()->api_key;
                $dataFinal2->api_key = $api_key;
            }

            $finalData = json_encode($dataFinal2);
            $finalData3 = json_encode($dataFinal3);
            $finalData4 = json_encode($dataFinal4);
            echo '{
                "code": 0,
                "error": "",
                "message": "Login successful.",
                "data": '.$finalData.',
                "operators": '.$finalData3.',
                "banner_list": '.$finalData4.'
            }';
            curl(getenv('API_LOGS').'logs/create_log_login', 1, 'id_user='.$dataFinal2->id_user.'&ip_address='.$this->get_client_ip().'&user_role='.$dataFinal2->user_role.'&token_login='.$update["token_login"].'&token_api='.$dataFinal2->token_api);
            // echo curl(getenv('API_TRANSACTIONS').'auth/get_token', 1, 'token_login='.$update["token_login"].'&login='.$finalData);
        } else {
            echo '{
                "code": 1,
                "error": "Email or Password is incorrect!",
                "message": "Email or Password is incorrect!",
                "data": null
            }';
        }
        $db->close();
    }

    public function postChange_password()
    {   
        $request = request();
        $dataPost = $request->getPost();
        $dataRequest = cek_token_login($dataPost);
        $password = hash('sha256', $dataRequest['password']);
        $update["password"] = $password;
        $db = db_connect();
        $db->table('app_users')->where('id_user', $dataRequest['id'])->update($update);
        $db->close();
        echo '{
            "code": 0,
            "error": "",
            "message": "",
            "data": "'.$update["password"].'"
        }';
    }

    public function postChange_password_user()
    {   
        $request = request();
        $db = db_connect();
        $dataPost = $request->getPost();
        $dataRequest = cek_token_login($dataPost);
        $id_user = $db->table('bestpva.app_users')->where('token_login', $dataPost['token_login'])->limit(1)->get()->getRow()->id_user;
        $password = hash('sha256', $dataRequest['password']);
        $update["password"] = $password;
        $db->table('app_users')->where('id_user', $id_user)->update($update);
        $db->close();
        echo '{
            "code": 0,
            "error": "",
            "message": "",
            "data": "'.$update["password"].'"
        }';
    }

    public function postGenerate_api_key()
    {   
        $request = request();
        $db = db_connect();
        $dataPost = $request->getPost();
        $dataRequest = cek_token_login($dataPost);
        $id_user = $db->table('bestpva.app_users')->where('token_login', $dataPost['token_login'])->limit(1)->get()->getRow()->id_user;
        $update["token_api"] = hash('sha256', $dataPost['token_login'].date('YmdHis'));
        $db->table('app_users')->where('id_user', $id_user)->update($update);
        $data = json_encode($db->table('app_users')->where('id_user', $id_user)->get()->getRow());
        $db->close();
        echo '{
            "code": 0,
            "error": "",
            "message": "",
            "data": '.$data.',
            "api_key": "'.$update["token_api"].'"
        }';
    }

    public function postIs_exist()
    {   
        $request = request();
        $db = db_connect();
        $json = $request->getJSON();
        $type = $json->type;
        $value = $json->value;
        $user_role = 2;
        $dataFinal = $db->table('app_users')->where($type, $value)->get()->getRow();
        
        if ($dataFinal) {
            echo '{
                "code": 1,
                "error": "'.$type.' as '.$value.' has been registered!",
                "message": "'.$type.' as '.$value.' has been registered!",
                "data": null
            }';
        } else {
            echo '{
                "code": 0,
                "error": "",
                "message": "Successful.",
                "data": null
            }';
        }
        $db->close();
    }

    public function postRegister_user()
    {   
        $request = request();
        $response = response();
        $db = db_connect();
        $json = $request->getJSON();
        $insert['email'] = $json->email;
        $insert['username'] = $json->username;
        $insert['password'] = hash('sha256', $json->password);
        $insert["token_login"] = hash('sha256', $insert['email'].date('YmdHis'));
        
        $builder = $db->table('app_users');
        $builder->ignore(true)->insert($insert);
        $builder = $db->table('app_users')->where('email', $insert['email'])->where('password', $insert['password'])->where('user_status', 'ACTIVE')->where('is_active', 1);
        $query   = $builder->get();
        $dataFinal = $query->getRow();
        
        if ($dataFinal) {
            if ($dataFinal->user_role === 1) {
                $api_key = $db->table('token_api')->orderBy('id', 'DESC')->limit(1)->get()->getRow()->api_key;
                $dataFinal->api_key = $api_key;
            }

            $finalData = json_encode($dataFinal);
            echo '{
                "code": 0,
                "error": "",
                "message": "Register successful.",
                "data": '.$finalData.'
            }';
            curl(getenv('API_LOGS').'logs/create_log_login', 1, 'id_user='.$dataFinal->id_user.'&user_role='.$dataFinal->user_role.'&token_login='.$insert["token_login"].'&token_api='.$dataFinal->token_api);
            // echo curl(getenv('API_TRANSACTIONS').'auth/get_token', 1, 'token_login='.$update["token_login"].'&login='.$finalData);
        }
        $db->close();
    }

    public function postGet_token_api()
    {   
        $request = request();
        $dataPost = $request->getPost();
        $dataRequest = cek_token_login($dataPost);
        $db = db_connect();
        $json = $request->getJSON();
        $email = $json->email;
        $token_login = $json->token_login;
        $type = $json->type;
        $update["token_api"] = hash('sha256', $email.$token_login.date('YmdHis'));
        $builder = $db->table('app_users')->where('email', $email)->where('token_login', $token_login)->where('user_role', $type);
        $builder->update($update);
        $db->close();
        echo '{
            "code": 0,
            "error": "",
            "message": "",
            "data": '.$update["token_api"].'
        }';
    }

    public function postGet_user_data_from_token_api()
    {   
        $request = request();
        $dataPost = $request->getPost();
        $dataRequest = cek_token_login($dataPost);
        $request = request();
        $db = db_connect();
        $json = $request->getJSON();
        $token_api = $json->token_api;
        $builder = $db->table('app_users')->where('token_api', $token_api);
        $dataFinal = $builder->get()->getResult();
        $db->close();
        echo '{
            "code": 0,
            "error": "",
            "message": "",
            "data": '.$dataFinal[0].'
        }';
    }

    public function postCreate_captcha()
    {
        $request = request();
        $dataPost = $request->getPost();
        $dataRequest = cek_token_login($dataPost);
        $rand = create_random_captcha();
        $db = db_connect();
        $insert = [];
        $insert['captcha_code'] = $rand;
        $insert['ip_address'] = getUserIP();
        $builder = $db->table('setting_captcha');
        $builder->ignore(true)->insert($insert);
        $sig = hash_hmac('sha256', $rand, getenv('SECRET_KEY'));
        $db->close();
        echo '{
            "code": 0,
            "error": "",
            "message": "",
            "data": '.$sig.'
        }';
    }

    public function postRegister()
    {   
        $request = request();
        $json = $request->getJSON();
        $db = db_connect();

        $builder0 = $db->table('setting_captcha');
        $builder0->where('captcha_code', $json->captcha);
        $builder0->where('is_used', 0);
        $dataCaptcha = $builder0->get()->getResult();
        if (!$dataCaptcha) {
            echo '{
                "code": 1,
                "error": "Captcha is not valid!",
                "message": "Captcha is not valid!",
                "data": null
            }';
            exit();
        }
        $timestampCreatedCaptcha = strtotime(date($dataCaptcha[0]->created_date));
        $timestampNow = strtotime(date('Y-m-d H:i:s'));
        $intervalTimeCapthca = $timestampNow - $timestampCreatedCaptcha;
        if ($intervalTimeCapthca > 3600) {
            echo '{
                "code": 1,
                "error": "Captcha has been expired!",
                "message": "Captcha has been expired!",
                "data": null
            }';
            exit();
        }
        $update0['is_used'] = 1;
        $builder0->where('captcha_code', $json->captcha);
        $builder0->ignore(true)->update($update0);

        $insert = [];
        $insert['username'] = $json->username;
        $insert['email'] = $json->email;
        $insert['password'] = hash('sha256', $json->password);
        $insert['user_role'] = 2;
        $insert['user_status'] = 'ACTIVE';
        $insert['is_active'] = 1;
        $insert['token_login'] = hash('sha256', $json->email.date('YmdHis'));
        $builder = $db->table('app_users');
        $builder->ignore(true)->insert($insert);
        if ($db->affectedRows() == 1) {
            $_SESSION["login"] = (object)$insert;
            $_SESSION["token_login"] = $insert["token_login"];
            echo '{
                "code": 0,
                "error": "",
                "message": "You have been successfuly registered!",
                "data": '.json_encode((object)$insert).'
            }';
        } else {
            echo '{
                "code": 1,
                "error": "User has been registered!",
                "message": "User has been registered!",
                "data": null
            }';
        }
        $db->close();
    }

    public function postTop5() {
        $request = request();
        $dataPost = $request->getPost();
        $dataRequest = cek_token_login($dataPost);
        $db = db_connect();
        $query = $db->query('SELECT 
            appu.*, bto.id_app_service, 
            COUNT(*) AS total_order, COALESCE(ROUND(SUM(bto.price_profit), 2), 0) AS total_profit, 
            COALESCE(ROUND(SUM(bto.price_user), 2), 0) AS total_amount FROM bestpva.app_users appu 
            LEFT JOIN bestpva_transactions.orders bto ON bto.id_user = appu.id_user 
            where appu.is_active = 1 and appu.user_role = 2 and bto.status = \'Complete\'
            GROUP BY appu.id_user 
            ORDER BY total_order DESC 
            limit 5;');
        $dataFinal = $query->getResult();

        $query2 = $db->query('SELECT 
            id_user, COUNT(id_user) as total_register, email, username, last_ip_address, last_ip_location, created_date, is_active 
            from app_users 
            where is_active = 1 and user_role = 2 
            GROUP BY DATE(created_date) 
            ORDER BY created_date ASC 
            limit 30;');
        $dataFinal2 = $query2->getResult();

        $query3 = $db->query('SELECT 
        appu.id_user, appu.email, appu.username, appu.last_ip_address, appu.last_ip_location, appu.created_date, appu.is_active,
        (select count(bto.id) from bestpva_transactions.orders bto where bto.id_user = appu.id_user) total_order,
        (select count(bto.id) from bestpva_transactions.orders bto where bto.created_date >= DATE(NOW() - INTERVAL 7 DAY) and bto.id_user = appu.id_user and bto.status = \'Complete\') weekly_order,
        (select count(bto.id) from bestpva_transactions.orders bto where bto.created_date >= DATE(NOW() - INTERVAL 7 DAY) and bto.id_user = appu.id_user and bto.status = \'Complete\') weekly_order,
        COALESCE(ROUND((
            (select sum(bft.amount) from bestpva_finance.topup_users bft where bft.id_user = appu.id_user and bft.status = \'success\') -
            (select sum(bto.price_user) from bestpva_transactions.orders bto where bto.id_user = appu.id_user and bto.status = \'Complete\')
        ), 2), 0) user_saldo
        from bestpva.app_users appu
                where is_active = 1 and user_role = 2 
                GROUP BY (appu.id_user)
                ORDER BY appu.created_date DESC
            limit 30;');
        $dataFinal3 = $query3->getResult();
        $db->close();
        $finalData = json_encode($dataFinal);
        $finalData2 = json_encode($dataFinal2);
        $finalData3 = json_encode($dataFinal3);
        echo '{
            "code": 0,
            "error": "",
            "message": "",
            "data": {
                "top": '.$finalData.',
                "graph": '.$finalData2.',
                "users": '.$finalData3.'
            }
        }';
    }

    public function postGet_user_saldo() {
        $request = request();
        $dataPost = $request->getPost();
        $dataRequest = cek_token_login($dataPost);
        $db = db_connect();
        $query = $db->query('SELECT COALESCE(ROUND((
            (select sum(bft.amount) from bestpva_finance.topup_users bft where bft.id_user = '.$dataRequest['id_user'].' and bft.status = \'success\') -
            (select sum(bto.price_user) from bestpva_transactions.orders bto where bto.id_user = '.$dataRequest['id_user'].' and bto.status = \'Complete\')
        ), 2), 0) user_saldo;');
        $dataFinal = $query->getRow()->user_saldo;
        $db->close();
        $finalData = json_encode($dataFinal);
        echo '{
            "code": 0,
            "error": "",
            "message": "",
            "data": '.$finalData.'
        }';
    }

}
