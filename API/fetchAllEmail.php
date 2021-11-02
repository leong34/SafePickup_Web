<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";

    $respond["message"]         = "Unable fetch emails";
    $respond["authorized"]      = false;
    $respond["user_emails"]     = array();
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $organization_id = getValue($result[0]["organization_id"]);
                
                $user_ids = getValue(getOrganization($organization_id, $dynamodb, $marshaler)["user_ids"]);

                foreach ($user_ids as $key => $value) {
                    $email = getUserData($key, $marshaler, $dynamodb);
                    
                    array_push($respond["user_emails"], getValue($email['email'])); 
                }
                
                $respond["message"] = "Successful retrieve emails";
                $respond["authorized"] = true;

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