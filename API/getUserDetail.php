<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";

    $respond["message"]         = "Unable fetch user detail";
    $respond["authorized"]      = false;
    $respond["user_inner_id"]   = "";
    $respond["email"]           = "";
    $respond["last_name"]       = "";
    $respond["first_name"]      = "";
    $respond["tel_num"]         = "";
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $respond["message"] = "Successful retrieve user detail";
                $respond["authorized"] = true;

                $respond["user_inner_id"]   = getValue($result[0]['user_internal_id']);
                $respond["email"]           = getValue($result[0]['email']);
                $respond["last_name"]       = getValue(getValue($result[0]['info'])['last_name']);
                $respond["first_name"]      = getValue(getValue($result[0]['info'])['first_name']);
                $respond["tel_num"]         = getValue(getValue($result[0]['info'])['tel_num']);
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