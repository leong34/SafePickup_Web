<?php
    require_once "DbConnect.php";

    $respond = array();

    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id'])){
            $key = $marshaler->marshalJson('
                    {
                        "user_id": "'.$_POST['user_id'].'"
                    }
                ');

            $eav = $marshaler->marshalJson('
                    {
                        ":message_token"  : ""
                    }
                ');

            $updateExpression = 'set message_token = :message_token';
            
            $params = [
                'TableName' => 'Users',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ReturnValues' => 'UPDATED_NEW'
            ];
            update_item($params, $dynamodb);
            $respond["message"] = "Successful logout";
        }
        else{
            $respond["message"] = "Failed to login - Requried Data is Missing.";
        }
    }
    else{
        $respond["message"] = "Type missmatch";
    }
    print_r(json_encode($respond));