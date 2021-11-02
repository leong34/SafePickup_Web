<?php
    include "include/session.php";
    include "include/user_Functions.php";
    include "include/dynamoDB_functions.php";

    check_session($dynamodb, $marshaler);

    $organization_id = $_SESSION['organization_id'];
    $organization   = getOrganization($organization_id, $dynamodb, $marshaler);

    $students       = array_values($organization['student_ids']);
    $classes        = array_values($organization['class_ids']);
    $users          = array_values($organization['user_ids'])[0];

    $class_arr      = getClassesData($classes, $marshaler, $dynamodb);
    $student_arr    = getStudentsData($students, $marshaler, $dynamodb);
    $guardian_arr   = getGuardiansData($users, $marshaler, $dynamodb);
    $attendance_arr = getAttendancesData($students, $marshaler, $dynamodb);
    $users_arr      = getUsersData($users, $marshaler, $dynamodb);

    $check_in_time = strtotime(array_values($organization['check_in_time'])[0]);
    $check_out_time = strtotime(array_values($organization['check_out_time'])[0]);
    $late_threshold = array_values($organization['late_threshold'])[0];

    $check_in_time = date("H:i", strtotime('+'.$late_threshold.' minutes', $check_in_time));
    $check_out_time = date("H:i", strtotime('+'.$late_threshold.' minutes', $check_out_time));

    $users_name = array();
    foreach($users_arr as $key => $value){
        if(array_values($value['user_type'])[0] !== "1" && empty(array_values($value['deleted_at'])[0]))
        $users_name[$key] = array_values(array_values($value['info'])[0]['last_name'])[0].' '.array_values(array_values($value['info'])[0]['first_name'])[0];
    }

    $extracted_guardian_arr = array();
    foreach($guardian_arr as $key => $value){
        if(!empty(array_values($value['deleted_at'])[0]))continue;
        $extracted_guardian_arr[$key]['name'] = array_values(array_values($value['info'])[0]['last_name'])[0].' '.array_values(array_values($value['info'])[0]['first_name'])[0];
        $extracted_guardian_arr[$key]['user_internal_id'] = array_values($value['user_internal_id'])[0];
    }

    $student = NULL;
    $present = 0;
    $absent = 0;

    if(isset($_GET['type']) && $_GET['type'] == "add"){
        $guardians_assigned = array();
        if(isset($_GET["guardian_ids"])){
            foreach ($_GET["guardian_ids"] as $key => $value) {
                if($value === "") continue;
                if(!isset($guardian_arr[$value]) || isset($guardians_assigned[$value])) continue;
                $guardians_assigned[$value] = $value;
            }
        }

        // create new student
        $student_id = "STUD".time()."".generateToken()."";
        if(!empty($guardians_assigned)){
            $item = $marshaler->marshalJson('
                {
                    "student_id"            : "'.$student_id.'",
                    "student_internal_id"   : "'.getInternalId("P", count($students[0])).'",
                    "created_at"            : "' .time(). '",
                    "updated_at"            : "' .time(). '",
                    "deleted_at"            : "",
                    "first_name"            : "'.$_GET['first_name'].'",
                    "last_name"             : "'.$_GET['last_name'].'",
                    "age"                   : "'.$_GET['age'].'",
                    "gender"                : '.$_GET['gender'].',
                    "class_id"              : "'.$_GET['class_id'].'",
                    "organization_id"       : "'.$organization_id.'",
                    "guardian_ids"          : '.json_encode($guardians_assigned).',
                    "status"                : 0
                }
            ');
        }
        else{
            $item = $marshaler->marshalJson('
                {
                    "student_id"        : "'.$student_id.'",
                    "student_internal_id"   : "'.getInternalId("P", count($students[0])).'",
                    "created_at"        : "' .time(). '",
                    "updated_at"        : "' .time(). '",
                    "deleted_at"        : "",
                    "first_name"        : "'.$_GET['first_name'].'",
                    "last_name"         : "'.$_GET['last_name'].'",
                    "age"               : "'.$_GET['age'].'",
                    "gender"            : '.$_GET['gender'].',
                    "class_id"          : "'.$_GET['class_id'].'",
                    "organization_id"   : "'.$organization_id.'",
                    "guardian_ids"      : {},
                    "status"            : 0
                }
            ');
        }
        
        $params = [
            'TableName' => "Students",
            'Item' => $item
        ];
        add_item($params, $dynamodb);

        // update student into organization
        $key = $marshaler->marshalJson('
                {
                    "organization_id"      : "'.$organization_id.'"
                }
            ');

        $eav = $marshaler->marshalJson('
                {
                    ":suids"     : "'.$student_id.'"
                }
            ');

        $updateExpression = 'set student_ids.#suid = :suids';

        $params = [
            'TableName' => 'Organizations',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeValues'=> $eav,
            'ExpressionAttributeNames' => ['#suid' => $student_id],
            'ReturnValues' => 'ALL_NEW'
        ];
        $result = update_item($params, $dynamodb);

        // loop update guardian's student ids
        foreach($guardians_assigned as $guardian_key => $guardian_value){
            $key = $marshaler->marshalJson('
                {
                    "user_id"      : "'.$guardian_key.'"
                }
            ');

            $eav = $marshaler->marshalJson('
                {
                    ":student_id"     : "'.$student_id.'"
                }
            ');

            $updateExpression = 'set student_ids.#suid = :student_id';
            $params = [
                'TableName' => 'Users',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ExpressionAttributeNames'=> ['#suid' => $student_id],
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }

        // update student into class
        if(!empty($_GET['class_id'])){
            $key = $marshaler->marshalJson('
                    {
                        "class_id"      : "'.$_GET['class_id'].'"
                    }
                ');
            $eav = $marshaler->marshalJson('
                    {
                        ":suids"     : "'.$student_id.'"
                    }
                ');

            $updateExpression = 'set student_ids.#suid = :suids';

            $params = [
                'TableName' => 'Classes',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ExpressionAttributeNames' => ['#suid' => $student_id],
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }
        
        $_SESSION['class_added'] = true;
        header('Location: userStudent.php');
    }
    else if(isset($_GET['type']) && $_GET['type'] == "edit" && isset($_GET['id'])){
        $guardians_assigned = array();
        $student_id = $_GET['id'];
        $student = $student_arr[$student_id];
        $student_old_class_id = array_values($student['class_id'])[0];
        
        $need_remove_guardian = isset(array_values($student['guardian_ids'])[0]) ? array_values($student['guardian_ids'])[0] : array();

        if(isset($_GET["guardian_ids"])){
            foreach ($_GET["guardian_ids"] as $key => $value) {
                if($value === "") continue;
                if(!isset($guardian_arr[$value]) || isset($guardians_assigned[$value])) continue;
                if(isset($need_remove_guardian[$value])) unset($need_remove_guardian[$value]);
                $guardians_assigned[$value] = $value;
            }
        }

        foreach ($need_remove_guardian as $key => $value) {
            $need_remove_guardian_id = array_values($value)[0];
            // unset guardian's student ids
            $key = $marshaler->marshalJson('
                    {
                        "user_id"      : "'.$need_remove_guardian_id.'"
                    }
                ');
            $updateExpression = 'remove student_ids.#suids';
            $params = [
                'TableName' => 'Users',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeNames'=> ["#suids" => $student_id],
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }

        // update student new data
        $key = $marshaler->marshalJson('
                {
                    "student_id"      : "'.$student_id.'"
                }
            ');

        if(empty($guardians_assigned)){
            $eav = $marshaler->marshalJson('
                {
                    ":guardian_ids"    : {},
                    ":last_name"       : "'.$_GET['last_name'].'",
                    ":first_name"      : "'.$_GET['first_name'].'",
                    ":age"             : "'.$_GET['age'].'",
                    ":gender"          : '.$_GET['gender'].',
                    ":class_id"        : "'.$_GET['class_id'].'"
                }
            ');
        }   
        else{
            $eav = $marshaler->marshalJson('
                {
                    ":guardian_ids"    : '.json_encode($guardians_assigned).',
                    ":last_name"       : "'.$_GET['last_name'].'",
                    ":first_name"      : "'.$_GET['first_name'].'",
                    ":age"             : "'.$_GET['age'].'",
                    ":gender"          : '.$_GET['gender'].',
                    ":class_id"        : "'.$_GET['class_id'].'"
                }
            ');
        }   
        
        $updateExpression = 'set guardian_ids = :guardian_ids, last_name = :last_name, first_name = :first_name, age = :age, gender = :gender, class_id = :class_id';
        $params = [
            'TableName' => 'Students',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeValues'=> $eav,
            'ReturnValues' => 'ALL_NEW'
        ];
        
        $result = update_item($params, $dynamodb);

        // update class's student id
        if($student_old_class_id != $_GET['class_id']){
            // unset old class's student id
            if(!empty($student_old_class_id)){
                $key = $marshaler->marshalJson('
                    {
                        "class_id"      : "'.$student_old_class_id.'"
                    }
                ');

                $updateExpression = 'remove student_ids.#suid';
                $params = [
                    'TableName' => 'Classes',
                    'Key' => $key,
                    'UpdateExpression' => $updateExpression,
                    'ExpressionAttributeNames'=> ['#suid' => $student_id],
                    'ReturnValues' => 'ALL_NEW'
                ];
                $result = update_item($params, $dynamodb);
            }
            // set new class's student id
            if(!empty($_GET['class_id'])){
                $key = $marshaler->marshalJson('
                    {
                        "class_id"      : "'.$_GET['class_id'].'"
                    }
                ');
                $eav = $marshaler->marshalJson('
                    {
                        ":student_id"    : "'.$student_id.'"
                    }
                ');

                $updateExpression = 'set student_ids.#suid = :student_id';
                $params = [
                    'TableName' => 'Classes',
                    'Key' => $key,
                    'UpdateExpression' => $updateExpression,
                    'ExpressionAttributeValues'=> $eav,
                    'ExpressionAttributeNames'=> ['#suid' => $student_id],
                    'ReturnValues' => 'ALL_NEW'
                ];
                $result = update_item($params, $dynamodb);
            }
            
        }


        // loop update guardian's student ids
        foreach($guardians_assigned as $guardian_key => $guardian_value){
            $key = $marshaler->marshalJson('
                {
                    "user_id"      : "'.$guardian_key.'"
                }
            ');

            $eav = $marshaler->marshalJson('
                {
                    ":student_id"     : "'.$student_id.'"
                }
            ');

            $updateExpression = 'set student_ids.#suid = :student_id';
            $params = [
                'TableName' => 'Users',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ExpressionAttributeNames'=> ['#suid' => $student_id],
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }

        $class_arr      = getClassesData($classes, $marshaler, $dynamodb);
        $student_arr    = getStudentsData($students, $marshaler, $dynamodb);
        $guardian_arr   = getGuardiansData($users, $marshaler, $dynamodb);
    }
    else if(isset($_GET['type']) && $_GET['type'] == "delete" && isset($_GET['id'])){
        $student_id = $_GET['id'];
        $student = $student_arr[$student_id];

        $need_remove_user = isset(array_values($student['guardian_ids'])[0]) ? array_values($student['guardian_ids'])[0] : array();
        
        // loop to unset user's student id
        foreach($need_remove_user as $key => $value){
            $need_remove_user_id = array_values($value)[0];

            $key = $marshaler->marshalJson('
                    {
                        "user_id"      : "'.$need_remove_user_id.'"
                    }
                ');

            $updateExpression = 'remove student_ids.#suid';
            $params = [
                'TableName' => 'Users',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeNames'=> ['#suid' => $student_id],
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }

        // update student's deleted_at and unset guardian_ids, class_id
        $key = $marshaler->marshalJson('
                {
                    "student_id"      : "'.$student_id.'"
                }
            ');

        $eav = $marshaler->marshalJson('
            {
                ":guardian_ids"     : {},
                ":deleted_at"       : "'.time().'",
                ":class_id"         : ""
            }
        ');

        $eav = $marshaler->marshalJson('
            {
                ":guardian_ids"     : {},
                ":deleted_at"       : "'.time().'"
            }
        ');

        $updateExpression = 'set guardian_ids = :guardian_ids, deleted_at = :deleted_at, class_id = :class_id';
        $updateExpression = 'set guardian_ids = :guardian_ids, deleted_at = :deleted_at';
        $params = [
            'TableName' => 'Students',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeValues'=> $eav,
            'ReturnValues' => 'ALL_NEW'
        ];
        $result = update_item($params, $dynamodb);

        // remove student from class
        if(!empty(array_values($student['class_id'])[0])){
            $key = $marshaler->marshalJson('
                {
                    "class_id"      : "'.array_values($student['class_id'])[0].'"
                }
            ');

            $updateExpression = 'remove student_ids.#suid';
            $params = [
                'TableName' => 'Classes',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeNames'=> ['#suid' => $student_id],
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }

        // remove student from organization
        $key = $marshaler->marshalJson('
                {
                    "organization_id"      : "'.$organization_id.'"
                }
            ');

        $updateExpression = 'remove student_ids.#suid';
        $params = [
            'TableName' => 'Organizations',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeNames'=> ['#suid' => $student_id],
            'ReturnValues' => 'ALL_NEW'
        ];
        
        // $result = update_item($params, $dynamodb);

        $_SESSION['class_delete'] = true;
        header('Location: userStudent.php');
    }
    else{
        echo '<style type="text/css">
        .alert {
            display: none;
        }
        </style>';
    }
    if(isset($_GET['id']) && $_GET['id'] !== ""){
        $student = $student_arr[$_GET['id']];
        foreach($attendance_arr as $student_key => $date_value){
            foreach ($date_value as $key => $value) {
                if(array_values($value['student_id'])[0] == $_GET['id']){
                    if(array_values($value['type'])[0] == "present"){
                        $present++;
                    }
                    else{
                        $absent++;
                    }
                }
            }
        }
    }
?>

<!DOCTYPE html>
<html>
<head>
<title>User - Student</title>
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
            <p><i class="fas fa-user-graduate" style="margin-right: 10px;"></i>Student</p>
        </div>
    </div>

    <div class="alert alert-success" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Successfully Update</h4>
    </div>
    
    <div class="row" style="padding: 20px 20px 20px 40px; display: flex; justify-content: space-around;">
            <div class="col-5">
                <div class="card">
                <div class="card-header">Overall Attendance</div>
                    <div class="card-body" style="height: 100%">
                        <div class="chartjs-size-monitor" style="position: absolute; left: 0px; top: 0px; right: 0px; bottom: 0px; overflow: hidden; pointer-events: none; visibility: hidden; z-index: -1;">
                            <div class="chartjs-size-monitor-expand" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;">
                                <div style="position:absolute;width:1000000px;height:1000000px;left:0;top:0"></div>
                            </div>
                            <div class="chartjs-size-monitor-shrink" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;">
                                <div style="position:absolute;width:200%;height:200%;left:0; top:0"></div>
                            </div>
                        </div> <canvas id="attendance_chart" width="299" height="200" class="chartjs-render-monitor" style="display: block; width: 299px; height: 200px;"></canvas>
                    </div>
                </div>
            </div>
        <div class="col-6">
            <form class="needs-validation" action="" method="GET" novalidate id="submit_form">
                <div class="form-row" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                  <div class="col-6">
                    <label for="last_name">Last Name</label>
                    <div class="input-group">
                      <input type="hidden" name="id" value="<?php if($student !== NULL){echo array_values($student['student_id'])[0]; }?>">

                      <input type="text" name="last_name" value="<?php if($student !== NULL){echo array_values($student['last_name'])[0];} ?>" class="form-control" id="last_name" placeholder="Last Name" aria-describedby="inputGroupPrepend" autocomplete="off" required>
                      <div class="invalid-feedback">
                        Last Name Cant Be Empty.
                      </div>
                    </div>
                  </div>

                  <div class="col-6">
                    <label for="first_name">First Name</label>
                    <div class="input-group">
                      <input type="text" name="first_name" value="<?php if($student !== NULL){echo array_values($student['first_name'])[0];} ?>" class="form-control" id="first_name" placeholder="First Name" aria-describedby="inputGroupPrepend" autocomplete="off" required>
                      <div class="invalid-feedback">
                        First Name Cant Be Empty.
                      </div>
                    </div>
                  </div>
                </div>

                <div class="form-row" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                  <div class="col-4">
                    <label for="age">Age</label>
                    <div class="input-group">
                      <input type="number" name="age" min="0" max="18" value="<?php if($student !== NULL){echo array_values($student['age'])[0];} ?>" class="form-control" id="age" placeholder="Age" aria-describedby="inputGroupPrepend" autocomplete="off" required>
                      <div class="invalid-feedback">
                        Please Enter a Valid Age Between 0 - 18
                      </div>
                    </div>
                  </div>

                  <div class="col-4">
                    <label for="gender">Gender</label>
                    <select class="form-select" name="gender">
                        <?php
                            if($student !== NULL){
                                if(array_values($student['gender'])[0]){
                                    echo '<option value="1" selected>Boy</option>';
                                    echo '<option value="0">Girl</option>';
                                }
                                else{
                                    echo '<option value="1">Boy</option>';
                                    echo '<option value="0" selected>Girl</option>';
                                }
                            }
                            else{
                                echo '<option value="1">Boy</option>';
                                echo '<option value="0">Girl</option>';
                            }
                        ?>
                    </select>
                  </div>

                  <div class="col-4">
                    <label for="class_id">Class</label>
                    <select class="form-select" name="class_id">
                        <option value="">-</option>
                        <?php
                            $student_class = "";
                            if($student !== NULL){
                                $student_class = array_values($student['class_id'])[0];
                            }
                            
                            foreach($class_arr as $key => $value){
                                if($student_class === $key)
                                    echo '<option value='.$key.' selected>'.array_values($value['name'])[0].'</option>';
                                else
                                    echo '<option value='.$key.'>'.array_values($value['name'])[0].'</option>';
                            }
                        ?>
                    </select>
                  </div>
                </div>

                <table id="student_table" class="table order-list form-row">
                    <thead>
                        <tr>
                            <td>Guardians</td>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align: center;">
                                <input type="button" class="btn btn-lg btn-block btn-primary" id="addrow" value="Add Guardian" />
                                <i id="refresh" class="fas fa-sync-alt btn btn-lg btn-block" title="Sync"></i>
                            </td>
                        </tr>
                        <tr>
                        </tr>
                    </tfoot>
                </table>
                <?php 
                    if($student !== NULL){
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
            <h1 style="margin-bottom: 20px;">Attendance</h1>
        </div>
    </div>

    <div style="padding: 30px;">
        <table id="attendance" class="display">  
            <thead>  
                <tr>  
                    <th>#</th>  
                    <th>Date</th>
                    <th>Attendance</th>
                    <th>Check In Time</th>
                    <th>Check Out Time</th>  
                </tr>  
            </thead>  
            <tbody>  
                <?php
                    $num = 1;
                    $late_count = 0;
                    if(isset($_GET['id']))
                    foreach($attendance_arr as $student_key => $date_value){
                        foreach ($date_value as $key => $value) {
                            if(array_values($value['student_id'])[0] == $_GET['id']){
                                $check_in = array_values($value['check_in'])[0];
                                $check_out = array_values($value['check_out'])[0];

                                echo '
                                <tr>
                                    <td>'.$num++.'</td>
                                    <td>'.date('d-m-Y', strtotime(array_values($value['date'])[0])).'</td>';

                                if(array_values($value['type'])[0] == "present"){
                                    if(count($check_in) == count($check_out)){
                                        $check_in = array_values(end(array_values($value['check_in'])[0]))[0];
                                        $check_out = array_values(end(array_values($value['check_out'])[0]))[0];
                                    }else{
                                        $check_in = array_values(end(array_values($value['check_in'])[0]))[0];
                                        $check_out = '-';
                                    }
                                    $late = "-";
                                    if(date("H:i", strtotime($check_in)) > $check_in_time){
                                        $late_count++;
                                        $present--;
                                        echo '
                                            <td>Late</td>
                                            <td>'.$check_in.'</td>
                                            <td>'.$check_out.'</td>
                                        </tr>';
                                    }
                                    else
                                    echo '
                                            <td>On Time</td>
                                            <td>'.$check_in.'</td>
                                            <td>'.$check_out.'</td>
                                        </tr>';
                                }
                                else{
                                    echo '
                                            <td>Absent</td>
                                            <td>-</td>
                                            <td>-</td>
                                        </tr>';
                                }
                            }
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
    

    $(document).ready(function () {
        $('#attendance').dataTable();

        var counter = 0;
        var guardian_assigned = <?php if($student !== NULL) echo json_encode(array_values($student['guardian_ids'])[0]); else echo "[]"?>;

        if(guardian_assigned.length != 0){
            for (const [key, value] of Object.entries(guardian_assigned)) {
                var newRow = $("<tr class='row' style='display: table-row'>");
                var cols = "";

                var guardian_list = <?php echo json_encode($extracted_guardian_arr);?>;

                cols += '<td class="col-5" style="padding-left: 0;">'+
                '<input list="guardian" class="form-control" name="guardian_ids[]" autocomplete="off" value="' + key + '">'+
                    '<datalist id="guardian">'

                for (const [key, value] of Object.entries(guardian_list)) {
                    cols += '<option value="'+ key +'">'+ value['user_internal_id'] + ' - ' + value['name'] + '</option>';
                }

                cols +='</datalist></td>';
                cols += '<td class="col-5"><input class="form-control" name="guardian_name[]" readonly></td>';
                cols += '<td class="col-1" style="padding-right: 0;"><input type="button" class="ibtnDel btn btn-md btn-danger "  value="Delete"></td>';
                cols += '<td class="col-1" style="padding-right: 0;"><a href="" target="_blank" name="guardian_link[]" class="btn btn-lg btn-block disabled" title="Open In New Tab"><i class="fas fa-external-link-alt alt_link"></i></a></td>';
                newRow.append(cols);
                $("table.order-list").append(newRow);
                counter++;
            }
        }

        $("#addrow").on("click", function () {
            var newRow = $("<tr class='row' style='display: table-row'>");
            var cols = "";

            var guardian_list = <?php echo json_encode($extracted_guardian_arr);?>;

            cols += '<td class="col-5" style="padding-left: 0;">'+
            '<input list="guardian" class="form-control" name="guardian_ids[]" autocomplete="off">'+
                '<datalist id="guardian">'

            for (const [key, value] of Object.entries(guardian_list)) {
                cols += '<option value="'+ key +'">'+ value['user_internal_id'] + ' - ' + value['name'] + '</option>';
            }

            cols +='</datalist></td>';
            cols += '<td class="col-5"><input class="form-control" name="guardian_name[]" readonly></td>';

            cols += '<td class="col-1" style="padding-right: 0;"><input type="button" class="ibtnDel btn btn-md btn-danger "  value="Delete"></td>';
            cols += '<td class="col-1" style="padding-right: 0;"><a href="" target="_blank" name="guardian_link[]" class="btn btn-lg btn-block disabled" title="Open In New Tab"><i class="fas fa-external-link-alt alt_link"></i></a></td>';
            newRow.append(cols);
            $("table.order-list").append(newRow);
            counter++;
        });

        var users_name = <?php echo json_encode($users_name);?>;
        var ids = document.getElementsByName('guardian_ids[]');
        var names = document.getElementsByName('guardian_name[]');
        var links = document.getElementsByName('guardian_link[]');

        for (var i = 0; i <ids.length; i++) {
            names[i].value = users_name[ids[i].value];

            var url = "userGuardianForm.php?id=";
            links[i].setAttribute('href', url+ids[i].value);
            links[i].classList.remove("disabled"); 
        }

        $("#refresh").on("click", function () {
            var ids = document.getElementsByName('guardian_ids[]');
            var names = document.getElementsByName('guardian_name[]');
            for (var i = 0; i <ids.length; i++) {

                if(users_name[ids[i].value] !== undefined){
                    names[i].value = users_name[ids[i].value];

                    var url = "userGuardianForm.php?id=";
                    links[i].setAttribute('href', url+ids[i].value);
                    links[i].classList.remove("disabled"); 
                }
                else{
                    names[i].value = '';
                    links[i].setAttribute('href', '');
                    links[i].classList.add("disabled"); 
                }
            }
        });

        $("table.order-list").on("click", ".ibtnDel", function (event) {
            $(this).closest("tr").remove();       
            counter -= 1
        });
    });

    $(document).ready(function () {
        var absent = <?php echo $absent;?>;
        var present = <?php echo $present;?>;
        var late = <?php echo $late_count;?>;

        var ctx = $("#attendance_chart");
        var myLineChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ["On Time", "Late", "Absent"],
                datasets: [{
                    data: [present, late, absent],
                    backgroundColor: ["rgba(100, 255, 0, 0.5)", "rgba(255, 193, 7, 0.6)", "rgba(255, 0, 0, 0.5)"]
                }]
            }
        });
    });

</script>

</body>
</html>