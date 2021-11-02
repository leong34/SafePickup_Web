<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";

    $respond["message"] = "Unable to mark student as absent";
    $respond["authorized"] = false;
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential']) && isset($_POST['student_ids'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $respond["message"] = "Student is marked as absent";
                $respond["authorized"] = true;
                $student_ids = $_POST['student_ids'];

                foreach ($student_ids as $key => $value) {
                    if(empty($value)) continue;
                    $item = $marshaler->marshalJson('
                        {
                            "student_id"        : "'.$value.'",
                            "date"              : "'.date("Y-m-d").'",
                            "type"              : "absent",
                            "check_in"          : [],
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