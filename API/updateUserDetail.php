<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";

    $respond["message"]         = "Failed to update user detail";
    $respond["authorized"]      = false;
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential']) && isset($_POST['last_name']) && isset($_POST['first_name']) && isset($_POST['tel']) ){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $respond["authorized"] = true;

                // print_r(json_encode($result[0]));
                $key = $marshaler->marshalJson('
                    {
                        "user_id": "'.$_POST['user_id'].'"
                    }
                ');

                if(!empty($_POST['old_password']) && !empty($_POST['new_password'])){
                    $respond["message"] = "Failed due to wrong password";
                    if(md5(array_values($result[0]['token'])[0].$_POST['old_password']) === array_values($result[0]['password'])[0]){
                        $respond["message"] = "Updating password and user detail success";
                        $newToken = generateToken();
                        $eav = $marshaler->marshalJson('
                            {
                                ":last_name"       : "'.$_POST['last_name'].'",
                                ":first_name"      : "'.$_POST['first_name'].'",
                                ":tel_num"         : "'.$_POST['tel'].'",
                                ":password"        : "'.md5($newToken.$_POST['new_password']).'",
                                ":token"           : "'.$newToken.'"
                            }
                        ');

                        $updateExpression = 'set info.#ln = :last_name, info.#fn = :first_name, info.#tn = :tel_num, password = :password, #tk = :token';
                        $params = [
                            'TableName' => 'Users',
                            'Key' => $key,
                            'UpdateExpression' => $updateExpression,
                            'ExpressionAttributeValues'=> $eav,
                            'ExpressionAttributeNames' => ['#ln' => 'last_name', '#fn' => 'first_name', '#tn' => 'tel_num', '#tk' => 'token'],
                            'ReturnValues' => 'ALL_NEW'
                        ];
                        update_item($params, $dynamodb);
                    }
                }
                else{
                    $respond["message"] = "User detail successfully update";
                    $eav = $marshaler->marshalJson('
                        {
                            ":last_name"       : "'.$_POST['last_name'].'",
                            ":first_name"      : "'.$_POST['first_name'].'",
                            ":tel_num"         : "'.$_POST['tel'].'"
                        }
                    ');

                    $updateExpression = 'set info.#ln = :last_name, info.#fn = :first_name, info.#tn = :tel_num';
                    $params = [
                        'TableName' => 'Users',
                        'Key' => $key,
                        'UpdateExpression' => $updateExpression,
                        'ExpressionAttributeValues'=> $eav,
                        'ExpressionAttributeNames' => ['#ln' => 'last_name', '#fn' => 'first_name', '#tn' => 'tel_num'],
                        'ReturnValues' => 'ALL_NEW'
                    ];
                    update_item($params, $dynamodb);
                }
            }
            else{
                $respond["message"] = "Invalid Credential.";
            }
        }
        else{
            $respond["message"] = "Requried Data is Missing.";
        }
    }
    else{
        $respond["message"] = "Type missmatch.";
    }

    print_r(json_encode($respond));