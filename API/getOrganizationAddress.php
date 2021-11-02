<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";

    $respond["message"] = "Unable fetch organization address";
    $respond["authorized"] = false;
    $respond["full_address"] = "";
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $organization = getOrganization(getValue($result[0]['organization_id']), $dynamodb, $marshaler);
                $respond["message"] = "Organization address fetched";
                $respond["authorized"] = true;
                $respond["full_address"] = getValue($organization['address_1']).", ".getValue($organization['address_2']).", ".getValue($organization['zip'])." ".getValue($organization['city']).", ".getValue($organization['state']);
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