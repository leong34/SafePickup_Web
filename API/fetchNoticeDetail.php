<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";

    $respond["message"] = "Unable to fetch notice detail";
    $respond["authorized"] = false;
    $respond["title"] = "";
    $respond["last_update"] = "";
    $respond["description"] = "";

    function viewNotice($notice_id, $user_id, $marshaler, $dynamodb){
        $key = $marshaler->marshalJson('
                {
                    "notice_id"      : "'.$notice_id.'"
                }
            ');

        $eav = $marshaler->marshalJson('
            {
                ":uids"     : "'.$user_id.'"
            }
        ');

        $updateExpression = 'set view_by.#uid = :uids';

        $params = [
            'TableName' => 'Notices',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeValues'=> $eav,
            'ExpressionAttributeNames' => ['#uid' => $user_id],
            'ReturnValues' => 'ALL_NEW'
        ];

        $result = update_item($params, $dynamodb);
    }

    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $respond["message"] = "Notice detail is fetched";
                $respond["authorized"] = true;
                
                $notice_data = getNoticesData($_POST['notice_id'], $marshaler, $dynamodb);

                viewNotice($_POST['notice_id'], $_POST['user_id'], $marshaler, $dynamodb);

                $respond["title"] = getValue($notice_data['title']);
                $respond["last_update"] = getValue($notice_data['updated_at']);
                $respond["description"] = getValue($notice_data['description']);
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