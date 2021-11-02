<?php

function getValue($var){
    return array_values($var)[0];
}

function generateToken($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function getInternalId($symbol, $num){
    $num = $num + 10000000 + 1;
    return $symbol.$num;
}

function checkCredential($user_id, $credential, $dynamodb, $marshaler){
    $respond = array();
    $eav = $marshaler->marshalJson('
        {
            ":user_id": "'.$user_id.'" 
        }
    ');
    $params = [
        'TableName' => "Users",
        'KeyConditionExpression' => 'user_id = :user_id',
        'ExpressionAttributeValues'=> $eav
    ];

    $result = query_item($params, $dynamodb);
    $respond['result'] = $result;
    
    if(!empty($result) && !empty(array_values($result[0]['credential'])[0]) && array_values($result[0]['credential'])[0] == $credential)
    $respond['valid'] = true;
    else
    $respond['valid'] = false;

    return $respond;
}
