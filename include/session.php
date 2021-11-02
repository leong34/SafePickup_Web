<?php
session_start();

function unset_all_session(){
    unset($_SESSION['user_id']);
    unset($_SESSION['credential']);
    unset($_SESSION['organization_id']);
}

function set_session($session_arr){
    $_SESSION['user_id']    = $session_arr['user_id'];
    $_SESSION['credential'] = $session_arr['credential'];
    $_SESSION['organization_id'] = $session_arr['organization_id'];
}

function check_session($dynamodb, $marshaler){
    if(isset($_SESSION['user_id']) && isset($_SESSION['credential']) && isset($_SESSION['organization_id'])){
        $eav = $marshaler->marshalJson('
            {
                ":user_id": "'.$_SESSION['user_id'].'" 
            }
        ');
        $params = [
            'TableName' => "Users",
            'KeyConditionExpression' => 'user_id = :user_id',
            'ExpressionAttributeValues'=> $eav
        ];

        $result = query_item($params, $dynamodb);
        
        if(empty($result) || array_values($result[0]['credential'])[0] != $_SESSION['credential'] || empty(array_values($result[0]['credential'])[0])){
            unset_all_session();
            header('Location: login.php');
        }
    }
    else{
        unset_all_session();
        header('Location: login.php');
    }
}

date_default_timezone_set("Asia/Kuala_Lumpur");