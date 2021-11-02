<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";

    $respond["message"]         = "Unable fetch student attendance";
    $respond["authorized"]      = false;
    $respond["attendance"]      = array();

    function getEventBasedOnClassId($class_id, $dynamodb, $marshaler){
        $eav = $marshaler->marshalJson('
            {
                ":class_id": "'.$class_id.'" 
            }
        ');
        $params = [
            'TableName' => "Events",
            'IndexName' => "classDate-index",
            'KeyConditionExpression' => 'class_id = :class_id',
            'ExpressionAttributeValues'=> $eav
        ];
    
        $result = query_item($params, $dynamodb);
        return $result;
    }

    function attendanceStatus($organization_check_in_time, $organization_late_threshold, $check_in_time){
        $status = "On Time";
        if(date("H:i", strtotime($check_in_time)) > $organization_check_in_time){
            $status = "Late";
        }
        return $status;
    }
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $organization_id = getValue($result[0]["organization_id"]);
                $organization = getOrganization($organization_id, $dynamodb, $marshaler);

                $organization_check_in_time  = strtotime(getValue($organization['check_in_time']));
                $organization_check_out_time = strtotime(getValue($organization['check_out_time']));
                $organization_late_threshold = getValue($organization['late_threshold']);

                $organization_check_in_time = date("H:i", strtotime('+'.$organization_late_threshold.' minutes', $organization_check_in_time));

                $attendances = getStudentAttendance($_POST['student_id'], $marshaler, $dynamodb);

                $temp_attendace = array();
                foreach($attendances as $key => $value){
                    $status = "";
                    $pick_up_by = "";
                    $request_time = "";
                    $pick_up_internal_id = "";
                    $guardian_id = "";
                    if(getValue($value['type']) == "absent"){
                        $status = "Absent";
                        $check_in_time = "";
                        $check_out_time = "";
                    }
                    else{
                        $check_in_arr = getValue($value['check_in']);
                        $check_out_arr = getValue($value['check_out']);

                        if(count($check_in_arr) == count($check_out_arr)){
                            $check_in_time = getValue(end($check_in_arr));
                            $check_out_time = getValue(end($check_out_arr));

                            $pick_up_request = getValue(getStudentPickUpRequest($_POST['student_id'], $marshaler, $dynamodb, getValue($value['date'])));
                            $pick_up_request = getValue(getValue(end($pick_up_request['request'])));

                            $guardian_id = getValue($pick_up_request['user_id']);
                            $request_time = getValue($pick_up_request['request_time']);

                            $guardian_detail = getUserData($guardian_id, $marshaler, $dynamodb);
                            $guardian_info = getValue($guardian_detail['info']);

                            // if($guardian_id != $_POST['user_id']){
                            //     $pick_up_by = getValue($guardian_info['last_name'])." ".getValue($guardian_info['first_name']);
                            // }
                            // else{
                            //     $pick_up_by = "You";
                            // }
                            $pick_up_by = getValue($guardian_info['last_name'])." ".getValue($guardian_info['first_name']);
                            $pick_up_internal_id = getValue($guardian_detail['user_internal_id']);
                            
                        }else{
                            $check_in_time = getValue(end($check_in_arr));
                            $check_out_time = '-';
                        }
                        $status = attendanceStatus($organization_check_in_time, $organization_late_threshold, $check_in_time);
                    }

                    $temp_attendace = array(
                        "date"                  => getValue($value['date']),
                        "status"                => $status,
                        "check_in_time"         => $check_in_time,
                        "check_out_time"        => $check_out_time,
                        "guardian_id"           => $guardian_id,
                        "pick_up_by"            => $pick_up_by,
                        "pick_up_internal_id"   => $pick_up_internal_id,
                        "request_time"          => $request_time
                    );
                    array_push($respond["attendance"], $temp_attendace);
                }
                
                $respond["message"]         = "Successful retrieve student attendance";
                $respond["authorized"]      = true;

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