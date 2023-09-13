<?php

namespace App\Controllers;

class Saldo extends BaseController
{
    public function index()
    {
        echo('welcome!');
    }

    public function postGet_user_saldo()
    {   
        $request = request();
        $dataPost = $request->getPost();
        $dataRequest = cek_token_login($dataPost);
        $db = db_connect();
        $id_user = $db->table('bestpva.app_users')->where('token_login', $dataPost['token_login'])->limit(1)->get()->getRow()->id_user;
        $q = 'SELECT
        (SELECT 
        COALESCE(ROUND(SUM(bftu.amount), 2), 0)
        from bestpva_finance.topup_users bftu
        where bftu.status = \'success\' and id_user = '.$id_user.') as total_topup,
        (SELECT 
        COALESCE(ROUND(SUM(bfru.amount), 2), 0)
        from bestpva_finance.refund_users bfru
        where bfru.status = \'Completed\' and id_user = '.$id_user.') as total_refund,
        (SELECT 
        COALESCE(ROUND(SUM(bto.price_user), 2), 0)
        from bestpva_transactions.orders bto
        where bto.status = \'Complete\' and id_user = '.$id_user.') as total_orders,
        (
            (SELECT 
            COALESCE(ROUND(SUM(bftu.amount), 2), 0)
            from bestpva_finance.topup_users bftu
            where bftu.status = \'success\' and id_user = '.$id_user.') -
            (SELECT 
            COALESCE(ROUND(SUM(bfru.amount), 2), 0)
            from bestpva_finance.refund_users bfru
            where bfru.status = \'Completed\' and id_user = '.$id_user.') -
            (SELECT 
            COALESCE(ROUND(SUM(bto.price_user), 2), 0)
            from bestpva_transactions.orders bto
            where bto.status = \'Complete\' and id_user = '.$id_user.')
        ) as saldo;
        ';
        $db = db_connect();
        $query = $db->query($q);
        $dataFinal = $query->getRow();
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
