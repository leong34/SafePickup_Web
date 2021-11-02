<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";

    $respond["message"] = "Unable to delete guardians";
    $respond["authorized"] = false;

    function updateGuardian($user_id, $dynamodb, $marshaler){
        $key = $marshaler->marshalJson('
                {
                    "user_id"      : "'.$user_id.'"
                }
            ');

        $eav = $marshaler->marshalJson('
            {
                ":student_ids"     : {},
                ":deleted_at"      : "'.time().'"
            }
        ');

        $updateExpression = 'set student_ids = :student_ids, deleted_at = :deleted_at';

        $params = [
            'TableName' => 'Users',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeValues'=> $eav,
            'ReturnValues' => 'ALL_NEW'
        ];
        $result = update_item($params, $dynamodb);
    }

    function updateCreator($creator_id, $user_id, $dynamodb, $marshaler){
        $key = $marshaler->marshalJson('
                {
                    "user_id"      : "'.$creator_id.'"
                }
            ');

        $updateExpression = 'remove family_ids.#fid';

        $params = [
            'TableName' => 'Users',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeNames' => ['#fid' => $user_id],
            'ReturnValues' => 'ALL_NEW'
        ];
        $result = update_item($params, $dynamodb);
    }

    function updateStudent($student_id, $user_id, $dynamodb, $marshaler){
        $key = $marshaler->marshalJson('
                {
                    "student_id"      : "'.$student_id.'"
                }
            ');

        $updateExpression = 'remove guardian_ids.#guid';
        $params = [
            'TableName' => 'Students',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeNames'=> ['#guid' => $user_id],
            'ReturnValues' => 'ALL_NEW'
        ];
        $result = update_item($params, $dynamodb);
    }
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential']) && isset($_POST['guardian_ids'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $respond["message"] = "Guardians is deleted and remove from student";
                $respond["authorized"] = true;
                $guardian_ids = $_POST['guardian_ids'];

                foreach ($guardian_ids as $key => $value) {
                    if(empty($value)) continue;
                    $guardian_data = getUserData($value, $marshaler, $dynamodb);
                    $student_ids = getValue($guardian_data['student_ids']);

                    foreach ($student_ids as $key => $student_id) {
                        updateStudent(getValue($student_id), $value, $dynamodb, $marshaler);
                    }

                    updateGuardian($value, $dynamodb, $marshaler);
                    updateCreator(getValue($result[0]['user_id']), $value, $dynamodb, $marshaler);
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