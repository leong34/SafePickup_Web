<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";

    $respond["message"] = "Unauthorized.";
    $respond["authorized"] = false;
    $respond["empty_face_id"] = true;

    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $respond["message"] = "Authorized.";
                $respond["authorized"] = true;
                $respond["empty_face_id"] = empty(array_values($result[0]['face_id'])[0]);
            }
        }
        else{
            $respond["message"] = "Unable to check credential - Requried Data is Missing.";
        }
    }
    else{
        $respond["message"] = "Type missmatch.";
    }

    print_r(json_encode($respond));