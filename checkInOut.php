<?php
include "include/session.php";
include "include/user_Functions.php";
include "include/dynamoDB_functions.php";

check_session($dynamodb, $marshaler);

$organization_id = $_SESSION['organization_id'];
$organization   = getOrganization($organization_id, $dynamodb, $marshaler);

$students       = array_values($organization['student_ids']);
$classes        = array_values($organization['class_ids']);

$class_arr      = getClassesData($classes, $marshaler, $dynamodb);
$student_arr    = getStudentsData($students, $marshaler, $dynamodb);
$attendance_arr = getAttendancesData($students, $marshaler, $dynamodb);


date_default_timezone_set("Asia/Kuala_Lumpur");

function checkInOut($suid, $type, $marshaler, $dynamodb){
    $key = $marshaler->marshalJson('
        {
            "student_id": "'.$suid.'", 
            "date": "'.date("Y-m-d").'"
        }
    ');
    $eav = $marshaler->marshalJson('
        {
            ":t": ["'.date("H:i:s").'"]
        }
    ');

    if($type == "check in"){
        $params = [
            'TableName' => "Attendances",
            'Key' => $key,
            'UpdateExpression' => 'set check_in = list_append(check_in, :t)',
            'ExpressionAttributeValues'=> $eav,
            'ReturnValues' => 'UPDATED_NEW'
        ];
    }
    else{
        $params = [
            'TableName' => "Attendances",
            'Key' => $key,
            'UpdateExpression' => 'set check_out = list_append(check_out, :t)',
            'ExpressionAttributeValues'=> $eav,
            'ReturnValues' => 'UPDATED_NEW'
        ];
    }
    
    update_item($params, $dynamodb);
}

function addAttendance($suid, $marshaler, $dynamodb, $type){
    if($type == "present"){
        $item = $marshaler->marshalJson('
            {
                "student_id"        : "'.$suid.'",
                "date"              : "'.date("Y-m-d").'",
                "type"              : "'.$type.'",
                "check_in"          : ["'.date("H:i:s").'"],
                "check_out"         : []
            }
        ');
    }
    else{
        $item = $marshaler->marshalJson('
            {
                "student_id"        : "'.$suid.'",
                "date"              : "'.date("Y-m-d").'",
                "type"              : "'.$type.'",
                "check_in"          : [],
                "check_out"         : []
            }
        ');
    }
    $params = [
        'TableName' => "Attendances",
        'Item' => $item
    ];
    add_item($params, $dynamodb);
}

if(isset($_GET["suid"]) && isset($_GET["type"]) && $_GET["type"] == "absent"){
    $updateable_student = array();

    // loop confirm changeable student
    if(isset($_GET["suid"])){
        foreach ($_GET["suid"] as $key => $value) {
            if($value === "") continue;
            if(!isset($student_arr[$value]) || isset($updateable_student[$value])) continue;
            if(!empty(array_values($student_arr[$value]['class_id'])[0]) && !isset($attendance_arr[$value][date("Y-m-d")]))
                $updateable_student[$value] = $value;
        }
    }
    
    if(!empty($updateable_student)){
        foreach($updateable_student as $key => $value){
            addAttendance($key, $marshaler, $dynamodb, "absent");
        }
    }

    $_SESSION['class_added'] = true;
    header('Location: userStudent.php');
}
else if(isset($_GET["suid"])){
    $updateable_student = array();

    // loop confirm changeable student
    if(isset($_GET["suid"])){
        foreach ($_GET["suid"] as $key => $value) {
            if($value === "") continue;
            if(!isset($student_arr[$value]) || isset($updateable_student[$value])) continue;
            if(!empty(array_values($student_arr[$value]['class_id'])[0]))
                $updateable_student[$value] = $value;
        }
    }
    
    // add or update attendance log
    if(!empty($updateable_student)){
        foreach($updateable_student as $key => $value){
            // update if and only if student present
            if(isset($attendance_arr[$key][date("Y-m-d")])){
                if(array_values($attendance_arr[$key][date("Y-m-d")]["type"])[0] == "present"){
                    $check_in   = array_values($attendance_arr[$key][date("Y-m-d")]["check_in"])[0];
                    $check_out  = array_values($attendance_arr[$key][date("Y-m-d")]["check_out"])[0];
                    
                    if(count($check_in) == count($check_out)){
                        $type = "check in";
                        checkInOut($key, $type, $marshaler, $dynamodb);
                    }
                    else{
                        continue;
                    }
                    // $type = count($check_in) == count($check_out) ? "check in" : "check out";
                    // checkInOut($key, $type, $marshaler, $dynamodb);
                }
            }
            // add
            else{
                addAttendance($key, $marshaler, $dynamodb, "present");
            }
        }
    }
    $_SESSION['class_added'] = true;
    header('Location: userStudent.php');
}
else if(isset($_GET["cuid"]) && $_GET["type"] == "check_in"){
    $updateable_student = array();

    // loop confirm changeable student
    if(isset($_GET["cuid"])){
        foreach ($_GET["cuid"] as $key => $value) {
            if($value === "") continue;
            if(!isset($class_arr[$value]) || isset($checkin_class[$value])) continue;
            if(!empty(array_values($class_arr[$value]['student_ids'])[0])){
                foreach(array_values($class_arr[$value]['student_ids'])[0] as $student_key => $student_value){
                    if(!isset($attendance_arr[$student_key][date("Y-m-d")])){
                        $updateable_student[$student_key] = $student_key;
                    }
                    else if(array_values($attendance_arr[$student_key][date("Y-m-d")]['type'])[0] != "absent"){
                        $check_in = array_values($attendance_arr[$student_key][date("Y-m-d")]['check_in'])[0];
                        $check_out = array_values($attendance_arr[$student_key][date("Y-m-d")]['check_out'])[0];

                        if(count($check_in) == count($check_out))
                            $updateable_student[$student_key] = $student_key;
                    }
                }
            }
        }
    }

    // add or update attendance log
    if(!empty($updateable_student)){
        foreach($updateable_student as $key => $value){
            // update if and only if student present
            if(isset($attendance_arr[$key][date("Y-m-d")])){
                if(array_values($attendance_arr[$key][date("Y-m-d")]["type"])[0] == "present"){
                    checkInOut($key, "check in", $marshaler, $dynamodb);
                }
            }
            // add
            else{
                addAttendance($key, $marshaler, $dynamodb, "present");
            }
        }
    }
    
    $_SESSION['class_checked_in'] = true;
    header('Location: class.php');
}
else if(isset($_GET["cuid"]) && $_GET["type"] == "check_out"){
    $updateable_student = array();

    // loop confirm changeable student
    if(isset($_GET["cuid"])){
        foreach ($_GET["cuid"] as $key => $value) {
            if($value === "") continue;
            if(!isset($class_arr[$value]) || isset($checkin_class[$value])) continue;
            if(!empty(array_values($class_arr[$value]['student_ids'])[0])){
                foreach(array_values($class_arr[$value]['student_ids'])[0] as $student_key => $student_value){
                    if(isset($attendance_arr[$student_key][date("Y-m-d")]) && array_values($attendance_arr[$student_key][date("Y-m-d")]['type'])[0] != "absent"){
                        $check_in = array_values($attendance_arr[$student_key][date("Y-m-d")]['check_in'])[0];
                        $check_out = array_values($attendance_arr[$student_key][date("Y-m-d")]['check_out'])[0];

                        if(count($check_in) != count($check_out))
                            $updateable_student[$student_key] = $student_key;
                    }
                }
            }
        }
    }

    // check out attendance log
    if(!empty($updateable_student)){
        foreach($updateable_student as $key => $value){
            checkInOut($key, "check out", $marshaler, $dynamodb);
        }
    }
    $_SESSION['class_checked_out'] = true;
    header('Location: class.php');
}

?>