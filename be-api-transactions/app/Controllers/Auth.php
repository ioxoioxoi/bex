<?php

namespace App\Controllers;

class Auth extends BaseController
{
    public function index()
    {
        return view('welcome_message');
    }

    public function postLogout()
    {   
        $session = session();
        $session->remove('login');
        $session->remove('token_login');
    }

    public function postSet_token()
    {
        $request = request();
        $postData = $request->getPost();
        $session = session();
        $session->set('login', $postData['login']);
        $session->set('token_login', $postData['token_login']);
        // sleep(5);
        print_r($session->get('login'));
    }

    public function postGet_token_login()
    {
        check_token_login();
        // echo '{
        //     "code": 0,
        //     "error": "",
        //     "message": "",
        //     "data": {
        //         "session_login": "'. $session_login. '",
        //         "session_token_login": "'. $session_token_login. '"
        //     }
        // }';
    }
}
