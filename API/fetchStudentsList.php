<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";

    $respond["message"] = "Unable to fetch student list";
    $respond["authorized"] = false;
    $respond["students"] = array();
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $respond["authorized"] = true;
                $respond["message"] = "Student list is being fetched";
                $students = getStudentsData(getValue($result[0]["student_ids"]), $marshaler, $dynamodb);
                
                foreach ($students as $key => $student_result) {
                    if(empty(getValue($student_result["class_id"]))){
                        continue;
                    }
                    $class = getClassData(getValue($student_result["class_id"]), $marshaler, $dynamodb);
                    $class_name = getValue($class["name"]);
                    $attendance = getStudentTodayAttendanceData(getValue($student_result["student_id"]), $marshaler, $dynamodb);
                    $pick_up = getTodayRequestData(getValue($student_result["student_id"]), $marshaler, $dynamodb);

                    $status = "Undefined";
                    foreach ($attendance as $key => $attendance_value) {
                        if(getValue($attendance_value["type"]) === "present"){
                            $status = "In School";
                        }
                        else{
                            $status = "Absent";
                        }
                    }

                    foreach ($pick_up as $key => $pick_up_value) {
                        $request_count = count(getValue($pick_up_value["request"]));
                        $last_request = getValue(getValue($pick_up_value["request"])[$request_count - 1]);

                        $status = "Requested for pick up";

                        if(!empty(getValue($last_request["release_time"]))){
                            $status = "Checked Out";
                        }
                    }
                    
                    $temp_student = array(
                        "student_id"    => getValue($student_result["student_id"]),
                        "last_name"     => getValue($student_result["last_name"]),
                        'first_name'    => getValue($student_result["first_name"]),
                        'age'           => getValue($student_result["age"]),
                        'gender'        => getValue($student_result["gender"]),
                        'class_id'      => getValue($student_result["class_id"]),
                        'class_name'    => $class_name,
                        'attendance'    => $status
                    );
                    array_push($respond["students"], $temp_student);
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