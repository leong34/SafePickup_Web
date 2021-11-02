<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";

    $respond["message"] = "Unable to check in student";
    $respond["authorized"] = false;
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential']) && isset($_POST['student_ids']) && isset($_POST['encrypted_code'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $organization = getOrganization(getValue($result[0]['organization_id']), $dynamodb, $marshaler);
                $respond["authorized"] = true;
                
                if($_POST['encrypted_code'] !== getValue($organization['encryptCode'])){
                    $respond["message"] = "Invalid encrypted code";
                }
                else{
                    $respond["message"] = "Student is checked in";
                    $student_ids = $_POST['student_ids'];

                    foreach ($student_ids as $key => $value) {
                        $item = $marshaler->marshalJson('
                            {
                                "student_id"        : "'.$value.'",
                                "date"              : "'.date("Y-m-d").'",
                                "type"              : "present",
                                "check_in"          : ["'.date("H:i:s").'"],
                                "check_out"         : []
                            }
                        ');

                        $params = [
                            'TableName' => "Attendances",
                            'Item' => $item
                        ];
                        add_item($params, $dynamodb);
                    }
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