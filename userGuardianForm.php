<?php
    include "include/mailing.php";
    include "include/session.php";
    include "include/user_Functions.php";
    include "include/dynamoDB_functions.php";

    check_session($dynamodb, $marshaler);

    $organization_id = $_SESSION['organization_id'];
    $organization   = getOrganization($organization_id, $dynamodb, $marshaler);

    $students       = array_values($organization['student_ids']);
    $student_arr    = array();

    $classes        = array_values($organization['class_ids']);
    $class_arr      = array();

    $users          = array_values($organization['user_ids'])[0];

    $class_arr      = getClassesData($classes, $marshaler, $dynamodb);
    $student_arr    = getStudentsData($students, $marshaler, $dynamodb);
    $guardian_arr   = getGuardiansData($users, $marshaler, $dynamodb);
    $user_arr       = getUsersData($users, $marshaler, $dynamodb);

    $guardian = NULL;
    $user_request = array();
    $overall_requests = array(
        "on_time_request" => 0,
        "early_request" => 0,
        "late_request" => 0,
    );

    $check_in_time = strtotime(array_values($organization['check_in_time'])[0]);
    $check_out_time = strtotime(array_values($organization['check_out_time'])[0]);
    $late_threshold = array_values($organization['late_threshold'])[0];

    $check_in_time          = date("H:i", strtotime('+'.$late_threshold.' minutes', $check_in_time));
    $early_check_out_time   = date("H:i", strtotime('-'.$late_threshold.' minutes', $check_out_time));
    $check_out_time         = date("H:i", strtotime('+'.$late_threshold.' minutes', $check_out_time));

    $extracted_guardian_arr = array();
    foreach($user_arr as $key => $value){
        $extracted_guardian_arr[$key] = array_values($value['email'])[0];
    }

    $extracted_guardian_name = array();
    foreach($guardian_arr as $key => $value){
        $extracted_guardian_name[$key] = array_values(array_values($value['info'])[0]['last_name'])[0]." ".array_values(array_values($value['info'])[0]['first_name'])[0];
    }


    $extracted_student_arr = array();
    foreach($student_arr as $key => $value){
        if(!empty(array_values($value['deleted_at'])[0]))continue;
        $extracted_student_arr[$key]['name'] = array_values($value['last_name'])[0].' '.array_values($value['first_name'])[0];
        $extracted_student_arr[$key]['class_id'] = array_values($value['class_id'])[0];
        $extracted_student_arr[$key]['student_internal_id'] = array_values($value['student_internal_id'])[0];
    }

    $extracted_class_arr = array();
    foreach($class_arr as $key => $value){
        $extracted_class_arr[$key] = array_values($value["name"])[0];
    }

    $student_name = array();

    foreach($student_arr as $key => $value){
        if(empty(array_values($value['deleted_at'])[0]))
        $student_name[$key] = array_values($value['last_name'])[0].' '.array_values($value['first_name'])[0];
    }

    function getAllStudentRelated($user_ids, $dynamodb, $marshaler, $guardian_arr){
        $student_related = array();
        foreach ($user_ids as $user_key => $suer_value) {
            $students = array_values($guardian_arr[$user_key]['student_ids'])[0];
            foreach($students as $student_key => $student_value){
                $student_related[$student_key] = $student_key;
            }
        }
        return $student_related;
    }

    function removeGuardianIdFromStudent($family_ids, $student_related, $dynamodb, $marshaler){
        $family_ids_arr = array();
        $updateExpression = 'remove ';

        foreach ($family_ids as $family_key => $family_value) {
            $family_ids_arr['#'.$family_key] = $family_key;
            $updateExpression .= 'guardian_ids.#'.$family_key.', ';
        }

        $updateExpression = substr($updateExpression, 0, -2);

        foreach($student_related as $student_key => $student_value){
            $key = $marshaler->marshalJson('
                    {
                        "student_id"      : "'.$student_value.'"
                    }
                ');

            $params = [
                'TableName' => 'Students',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeNames'=> $family_ids_arr,
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }
    }

    function updateCreator($creator_id, $user_id, $dynamodb, $marshaler){
        $key = $marshaler->marshalJson('
                {
                    "user_id"      : "'.$creator_id.'"
                }
            ');

        $updateExpression = 'remove family_ids.#fid';

        $params = [
            'TableName' => 'Users',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeNames' => ['#fid' => $user_id],
            'ReturnValues' => 'ALL_NEW'
        ];
        $result = update_item($params, $dynamodb);
    }

    function updateAllFamily($family_ids, $dynamodb, $marshaler){
        foreach ($family_ids as $family_key => $family_value) {
            $key = $marshaler->marshalJson('
                    {
                        "user_id"      : "'.$family_key.'"
                    }
                ');

            $eav = $marshaler->marshalJson('
                {
                    ":student_ids"     : {},
                    ":family_ids"      : {},
                    ":deleted_at"      : "'.time().'"
                }
            ');

            $updateExpression = 'set student_ids = :student_ids, deleted_at = :deleted_at, family_ids = :family_ids';

            $params = [
                'TableName' => 'Users',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }
    }


    if(isset($_POST['type']) && $_POST['type'] == "add"){
        $students_assigned = array();
        if(isset($_POST["student_ids"])){
            foreach ($_POST["student_ids"] as $key => $value) {
                if($value === "") continue;
                if(!isset($student_arr[$value]) || isset($students_assigned[$value])) continue;
                $students_assigned[$value] = $value;
            }
        }
        // create new user
        $user_id = "U".time()."".generateToken()."";
        $user_token = generateToken();
        if(!empty($students_assigned)){
        $item = $marshaler->marshalJson('
                {
                    "user_id"           : "'.$user_id.'",
                    "user_internal_id"  : "'.getInternalId("G", count($users)).'",
                    "created_at"        : "' .time(). '",
                    "updated_at"        : "' .time(). '",
                    "deleted_at"        : "",
                    "email"             : "'.$_POST['email'].'",
                    "verified_at"       : "",
                    "password"          : "'.md5($user_token.$_POST['new_password']).'",
                    "token"             : "'.$user_token.'",
                    "user_type"         : 0,
                    "message_token"     : "",
                    "credential"        : "",
                    "family_ids"        : {},
                    "created_by"        : "'.$_SESSION['user_id'].'",
                    "organization_id"   : "'.$organization_id.'",
                    "student_ids"       : '.json_encode($students_assigned).',
                    "face_id"           : "",
                    "info"              : {
                        "first_name": "'.$_POST['first_name'].'",
                        "last_name" : "'.$_POST['last_name'].'",
                        "tel_num"   : "'.$_POST['tel_num'].'"
                    }
                }
            ');
        }
        else{
            $item = $marshaler->marshalJson('
                {
                    "user_id"           : "'.$user_id.'",
                    "user_internal_id"  : "'.getInternalId("G", count($users)).'",
                    "created_at"        : "' .time(). '",
                    "updated_at"        : "' .time(). '",
                    "deleted_at"        : "",
                    "email"             : "'.$_POST['email'].'",
                    "verified_at"       : "",
                    "password"          : "'.md5($user_token.$_POST['new_password']).'",
                    "token"             : "'.$user_token.'",
                    "user_type"         : 0,
                    "message_token"     : "",
                    "credential"        : "",
                    "family_ids"        : {},
                    "created_by"        : "'.$_SESSION['user_id'].'",
                    "organization_id"   : "'.$organization_id.'",
                    "student_ids"       : {},
                    "face_id"           : "",
                    "info"              : {
                        "first_name": "'.$_POST['first_name'].'",
                        "last_name" : "'.$_POST['last_name'].'",
                        "tel_num"   : "'.$_POST['tel_num'].'"
                    }
                }
            ');
        }   
        $params = [
            'TableName' => "Users",
            'Item' => $item
        ];
        add_item($params, $dynamodb);

        // update user into organization
        $key = $marshaler->marshalJson('
                {
                    "organization_id"      : "'.$organization_id.'"
                }
            ');

        $eav = $marshaler->marshalJson('
                {
                    ":uids"     : "'.$user_id.'"
                }
            ');

        $updateExpression = 'set user_ids.#uid = :uids';

        $params = [
            'TableName' => 'Organizations',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeValues'=> $eav,
            'ExpressionAttributeNames' => ['#uid' => $user_id],
            'ReturnValues' => 'ALL_NEW'
        ];
        $result = update_item($params, $dynamodb);

        // loop update student's guardians ids
        foreach($students_assigned as $student_key => $student_value){
            $key = $marshaler->marshalJson('
                {
                    "student_id"      : "'.$student_key.'"
                }
            ');

            $eav = $marshaler->marshalJson('
                {
                    ":guardian_ids"     : "'.$user_id.'"
                }
            ');

            $updateExpression = 'set guardian_ids.#guid = :guardian_ids';
            $params = [
                'TableName' => 'Students',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ExpressionAttributeNames'=> ['#guid' => $user_id],
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }

        $_SESSION['class_added'] = true;

        $full_name = $_POST['last_name'].' '.$_POST['first_name'];
        
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        if(array_values($organization['mailing'])[0])
        mailTo($_POST['email'], $full_name, $user_token, "http://" . $_SERVER['SERVER_NAME'] , $mail);
        header('Location: userGuardian.php');
    }
    else if(isset($_POST['type']) && $_POST['type'] == "edit" && isset($_POST['id'])){
        $students_assigned = array();
        $user_id = $_GET['id'];
        $guardian = $guardian_arr[$user_id];
        
        $need_remove_student = isset(array_values($guardian['student_ids'])[0]) ? array_values($guardian['student_ids'])[0] : array();
        
        if(isset($_POST["student_ids"])){
            foreach ($_POST["student_ids"] as $key => $value) {
                if($value === "") continue;
                if(!isset($student_arr[$value]) || isset($students_assigned[$value])) continue;
                if(isset($need_remove_student[$value])) unset($need_remove_student[$value]);
                $students_assigned[$value] = $value;
            }
        }

        foreach ($need_remove_student as $key => $value) {
            $need_remove_student_id = array_values($value)[0];
            // unset student's guardian ids
            $key = $marshaler->marshalJson('
                    {
                        "student_id"      : "'.$need_remove_student_id.'"
                    }
                ');
            $updateExpression = 'remove guardian_ids.#guids';
            $params = [
                'TableName' => 'Students',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeNames'=> ["#guids" => $user_id],
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }

        // update guardian new info
        $key = $marshaler->marshalJson('
                {
                    "user_id"      : "'.$user_id.'"
                }
            ');

        if(empty($_POST['new_password'])){
            if(empty($students_assigned)){
                $eav = $marshaler->marshalJson('
                    {
                        ":student_ids"     : {},
                        ":last_name"       : "'.$_POST['last_name'].'",
                        ":first_name"      : "'.$_POST['first_name'].'",
                        ":tel_num"         : "'.$_POST['tel_num'].'"
                    }
                ');
            }   
            else{
                $eav = $marshaler->marshalJson('
                    {
                        ":student_ids"     : '.json_encode($students_assigned).',
                        ":last_name"       : "'.$_POST['last_name'].'",
                        ":first_name"      : "'.$_POST['first_name'].'",
                        ":tel_num"         : "'.$_POST['tel_num'].'"
                    }
                ');
            }   
            
            $updateExpression = 'set student_ids = :student_ids, info.#ln = :last_name, info.#fn = :first_name, info.#tn = :tel_num';
            $params = [
                'TableName' => 'Users',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ExpressionAttributeNames' => ['#ln' => 'last_name', '#fn' => 'first_name', '#tn' => 'tel_num'],
                'ReturnValues' => 'ALL_NEW'
            ];
            
            $result = update_item($params, $dynamodb);
        }
        else{
            $new_token = generateToken();
            if(empty($students_assigned)){
                $eav = $marshaler->marshalJson('
                    {
                        ":student_ids"     : {},
                        ":last_name"       : "'.$_POST['last_name'].'",
                        ":first_name"      : "'.$_POST['first_name'].'",
                        ":tel_num"         : "'.$_POST['tel_num'].'",
                        ":password"        : "'.md5($new_token.$_POST['new_password']).'",
                        ":token"           : "'.$new_token.'"
                    }
                ');
            }    
            else{
                $eav = $marshaler->marshalJson('
                    {
                        ":student_ids"     : '.json_encode($students_assigned).',
                        ":last_name"       : "'.$_POST['last_name'].'",
                        ":first_name"      : "'.$_POST['first_name'].'",
                        ":tel_num"         : "'.$_POST['tel_num'].'",
                        ":password"        : "'.md5($new_token.$_POST['new_password']).'",
                        ":token"           : "'.$new_token.'"
                    }
                ');
            }    
            $updateExpression = 'set student_ids = :student_ids, info.#ln = :last_name, info.#fn = :first_name, info.#tn = :tel_num, password = :password, #tk = :token';
            $params = [
                'TableName' => 'Users',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ExpressionAttributeNames' => ['#ln' => 'last_name', '#fn' => 'first_name', '#tn' => 'tel_num', '#tk' => 'token'],
                'ReturnValues' => 'ALL_NEW'
            ];
            
            $result = update_item($params, $dynamodb);
        }

        // loop update student's guardians ids
        foreach($students_assigned as $student_key => $student_value){
            $key = $marshaler->marshalJson('
                {
                    "student_id"      : "'.$student_key.'"
                }
            ');

            $eav = $marshaler->marshalJson('
                {
                    ":guardian_ids"     : "'.$user_id.'"
                }
            ');

            $updateExpression = 'set guardian_ids.#guid = :guardian_ids';
            $params = [
                'TableName' => 'Students',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ExpressionAttributeNames'=> ['#guid' => $user_id],
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }

        $class_arr      = getClassesData($classes, $marshaler, $dynamodb);
        $student_arr    = getStudentsData($students, $marshaler, $dynamodb);
        $guardian_arr   = getGuardiansData($users, $marshaler, $dynamodb);
        $user_arr       = getUsersData($users, $marshaler, $dynamodb);
    }
    else if(isset($_GET['type']) && $_GET['type'] == "delete" && isset($_GET['id'])){
        $user_id = $_GET['id'];
        $guardian = $guardian_arr[$user_id];

        if(array_values($guardian['user_type'])[0] == "0"){
            $family_ids = array_values($guardian['family_ids'])[0];
            $family_ids[$_GET['id']] = array("S" => $_GET['id']);

            $student_related = getAllStudentRelated($family_ids, $dynamodb, $marshaler, $guardian_arr);
            removeGuardianIdFromStudent($family_ids, $student_related, $dynamodb, $marshaler);
            updateAllFamily($family_ids, $dynamodb, $marshaler);

            $_SESSION['class_delete_main'] = true;
        }
        else{
            updateCreator(array_values($guardian['created_by'])[0], $user_id, $dynamodb, $marshaler);
            $need_remove_student = isset(array_values($guardian['student_ids'])[0]) ? array_values($guardian['student_ids'])[0] : array();
        
            // loop to unset student's guardian id
            foreach($need_remove_student as $key => $value){
                $need_remove_student_id = array_values($value)[0];

                $key = $marshaler->marshalJson('
                        {
                            "student_id"      : "'.$need_remove_student_id.'"
                        }
                    ');

                $updateExpression = 'remove guardian_ids.#guid';
                $params = [
                    'TableName' => 'Students',
                    'Key' => $key,
                    'UpdateExpression' => $updateExpression,
                    'ExpressionAttributeNames'=> ['#guid' => $user_id],
                    'ReturnValues' => 'ALL_NEW'
                ];
                $result = update_item($params, $dynamodb);
            }

            // update user's deleted_at and unset student_ids
            $key = $marshaler->marshalJson('
                    {
                        "user_id"      : "'.$user_id.'"
                    }
                ');

            $eav = $marshaler->marshalJson('
                {
                    ":student_ids"     : {},
                    ":deleted_at"      : "'.time().'"
                }
            ');

            $updateExpression = 'set student_ids = :student_ids, deleted_at = :deleted_at';
            $params = [
                'TableName' => 'Users',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);

            $_SESSION['class_delete'] = true;
        }
        header('Location: userGuardian.php');
    }
    else{
        echo '<style type="text/css">
        .alert {
            display: none;
        }
        </style>';
    }

    if(isset($_GET['id']) && $_GET['id'] !== ""){
        $guardian = $guardian_arr[$_GET['id']];
        
        $request_arr = getRequestsData($students, $marshaler, $dynamodb);
        foreach ($request_arr as $student_key => $value) {
            if(isset(array_values($guardian['student_ids'])[0][$student_key])){

                foreach($value as $date => $value){
                    $request_date = array_values($value['date'])[0];

                    foreach($value['request'] as $key => $value){

                        foreach($value as $key => $value){

                            if(array_values(array_values($value)[0]['user_id'])[0] == $_GET['id']){

                                if(!isset($user_request[$student_key][$request_date]))
                                    $user_request[$student_key][$request_date] = array();

                                $user_request[$student_key][$request_date] = array(
                                    "user_id" => array_values($value)[0]['user_id'],
                                    "request_time" => array_values($value)[0]['request_time'],
                                    "release_time" => array_values($value)[0]['release_time'],
                                    "status" => ""
                                );

                                $request_time = date('H:i', strtotime(array_values(array_values($value)[0]["request_time"])[0]));
                                
                                if($request_time > $early_check_out_time && $request_time < $check_out_time){
                                    $overall_requests["on_time_request"]++;
                                    $user_request[$student_key][$request_date]['status'] = "On Time";
                                }
                                else if($request_time < $early_check_out_time){
                                    $overall_requests["early_request"]++;
                                    $user_request[$student_key][$request_date]['status'] = "Early";
                                }
                                else{
                                    $overall_requests["late_request"]++;
                                    $user_request[$student_key][$request_date]['status'] = "Late";
                                }
                            }
                        }
                    }
                }
            }
        }
    }
?>

<!DOCTYPE html>
<html>
<head>
<title>User - Guardian</title>
<?php include "include/heading.php";?>
<style>
    .table>:not(caption)>*>* {
        border-bottom-width: 0;
    }
</style>
</head>
<body>
<?php include "include/navbar.php";?>

<div class="main">
    <div class="row">
        <div class="col-12 heading">
            <p><i class="fas fa-user-tie" style="margin-right: 10px;"></i>Guardian</p>
        </div>
    </div>

    <div class="alert alert-success" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Successfully Update</h4>
    </div>
    
    <div class="row" style="padding: 20px 20px 20px 40px; display: flex; justify-content: space-around;">
        <div class="col-5">
            <div class="card">
            <div class="card-header">Overall Request</div>
                <div class="card-body" style="height: 100%">
                    <div class="chartjs-size-monitor" style="position: absolute; left: 0px; top: 0px; right: 0px; bottom: 0px; overflow: hidden; pointer-events: none; visibility: hidden; z-index: -1;">
                        <div class="chartjs-size-monitor-expand" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;">
                            <div style="position:absolute;width:1000000px;height:1000000px;left:0;top:0"></div>
                        </div>
                        <div class="chartjs-size-monitor-shrink" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;">
                            <div style="position:absolute;width:200%;height:200%;left:0; top:0"></div>
                        </div>
                    </div> <canvas id="pickup_chart" width="299" height="200" class="chartjs-render-monitor" style="display: block; width: 299px; height: 200px;"></canvas>
                </div>
            </div>
        </div>
    
        <div class="col-6">
            <form class="needs-validation" action="" method="POST" onsubmit="<?php if($guardian === NULL) echo 'myValidation();';?>" novalidate autocomplete="off">
                <div class="form-row" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                  <div class="col-6">
                    <label for="last_name">Last Name</label>
                    <div class="input-group">
                      <input type="hidden" name="id" value="<?php if($guardian !== NULL){echo array_values($guardian['user_id'])[0]; }?>">

                      <input type="text" name="last_name" value="<?php if($guardian !== NULL){echo array_values(array_values($guardian['info'])[0]['last_name'])[0];} ?>" class="form-control" id="last_name" placeholder="Last Name" aria-describedby="inputGroupPrepend" autocomplete="off" required>
                      <div class="invalid-feedback">
                        Last Name Cant Be Empty.
                      </div>
                    </div>
                  </div>

                  <div class="col-6">
                    <label for="first_name">First Name</label>
                    <div class="input-group">
                      <input type="text" name="first_name" value="<?php if($guardian !== NULL){echo array_values(array_values($guardian['info'])[0]['first_name'])[0];} ?>" class="form-control" id="first_name" placeholder="First Name" aria-describedby="inputGroupPrepend" autocomplete="off" required>
                      <div class="invalid-feedback">
                        First Name Cant Be Empty.
                      </div>
                    </div>
                  </div>
                </div>

                <div class="form-row" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                  <div class="col-6">
                    <label for="tel_num">Telephone Number</label>
                    <div class="input-group">
                      <input type="tel" name="tel_num" value="<?php if($guardian !== NULL){echo array_values(array_values($guardian['info'])[0]['tel_num'])[0];} ?>" class="form-control" id="tel_num" placeholder="012-5796665" aria-describedby="inputGroupPrepend" pattern="[0-9]{3}-[0-9]{7,}" autocomplete="off" required>
                      <div class="invalid-feedback">
                        Invalid Telephone Number
                      </div>
                    </div>
                  </div>

                  <div class="col-6">
                    <label for="tel_num">Created By</label>
                    <div class="input-group">
                        <?php 
                            if($guardian !== NULL){
                                if(isset($guardian['created_by']) && isset($extracted_guardian_name[array_values($guardian['created_by'])[0]])){
                                    echo '<a href="userGuardianForm.php?id='.array_values($guardian['created_by'])[0].'" class="form-control link" target="blank" readonly>'.$extracted_guardian_name[array_values($guardian['created_by'])[0]].'</a>'; 
                                }    
                                else   
                                    echo '<p class="form-control link" readonly>Admin</p>';
                            }
                            else   
                                echo '<p class="form-control link" readonly>Admin</p>';
                        ?>
                    </div>
                  </div>
                </div>

                <div class="form-row">
                  <div class="col-md-12 mb-3">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" value="<?php if($guardian !== NULL){echo array_values($guardian['email'])[0];} ?>" <?php if($guardian !== NULL){echo 'readonly ';} ?> class="form-control" id="email" required autocomplete="off">
                    <div class="invalid-feedback">
                        Email is invalid.
                    </div>
                    <div class="custom-invalid-feedback" id="email_invalid">
                          Email is been taken.
                    </div>
                  </div>
                </div>


                <div class="form-row">
                  <div class="col-md-12 mb-3">
                    <label for="new_password">New Password</label>
                    <input type="password" name="new_password" class="form-control" id="new_password" <?php if($guardian === NULL){echo 'required ';} ?>>
                    <div class="invalid-feedback">
                        Password cannot be empty.
                    </div>
                  </div>
                </div>

                <table id="student_table" class="table order-list form-row">
                    <thead>
                        <tr>
                            <td>Students</td>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align: center;">
                                <input type="button" class="btn btn-lg btn-block btn-primary" id="addrow" value="Add Student" />
                                <i id="refresh" class="fas fa-sync-alt btn btn-lg btn-block" title="Sync"></i>
                            </td>
                        </tr>
                        <tr>
                        </tr>
                    </tfoot>
                </table>
                <?php 
                    if($guardian !== NULL){
                        echo '<button class="btn btn-warning" type="submit" style="padding: 5px 20px;" name="type" value="edit">Edit</button>';
                    }
                    else{
                        echo '<button class="btn btn-success" type="submit" style="padding: 5px 20px;" name="type" value="add">Submit</button>';
                    }
                ?>
              </form>
        </div>
    </div>

    <div class="row" style="">
        <div class="col" style="padding-left: 30px;">
            <h1 style="margin-bottom: 20px;">Pick Up Request</h1>
        </div>
    </div>

    <div style="padding: 30px;">
        <table id="request" class="display">  
            <thead>  
                <tr>  
                    <th>#</th>  
                    <th>Student Name</th>  
                    <th>Class</th>  
                    <th>Date</th>
                    <th>Request Time</th>
                    <th>Release Time</th>
                    <th>Status</th>
                </tr>  
            </thead>  
            <tbody>  
                <?php
                    $num = 1;
                    foreach ($user_request as $student_key => $date_value) {
                        foreach($date_value as $date_key => $requests){
                                echo '
                                <tr>
                                    <td>'.$num++.'</td>
                                    <td><a href="userStudentForm.php?id='.$student_key.'" class="link" target="blank">'.array_values($student_arr[$student_key]['last_name'])[0].' '.array_values($student_arr[$student_key]['first_name'])[0].'</a></td>
                                    <td>'.array_values($class_arr[array_values($student_arr[$student_key]['class_id'])[0]]['name'])[0].'</td>
                                    <td>'.date('d-m-Y', strtotime($date_key)).'</td>
                                    <td>'.array_values($requests['request_time'])[0].'</td>
                                    <td>'.array_values($requests['release_time'])[0].'</td>
                                    <td>'.$requests['status'].'</td></tr>';
                        }
                    }
                ?>
            </tbody>  
        </table>
    </div>

</div>
</div>
<script src='https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.1.4/Chart.bundle.min.js'></script>
<script>
// Example starter JavaScript for disabling form submissions if there are invalid fields
    (function() {
        'use strict';
        window.addEventListener('load', function() {
        // Fetch all the forms we want to apply custom Bootstrap validation styles to
            var forms = document.getElementsByClassName('needs-validation');
            // Loop over them and prevent submission
            var validation = Array.prototype.filter.call(forms, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        }, false);
    })();

    function textOnChange(param){
        var textChanger = param;
        textChanger.addEventListener("input", function(){
        myValidation();
        })
    }

    <?php
        if(($guardian === NULL))
        echo "textOnChange(document.getElementById('email'));";
    ?>

    function myValidation() {
        var new_email = document.getElementById('email').value;
        var users_list = <?php echo json_encode($extracted_guardian_arr);?>;
        var invalid_email = false;

        if(new_email === ""){
            document.getElementById("email_invalid").style.display = "none";
        }

        for (const [key, value] of Object.entries(users_list)) {
            if(new_email == value){
                invalid_email = true;
                break;
            }
        }

        if(invalid_email){
            event.preventDefault();
            document.getElementById("email_invalid").style.display = "block";
            document.getElementById("email").classList.add("custom-invalid-field");
            return false;
        }
        else{
            document.getElementById("email_invalid").style.display = "none";
            document.getElementById("email").classList.remove("custom-invalid-field");
        }
        return true;
    }
    

    $(document).ready(function () {
        $('#request').dataTable();

        var counter = 0;
        var student_assigned = <?php if($guardian !== NULL) echo json_encode(array_values($guardian['student_ids'])[0]); else echo "[]"?>;

        if(student_assigned.length != 0){
            for (const [key, value] of Object.entries(student_assigned)) {
                var newRow = $("<tr class='row' style='display: table-row'>");
                var cols = "";

                var student_list = <?php echo json_encode($extracted_student_arr);?>;
                var class_list = <?php echo json_encode($extracted_class_arr);?>;

                cols += '<td class="col-5" style="padding-left: 0;">'+
                '<input list="student" class="form-control" name="student_ids[]" autocomplete="off" value="' + key + '">'+
                    '<datalist id="student">';

                for (const [key, value] of Object.entries(student_list)) {
                    if(value["class_id"] === "")
                        cols += '<option value="'+ key +'">' + value["student_internal_id"] + ' - '+ value["name"];
                    else
                        cols += '<option value="'+ key +'">' + value["student_internal_id"] + ' - '+ value["name"] + ' - ' + class_list[value["class_id"]];
                    
                }

                cols +='</datalist></td>';
                
                cols += '<td class="col-5"><input class="form-control" name="student_name[]" readonly></td>';
                cols += '<td class="col-1" style="padding-right: 0;"><input type="button" class="ibtnDel btn btn-md btn-danger "  value="Delete"></td>';
                cols += '<td class="col-1" style="padding-right: 0;"><a href="" target="_blank" name="student_link[]" class="btn btn-lg btn-block disabled" title="Open In New Tab"><i class="fas fa-external-link-alt alt_link"></i></a></td>';
                newRow.append(cols);
                $("table.order-list").append(newRow);
                counter++;
            }
        }

        $("#addrow").on("click", function () {
            var newRow = $("<tr class='row' style='display: table-row'>");
            var cols = "";

            var student_list = <?php echo json_encode($extracted_student_arr);?>;
            var class_list = <?php echo json_encode($extracted_class_arr);?>;

            cols += '<td class="col-5" style="padding-left: 0;">'+
            '<input list="student" class="form-control" name="student_ids[]" autocomplete="off">'+
                '<datalist id="student">';

            for (const [key, value] of Object.entries(student_list)) {
                if(value["class_id"] === "")
                    cols += '<option value="'+ key +'">' + value["student_internal_id"] + ' - '+ value["name"];
                else
                    cols += '<option value="'+ key +'">' + value["student_internal_id"] + ' - '+ value["name"] + ' - ' + class_list[value["class_id"]];
                
            }

            cols +='</datalist></td>';

            cols += '<td class="col-5"><input class="form-control" name="student_name[]" readonly></td>';
            cols += '<td class="col-1" style="padding-right: 0;"><input type="button" class="ibtnDel btn btn-md btn-danger "  value="Delete"></td>';
            cols += '<td class="col-1" style="padding-right: 0;"><a href="" target="_blank" name="student_link[]" class="btn btn-lg btn-block disabled" title="Open In New Tab"><i class="fas fa-external-link-alt alt_link"></i></a></td>';
            newRow.append(cols);
            $("table.order-list").append(newRow);
            counter++;
        });

        $("table.order-list").on("click", ".ibtnDel", function (event) {
            $(this).closest("tr").remove();       
            counter -= 1
        });

        var student_list = <?php echo json_encode($extracted_student_arr);?>;
        var class_list = <?php echo json_encode($extracted_class_arr);?>;

        var ids = document.getElementsByName('student_ids[]');
        var names = document.getElementsByName('student_name[]');
        var links = document.getElementsByName('student_link[]');

        for (var i = 0; i <ids.length; i++) {
            if(student_list[ids[i].value]['class_id'] === ''){
                names[i].value = student_list[ids[i].value]['name'];
            }    
            else{
                names[i].value = student_list[ids[i].value]['name'] + ' - ' + class_list[student_list[ids[i].value]['class_id']];
            }   
            var url = "userStudentForm.php?id=";
            links[i].setAttribute('href', url+ids[i].value);
            links[i].classList.remove("disabled"); 
        }

        $("#refresh").on("click", function () {
            var ids = document.getElementsByName('student_ids[]');
            var names = document.getElementsByName('student_name[]');
            var links = document.getElementsByName('student_link[]');
            for (var i = 0; i <ids.length; i++) {
                if(student_list[ids[i].value] !== undefined){
                    if(student_list[ids[i].value]['class_id'] === '')
                        names[i].value = student_list[ids[i].value]['name'];
                    else
                        names[i].value = student_list[ids[i].value]['name'] + ' - ' + class_list[student_list[ids[i].value]['class_id']];

                    var url = "userStudentForm.php?id=";
                    links[i].setAttribute('href', url+ids[i].value);
                    links[i].classList.remove("disabled");
                }
                else{
                    names[i].value = "";
                    links[i].setAttribute('href', "");
                    links[i].classList.add("disabled");
                }
            }
        });
    });


    $(document).ready(function (){
        var overall_requests = <?php echo json_encode($overall_requests); ?>;

        var ctx = $("#pickup_chart");
        var myLineChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ["On Time", "Early", "Late"],
                datasets: [{
                    data: [overall_requests['on_time_request'], overall_requests['early_request'], overall_requests['late_request']],
                    backgroundColor: ["rgba(100, 255, 0, 0.5)", "rgba(255, 193, 7, 0.6)", "rgba(255, 0, 0, 0.5)"]
                }]
            }
        });
    });
</script>

</body>
</html>