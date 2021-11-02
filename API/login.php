<?php
    require_once "DbConnect.php";

    $respond = array();
    $data = array(
        'user_id'           => "",
        'credential'        => "",
        'organization_id'   => "",
        'face_id'           => ""
    );

    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['email']) && isset($_POST['password'])){
            $eav = $marshaler->marshalJson('
                {
                    ":email": "'.$_POST['email'].'" 
                }
            ');
            $params = [
                'TableName' => "Users",
                'IndexName' => "user_index",
                'KeyConditionExpression' => 'email = :email',
                'ExpressionAttributeValues'=> $eav
            ];
        
            $result = query_item($params, $dynamodb);
            if(!empty($result)){
                $result = array_values($result)[0];

                if(md5(array_values($result['token'])[0].$_POST['password']) === array_values($result['password'])[0] && array_values($result['user_type'])[0] !== "1" && isset($result['verified_at']) && !empty(array_values($result['verified_at'])[0])){
                    $data = array(
                        'user_id'           => array_values($result['user_id'])[0],
                        'credential'        => md5(array_values($result['user_id'])[0].time()),
                        'organization_id'   => array_values($result['organization_id'])[0],
                        'face_id'           => array_values($result['face_id'])[0],
                        'user_type'         => array_values($result['user_type'])[0]
                    );
        
                    $key = $marshaler->marshalJson('
                            {
                                "user_id": "'.$data['user_id'].'"
                            }
                        ');
        
                    $eav = $marshaler->marshalJson('
                            {
                                ":credential"     : "'.$data['credential'].'",
                                ":message_token"  : "'.$_POST['message_token'].'"
                            }
                        ');
        
                    $updateExpression = 'set credential = :credential, message_token = :message_token';
                    
                    $params = [
                        'TableName' => 'Users',
                        'Key' => $key,
                        'UpdateExpression' => $updateExpression,
                        'ExpressionAttributeValues'=> $eav,
                        'ReturnValues' => 'ALL_NEW'
                    ];
                    update_item($params, $dynamodb);
                    $respond["message"] = "Welcome ".array_values(array_values($result['info'])[0]["last_name"])[0]." ".array_values(array_values($result['info'])[0]["first_name"])[0].".";
                }
                else if(isset($result['verified_at']) && empty(array_values($result['verified_at'])[0])){
                    $respond["message"] = "Account is not verified yet.";
                }
                else{
                    $respond["message"] = "Password missmatch.";
                }
            }
            else{
                $respond["message"] = "Account does not exist.";
            }
        }
        else{
            $respond["message"] = "Failed to login - Requried Data is Missing.";
        }
    }
    else{
        $respond["message"] = "Type missmatch";
    }

    $respond["data"] = $data;

    print_r(json_encode($respond));