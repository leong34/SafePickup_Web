<?php
include "include/session.php";
include "include/dynamoDB_functions.php";

check_session($dynamodb, $marshaler);
$organization_id = $_SESSION['organization_id'];
$organization = getOrganization($organization_id, $dynamodb, $marshaler);

$students   = array_values($organization['student_ids']);
$users      = array_values($organization['user_ids'])[0];
$classes    = array_values($organization['class_ids']);

$class_arr      = getClassesData($classes, $marshaler, $dynamodb);
$attendance_arr = getAttendancesData($students, $marshaler, $dynamodb);
$student_arr    = getStudentsData($students, $marshaler, $dynamodb);

$request_arr    = getTodayRequestsData($students, $marshaler, $dynamodb);

if(isset($_GET["suid"]) && !empty($_GET["suid"])){
    $release_student = array();
    // loop confirm release student
    if(isset($_GET["suid"])){
        foreach ($_GET["suid"] as $key => $value) {
            if($value === "") continue;
            if(!isset($student_arr[$value])) continue;

            $request = array_values($request_arr[$value][0]["request"])[0];
            $last_request = array_values(end(array_values($request_arr[$value][0]["request"])[0]))[0];
            $index = count($request) - 1;

            if(!isset($request_arr[$value][date("Y-m-d")]) && empty(array_values($last_request["release_time"])[0])){
                $key = $marshaler->marshalJson('
                        {
                            "student_id"   : "'.$value.'",
                            "date"         : "' .date("Y-m-d"). '"
                        }
                    ');

                $eav = $marshaler->marshalJson('
                        {
                            ":rt"           : "'.date("H:i:s").'"
                        }
                    ');

                $updateExpression = 'set #req['.$index.'].release_time = :rt';
                $params = [
                    'TableName' => 'Requests',
                    'Key' => $key,
                    'UpdateExpression' => $updateExpression,
                    'ExpressionAttributeValues'=> $eav,
                    'ExpressionAttributeNames'=> ['#req' => "request"],
                    'ReturnValues' => 'ALL_NEW'
                    ];
                $result = update_item($params, $dynamodb);

                $key = $marshaler->marshalJson('
                    {
                        "student_id": "'.$value.'", 
                        "date": "'.date("Y-m-d").'"
                    }
                    ');

                $eav = $marshaler->marshalJson('
                    {
                        ":t": ["'.date("H:i:s").'"]
                    }
                    ');
                $params = [
                    'TableName' => "Attendances",
                    'Key' => $key,
                    'UpdateExpression' => 'set check_out = list_append(check_out, :t)',
                    'ExpressionAttributeValues'=> $eav,
                    'ReturnValues' => 'UPDATED_NEW'
                    ];
                $result = update_item($params, $dynamodb);
            }
        }

        $_SESSION['class_added'] = true;
        header('Location: index.php');
    }
}

?>