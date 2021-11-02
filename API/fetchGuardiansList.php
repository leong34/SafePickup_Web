<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";

    $respond["message"] = "Unable to fetch guardian list";
    $respond["authorized"] = false;
    $respond["guardians"] = array();
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $respond["message"] = "Guardian list is being fetched";
                $respond["authorized"] = true;

                $guardiansList = getGuardiansData(getValue($result[0]["family_ids"]), $marshaler, $dynamodb);

                foreach($guardiansList as $key => $guardian_result){
                    // print_r(json_encode($guardian_result));
                    $student_list = array();
                    foreach(getValue($guardian_result["student_ids"]) as $student_key => $student_value){
                        if(empty($student_key)) continue;

                        $student = getStudentData($student_key, $marshaler, $dynamodb);
                        $class = getClassData(getValue($student["class_id"]), $marshaler, $dynamodb);
                        $temp_student = array(
                            "student_id"    => getValue($student["student_id"]),
                            "last_name"     => getValue($student["last_name"]),
                            'first_name'    => getValue($student["first_name"]),
                            'age'           => getValue($student["age"]),
                            'gender'        => getValue($student["gender"]),
                            'class_id'      => getValue($student["class_id"]),
                            'class_name'    => getValue($class["name"]),
                            'attendance'    => ""
                        );
                        array_push($student_list, $temp_student);
                    }

                    $temp_guardian = array(
                        "user_id"           => getValue($guardian_result["user_id"]),
                        "user_internal_id"  => getValue($guardian_result["user_internal_id"]),
                        "last_name"         => getValue(getValue($guardian_result['info'])['last_name']),
                        'first_name'        => getValue(getValue($guardian_result['info'])['first_name']),
                        'tel'               => getValue(getValue($guardian_result['info'])['tel_num']),
                        'email'             => getValue($guardian_result["email"]),
                        'verified_at'       => getValue($guardian_result["verified_at"]),
                        'students'          => $student_list
                    );
                    array_push($respond["guardians"], $temp_guardian);
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