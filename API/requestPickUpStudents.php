<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";
    include_once "Utilities/sendNotification.php";
    include_once "faceRekog.php";

    $respond["message"] = "Unable to to send request";
    $respond["image"] = "None";
    $respond["authorized"] = false;
    $respond["rekog_message"] = "";
    $respond["face_id_verified"] = false;

    function getTargetMessagingTokens($student_ids, $marshaler, $dynamodb){
        $extracted_student_ids = array();
        $messagingTokens = array();
        $guardian_ids = array();
        $requested_student = "";
        $return_data = array();
        foreach($student_ids as $key => $value){
            $extracted_student_ids[$value] = $value;
        }

        $students = getStudentsData($extracted_student_ids, $marshaler, $dynamodb);
        foreach ($students as $key => $value) {
            foreach (getValue($value['guardian_ids']) as $guardian_key => $guardian_value) {
                $guardian_ids[getValue($guardian_value)] = getValue($guardian_value);
            }
            $requested_student .= getValue($value['last_name'])." ".getValue($value['first_name']).", ";
        }

        $requested_student = substr($requested_student, 0, -2);
        
        foreach ($guardian_ids as $key => $value) {
            $token = getValue(getUserData($key, $marshaler, $dynamodb)['message_token']);
            if(!empty($token)){
                $messagingTokens[$token] = $token;
            }
        }
        $return_data = array(
            "student_name"      => $requested_student,
            "messagingTokens"   => $messagingTokens
        );
        return $return_data;
    }
    

    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential']) && isset($_POST['face_id']) && isset($_POST['student_ids']) && isset($_FILES['image'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $user_full_name = getValue(getValue($result[0]["info"])["last_name"])." ".getValue(getValue($result[0]["info"])["first_name"]);
                
                $target_file = basename($_FILES["image"]["name"]);
                $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

                $target_file = "Picture/".$_POST['user_id'].".".$imageFileType;
                $resize_file = "Picture/".$_POST['user_id']."_resize.".$imageFileType;

                $respond["authorized"] = true;

                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    list($width, $height) = getimagesize($target_file);
                        
                    $new_width = $width * 0.1;
                    $new_height = $height * 0.1;
                        
                    $image_p = imagecreatetruecolor($new_width, $new_height);
                    $image = imagecreatefromjpeg($target_file);

                    imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

                    imagejpeg($image_p, $resize_file, 100);

                    $respond["image"]   = $resize_file;
                    $recog_respond = searchFaceByImage($resize_file, $client, $_POST['face_id']);
                    $respond["rekog_message"] = $recog_respond['message'];
                    $respond["message"] = "Invalid face id";

                    if($recog_respond['validated']){
                        $respond["face_id_verified"] = true;
                        $respond["message"] = "Request is send ";
                        $student_ids = $_POST['student_ids'];
                        $added_student = array();

                        foreach ($student_ids as $key => $value) {
                            $student_ids[$key] = str_replace("\"","", $value);
                        }

                        foreach ($student_ids as $key => $value) {
                            if(!empty(getStudentTodayRequest($value, $marshaler, $dynamodb))){
                                continue;
                            }
                            array_push($added_student, $value);
                            $item = $marshaler->marshalJson('
                                    {
                                        "student_id"        : "'.$value.'",
                                        "date"              : "' .date("Y-m-d"). '",
                                        "type"              : "pick up",
                                        "request"           : [ {"user_id": "'.$_POST['user_id'].'", "request_time": "'.date("H:i:s").'", "release_time": ""}]
                                    }
                                ');

                            $params = [
                                'TableName' => "Requests",
                                'Item' => $item
                            ];
                            add_item($params, $dynamodb);
                        }

                        $message_details = getTargetMessagingTokens($added_student, $marshaler, $dynamodb);
                        $messaging_tokens = $message_details["messagingTokens"];
                        $data = array(
                            'title'     => "Pickup Request Notification",
                            'body'      => $user_full_name." have requested to pickup ".$message_details['student_name']
                        );

                        broadCastNotification($messaging_tokens, $data);
                    }

                    unlink($target_file);
                    unlink($resize_file);
                }
                else{
                    $respond["message"] = "Failed to insert to server";
                    $respond["image"]   = $target_file;
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