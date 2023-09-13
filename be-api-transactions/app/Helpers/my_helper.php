<?php 

    date_default_timezone_set("Asia/Bangkok");

    function cek_session_login() {        
        $request = request();
        $session = session();

        if($request->hasHeader('Authorization')) {
            $db = db_connect();
            $tokenLogin = $request->header('Authorization')->getValue();
            $builder = $db->table('bestpva.app_users')->where('token_login', $tokenLogin);
            $dataUser = $builder->get()->getRow();
            $db->close();
            if ($dataUser) {
                $user = $dataUser;
                $session->set('login', $user);
                $session->set('token_login', $user->token_login);
                $session->set('token_api', $user->token_api);
            } else {
                echo '{
                    "code": 1,
                    "error": "Token is not valid!",
                    "message": "Token is not valid!",
                    "data": null
                }';
                exit();
            }
        } else {
            echo '{
                "code": 1,
                "error": "Token is not valid!",
                "message": "Token is not valid!",
                "data": null
            }';
            exit();
        }
    }

    function cek_token_login($postData) {        
        $request = request();
        // $session = session();

        if(isset($postData['token_login'])) {
            $db = db_connect();
            $tokenLogin = $postData['token_login'];
            $builder = $db->table('bestpva.app_users')->where('token_login', $tokenLogin);
            $dataUser = $builder->get()->getRow();
            $db->close();
            if ($dataUser) {
                // $user = $dataUser;
                // $session->set('login', $user);
                // $session->set('token_login', $user['token_login']);
                // $session->set('token_api', $user->token_api);

                unset($postData['token_login']);
                return $postData;
            } else {
                echo '{
                    "code": 1,
                    "error": "Token is not valid!",
                    "message": "Token is not valid!",
                    "data": null
                }';
                exit();
            }
        } else {
            echo '{
                "code": 1,
                "error": "Token is not valid!",
                "message": "Token is not valid!",
                "data": null
            }';
            exit();
        }
    }

    function format_rupiah($angka) {
        $rupiah=number_format($angka,0,',','.');
        return $rupiah;
    }

    function curl($url, $isPost=false, $postFields=false, $headers=false) {
        set_time_limit(5);
        ignore_user_abort(false);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, $isPost);
        if ($isPost) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        }

        // In real life you should use something like:
        // curl_setopt($ch, CURLOPT_POSTFIELDS, 
        //          http_build_query(array('postvar1' => 'value1')));
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // Receive server response ...
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); 
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); //timeout in seconds
        // curl_setopt($ch, CURLOPT_TIMEOUT_MS, 200);

        $server_output = curl_exec($ch);

        curl_close($ch);

        return $server_output;
        // Further processing ...
        // if ($server_output == "OK") { ... } else { ... }
    }

    function get_services_price($country) {
        $data0 = json_decode(curl('https://api.sms-activate.org/stubs/handler_api.php?api_key=202AdA8c18eb16Ac763A6b81f1AceA5e&action=getPrices&country=6'));
        $data1 = get_object_vars(get_object_vars($data0)[6]);
        $dataX = [];
        foreach($data1 as $key => $value) {
            $dataX[$key] = get_object_vars($value)['cost'];
        }
        return ($dataX);
    }

    function upload_file($_request)
    {   
        $file = $_request->getFile('userfile');
        $validationRule = [
            'userfile' => [
                'label' => 'Image File',
                'rules' => [
                    'uploaded[userfile]',
                    'is_image[userfile]',
                    'mime_in[userfile,image/jpg,image/jpeg,image/gif,image/png,image/webp]',
                    'max_size[userfile,100]',
                    'max_dims[userfile,1024,768]',
                ],
            ],
        ];
        if ($file->getSizeByUnit('mb') > 2) {
            return ['errors' => "File size must < 2mb!"];
        }
        if (
            $file->getMimeType() !== 'image/jpg' &&
            $file->getMimeType() !== 'image/jpeg' &&
            $file->getMimeType() !== 'image/png' &&
            $file->getMimeType() !== 'image/webp'
            ) {
            return ['errors' => "File type must an image!"];
        }

        $newName = $file->getRandomName();
        $x = $file->move(ROOTPATH  . 'public/images', $newName);
       
        $data = ['name' => '/images/'.$newName];
        return $data;
        // return view('upload_form', $data);
    }

    function create_random_captcha() {
        $seed = str_split('abcdefghijklmnopqrstuvwxyz'
                 .'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
                 .'0123456789'); // and any other characters
        shuffle($seed); // probably optional since array_is randomized; this may be redundant
        $rand = '';
        foreach (array_rand($seed, 6) as $k) $rand .= $seed[$k];
        return $rand;
    }
    
    function getUserIP()
    {
        // Get real visitor IP behind CloudFlare network
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
                $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
                $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }
        $client  = @$_SERVER['HTTP_CLIENT_IP'];
        $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
        $remote  = $_SERVER['REMOTE_ADDR'];

        if(filter_var($client, FILTER_VALIDATE_IP))
        {
            $ip = $client;
        }
        elseif(filter_var($forward, FILTER_VALIDATE_IP))
        {
            $ip = $forward;
        }
        else
        {
            $ip = $remote;
        }

        return $ip;
    }


?>