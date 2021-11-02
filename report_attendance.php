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
    $request_arr    = getRequestsData($students, $marshaler, $dynamodb);

    $check_in_time = strtotime(array_values($organization['check_in_time'])[0]);
    $check_out_time = strtotime(array_values($organization['check_out_time'])[0]);
    $late_threshold = array_values($organization['late_threshold'])[0];

    $check_in_time = date("H:i", strtotime('+'.$late_threshold.' minutes', $check_in_time));
    $check_out_time = date("H:i", strtotime('+'.$late_threshold.' minutes', $check_out_time));

    $late = array();
    $on_time = array();
    $absent = array();

    $extracted_student_arr = array();
    foreach($student_arr as $key => $value){
        if(!empty(array_values($value['deleted_at'])[0]))continue;
        $extracted_student_arr[$key]['name'] = array_values($value['last_name'])[0].' '.array_values($value['first_name'])[0];
        $extracted_student_arr[$key]['student_internal_id'] = array_values($value['student_internal_id'])[0];
    }

    $extracted_class_arr = array();
    foreach($class_arr as $key => $value){
        if(!empty(array_values($value['deleted_at'])[0]))continue;
        $extracted_class_arr[$key]["name"] = array_values($value["name"])[0];
        $extracted_class_arr[$key]["class_internal_id"] = array_values($value["class_internal_id"])[0];
    }

    $from_date = !empty($_GET['from_date']) ? date('Y-m-d', strtotime($_GET['from_date']) ) : date('Y-m-d', strtotime('monday this week') );
    $to_date = !empty($_GET['to_date']) ? date('Y-m-d', strtotime($_GET['to_date']) ) : date('Y-m-d', strtotime('sunday this week') );

    $attendances = array();
    
    $present_attendance_arr = getYearAttendancesData($marshaler, $dynamodb, 'present', $from_date, $to_date);
    foreach($present_attendance_arr as $key => $value){
        $sid = array_values($value['student_id'])[0];
        if(!isset($student_arr[$sid])) continue;
        if(!empty(array_values($student_arr[$sid]["deleted_at"])[0])) continue;

        $cid = array_values($student_arr[$sid]['class_id'])[0];
        if(empty($cid) || !isset($class_arr[$cid])) continue;

        if(!empty($_GET["student_id"]) && (!isset($student_arr[$_GET["student_id"]]) || $sid != $_GET["student_id"]))
            continue;

        if(!empty($_GET["class_id"]) && (!isset($class_arr[$_GET["class_id"]]) || $cid != $_GET["class_id"]))
            continue;

        $check_in = array_values($value['check_in'])[0];
        $check_out = array_values($value['check_out'])[0];

        if(count($check_in) == count($check_out)){
            $check_in = array_values(end(array_values($value['check_in'])[0]))[0];
            $check_out = array_values(end(array_values($value['check_out'])[0]))[0];
        }else{
            $check_in = array_values(end(array_values($value['check_in'])[0]))[0];
            $check_out = '-';
        }

        $type = "On Time";

        if(date("H:i", strtotime($check_in)) > $check_in_time){
            if(isset($late[array_values($value['date'])[0]])){
                $late[array_values($value['date'])[0]]++;
            }else{
                $late[array_values($value['date'])[0]] = 1;
            }
            $type = "Late";
        }
        else{
            if(isset($on_time[array_values($value['date'])[0]])){
                $on_time[array_values($value['date'])[0]]++;
            }else{
                $on_time[array_values($value['date'])[0]] = 1;
            }
        }

        $attendance_item = array(
            "student_id"            => array_values($value['student_id'])[0],
            "student_internal_id"   => $extracted_student_arr[array_values($value['student_id'])[0]]['student_internal_id'],
            "name"                  => $extracted_student_arr[array_values($value['student_id'])[0]]['name'],
            "date"                  => array_values($value['date'])[0],
            "class_name"            => array_values($class_arr[$cid]["name"])[0],
            "type"                  => $type,
            "check_in_time"         => $check_in,
            "check_out_time"        => $check_out,
        );
        array_push($attendances, $attendance_item);
    }

    $absent_attendance_arr = getYearAttendancesData($marshaler, $dynamodb, 'absent', $from_date, $to_date);
    foreach($absent_attendance_arr as $key => $value){
        $sid = array_values($value['student_id'])[0];
        if(!isset($student_arr[$sid])) continue;
        if(!empty(array_values($student_arr[$sid]["deleted_at"])[0])) continue;

        $cid = array_values($student_arr[$sid]['class_id'])[0];
        if(empty($cid) || !isset($class_arr[$cid])) continue;
        
        if(!empty($_GET["student_id"]) && (!isset($student_arr[$_GET["student_id"]]) || $sid != $_GET["student_id"]))
            continue;

        if(!empty($_GET["class_id"]) && (!isset($class_arr[$_GET["class_id"]]) || $cid != $_GET["class_id"]))
            continue;

        $attendance_item = array(
            "student_id"            => array_values($value['student_id'])[0],
            "student_internal_id"   => $extracted_student_arr[array_values($value['student_id'])[0]]['student_internal_id'],
            "name"                  => $extracted_student_arr[array_values($value['student_id'])[0]]['name'],
            "date"                  => array_values($value['date'])[0],
            "class_name"            => array_values($class_arr[$cid]["name"])[0],
            "type"                  => "Absent",
            "check_in_time"         => "-",
            "check_out_time"        => "-",
        );

        if(isset($absent[array_values($value['date'])[0]])){
            $absent[array_values($value['date'])[0]]++;
        }else{
            $absent[array_values($value['date'])[0]] = 1;
        }

        array_push($attendances, $attendance_item);
    }

    $i = 0;
    $dates = array();
    $label = array();

    if(!isset($_GET['group_by_type']) || $_GET['group_by_type'] == "date"){
        array_push($dates, $from_date);
        array_push($label, date("d-m-Y", strtotime($from_date)));
        while(1){
            if(end($dates) != $to_date){
                $date = date_create( end($dates) );
                date_sub($date, date_interval_create_from_date_string("-1 days"));
                
                $day = date_format($date,"D");
                $date = date_format($date,"Y-m-d");

                // if($day  != "Sun" && $day  != "Sat"){
                //     array_push($dates, $date);
                // }
                array_push($dates, $date);
                array_push($label, date("d-m-Y", strtotime($date)));
            }else break;
        }
        $info = array(
            "dates"     => $dates,
            "label"     => $label,
            "late"      => $late,
            "on_time"   => $on_time,
            "absent"    => $absent
        );
    }
    else if(!isset($_GET['group_by_type']) || $_GET['group_by_type'] == "day"){
        $filtered_late = array();
        foreach($late as $key => $value){
            if(!isset($filtered_late[date("D", strtotime($key))])){
                $filtered_late[date("D", strtotime($key))] = $value;
            }else{
                $filtered_late[date("D", strtotime($key))] += $value;
            }
        }

        $filtered_on_time = array();
        foreach($on_time as $key => $value){
            if(!isset($filtered_on_time[date("D", strtotime($key))])){
                $filtered_on_time[date("D", strtotime($key))] = $value;
            }else{
                $filtered_late[date("D", strtotime($key))] += $value;
            }
        }

        $filtered_absent = array();
        foreach($absent as $key => $value){
            if(!isset($filtered_absent[date("D", strtotime($key))])){
                $filtered_absent[date("D", strtotime($key))] = $value;
            }else{
                $filtered_absent[date("D", strtotime($key))] += $value;
            }
        }
        $info = array(
            "dates"     => array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'),
            "label"     => array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'),
            "late"      => $filtered_late,
            "on_time"   => $filtered_on_time,
            "absent"    => $filtered_absent
        );
    }
    else if(!isset($_GET['group_by_type']) || $_GET['group_by_type'] == "month"){
        array_push($dates, date("Y-m", strtotime($from_date)));
        array_push($label, date("M Y", strtotime($from_date)));

        while(1){
            if(end($dates) != date("Y-m", strtotime($to_date))){
                $date = date_create( end($dates) );
                date_sub($date, date_interval_create_from_date_string("-1 months"));
                $date = date_format($date,"Y-m");
                array_push($dates, $date);
                array_push($label, date("M Y", strtotime($date)));
            }else break;
        }
        $filtered_late = array();
        foreach($late as $key => $value){
            if(!isset($filtered_late[date("Y-m", strtotime($key))])){
                $filtered_late[date("Y-m", strtotime($key))] = $value;
            }else{
                $filtered_late[date("Y-m", strtotime($key))] += $value;
            }
        }

        $filtered_on_time = array();
        foreach($on_time as $key => $value){
            if(!isset($filtered_on_time[date("Y-m", strtotime($key))])){
                $filtered_on_time[date("Y-m", strtotime($key))] = $value;
            }else{
                $filtered_late[date("Y-m", strtotime($key))] += $value;
            }
        }

        $filtered_absent = array();
        foreach($absent as $key => $value){
            if(!isset($filtered_absent[date("Y-m", strtotime($key))])){
                $filtered_absent[date("Y-m", strtotime($key))] = $value;
            }else{
                $filtered_absent[date("Y-m", strtotime($key))] += $value;
            }
        }
        $info = array(
            "dates"     => $dates,
            "label"     => $label,
            "late"      => $filtered_late,
            "on_time"   => $filtered_on_time,
            "absent"    => $filtered_absent
        );
    }
    else if(!isset($_GET['group_by_type']) || $_GET['group_by_type'] == "year"){
        array_push($dates, date("Y", strtotime($from_date)));
        array_push($label, date("Y", strtotime($from_date)));
        while(1){
            if(end($dates) != date("Y", strtotime($to_date))){
                $date = date_create( end($dates)."-01-01" );
                date_sub($date, date_interval_create_from_date_string("-1 years"));
                $date = date_format($date,"Y");
                array_push($dates, $date);
                array_push($label, $date);
            }else break;
        }
        $filtered_late = array();
        foreach($late as $key => $value){
            if(!isset($filtered_late[date("Y", strtotime($key))])){
                $filtered_late[date("Y", strtotime($key))] = $value;
            }else{
                $filtered_late[date("Y", strtotime($key))] += $value;
            }
        }

        $filtered_on_time = array();
        foreach($on_time as $key => $value){
            if(!isset($filtered_on_time[date("Y", strtotime($key))])){
                $filtered_on_time[date("Y", strtotime($key))] = $value;
            }else{
                $filtered_late[date("Y", strtotime($key))] += $value;
            }
        }

        $filtered_absent = array();
        foreach($absent as $key => $value){
            if(!isset($filtered_absent[date("Y", strtotime($key))])){
                $filtered_absent[date("Y", strtotime($key))] = $value;
            }else{
                $filtered_absent[date("Y", strtotime($key))] += $value;
            }
        }
        $info = array(
            "dates"     => $dates,
            "label"     => $label,
            "late"      => $filtered_late,
            "on_time"   => $filtered_on_time,
            "absent"    => $filtered_absent
        );
    }

    if(!isset($_SESSION['class_added'])){
        echo '<style type="text/css">
            .alert_add{
                display: none;
            }
            </style>';
    }
    else{
        unset($_SESSION['class_added']);
    }

?>
<!DOCTYPE html>
<html>
<head>
<title>Report - Attendance</title>
<?php include "include/heading.php";?>
</head>
<body>
<?php include "include/navbar.php";?>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>
<div class="main">
    <div class="row">
        <div class="col-12 heading">
        <p><i class="fas fa-clipboard-check" style="margin-right: 10px;"></i>Attendance</p>
        </div>
    </div>
    
    <div class="row" style="padding: 20px 20px 20px 40px; display: flex; justify-content: center;">
        <div class="col-8">
            <form class="needs-validation" action="" method="GET" onsubmit="myValidation();" novalidate autocomplete="off">

                <div class="form-row" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div class="col-5">
                        <label for="date">From</label>
                        <div class="input-group">

                        <input type="text" name="from_date" class="form-control" id="from_date" value="<?php echo date("Y-m-d", strtotime($from_date));?>" required>
                        <div class="invalid-feedback">
                            Date Cant Be Empty.
                        </div>
                        <div class="custom-invalid-feedback" id="from_date_invalid">
                            Cannot more than to date.
                        </div>
                        </div>
                    </div>

                    <div class="col-5">
                        <label for="date">To</label>
                        <div class="input-group">
                        <input type="text" name="to_date" class="form-control" id="to_date" value="<?php echo date("Y-m-d", strtotime($to_date));?>" required>
                        <div class="invalid-feedback">
                            Date Cant Be Empty.
                        </div>
                        <div class="custom-invalid-feedback" id="to_date_invalid">
                            Cannot less than from date.
                        </div>
                        </div>
                    </div>
                </div>

                <div class="form-row" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div class="col-5">
                        <label for="group_by">Filter By</label>
                        <select class="form-select" name="group_by" id="group_by" required>
                            <option value="organization" <?php if(isset($_GET['group_by']) && $_GET['group_by'] == "organization") echo 'selected';?>>Organization</option>
                            <option value="class" <?php if(isset($_GET['group_by']) && $_GET['group_by'] == "class") echo 'selected';?>>Class</option>
                            <option value="student" <?php if(isset($_GET['group_by']) && $_GET['group_by'] == "student") echo 'selected';?>>Student</option>
                        </select>
                    </div>

                    <div class="col-5">
                        <label for="group_by_type">Group By</label>
                        <select class="form-select" name="group_by_type" id="group_by_type" required>
                            <option value="date" <?php if(isset($_GET['group_by_type']) && $_GET['group_by_type'] == "date") echo 'selected';?>>Date</option>
                            <option value="day" <?php if(isset($_GET['group_by_type']) && $_GET['group_by_type'] == "day") echo 'selected';?>>Day</option>
                            <option value="month" <?php if(isset($_GET['group_by_type']) && $_GET['group_by_type'] == "month") echo 'selected';?>>Month</option>
                            <option value="year" <?php if(isset($_GET['group_by_type']) && $_GET['group_by_type'] == "year") echo 'selected';?>>Year</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div class="col-5">
                        <label for="class_id">Class Id</label>
                        <input list="class_id" class="form-control" name="class_id"  id="class_id_input" autocomplete="off" value="<?php if(isset($_GET['class_id'])) echo $_GET['class_id'];?>" disabled>
                        <datalist id="class_id">
                            <?php
                                foreach($class_arr as $key => $value){
                                    if(empty(array_values($value['deleted_at'])[0])){
                                        echo '<option value="'.$key.'">'.array_values($value['class_internal_id'])[0].' - '.array_values($value['name'])[0].'</option>';
                                    }
                                }
                            ?>
                        </datallist>
                    </div>

                    <div class="col-5">
                        <label for="class_info">Class Info</label>
                        <input type="text" class="form-control" name="class_info"  id="class_info" autocomplete="off" value="" readonly>
                    </div>
                </div>

                <div class="form-row" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div class="col-5">
                        <label for="student_id">Student Id</label>
                        <input list="student_id" class="form-control" name="student_id"  id="student_id_input" autocomplete="off" value="<?php if(isset($_GET['student_id'])) echo $_GET['student_id'];?>" disabled>
                        <datalist id="student_id">
                            <?php
                                foreach($student_arr as $key => $value){
                                    if(empty(array_values($value['deleted_at'])[0])){
                                        echo '<option value="'.$key.'">'.array_values($value['student_internal_id'])[0].' - '.array_values($value['last_name'])[0].' '.array_values($value['first_name'])[0].'</option>';
                                    }
                                }
                            ?>
                        </datallist>
                    </div>

                    <div class="col-5">
                        <label for="student_info">Student Info</label>
                        <input type="text" class="form-control" name="student_info"  id="student_info" autocomplete="off" value="" readonly>
                    </div>
                </div>

                <button class="btn btn-success" type="submit" style="padding: 5px 20px; margin-top: 20px;">Filter</button>
              </form>
        </div>
    </div>

    <div class="page-content page-container" id="page-content">
        <div class="padding">
            <div class="row">
                <div class="container-fluid d-flex justify-content-center">
                    <div class="col-sm-8 col-md-8">
                        <div class="card">
                            <div class="card-header">Attendance</div>
                            <div class="card-body" style="">
                                <div class="chartjs-size-monitor" style="position: absolute; left: 0px; top: 0px; right: 0px; bottom: 0px; overflow: hidden; pointer-events: none; visibility: hidden; z-index: -1;">
                                    <div class="chartjs-size-monitor-expand" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;">
                                        <div style="position:absolute;width:1000000px;height:1000000px;left:0;top:0"></div>
                                    </div>
                                    <div class="chartjs-size-monitor-shrink" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;">
                                        <div style="position:absolute;width:200%;height:200%;left:0; top:0"></div>
                                    </div>
                                </div> <canvas id="chart-line" width="299" height="200" class="chartjs-render-monitor" style="display: block; width: 299px; height: 200px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="padding: 30px;">
        <table id="attendance" class="display">  
            <thead>  
                <tr>  
                    <th>#</th>  
                    <th>Student Id</th>  
                    <th>Name</th>  
                    <th>Date</th>
                    <th>Class Name</th>
                    <th>Attendance</th>
                    <th>Check In Time</th>
                    <th>Check Out Time</th>  
                </tr>  
            </thead>  
            <tbody>  
                <?php
                    $num = 1;
                    foreach($attendances as $key => $value){
                        echo '
                        <tr>
                            <td>'.$num++.'</td>
                            <td>'.$value["student_internal_id"].'</td>
                            <td><a href="userStudentForm.php?id='.$value["student_id"].'" class="link" target="blank">'.$value["name"].'</a></td>
                            <td>'.date("D, d-m-Y", strtotime($value["date"])).'</td>
                            <td>'.$value["class_name"].'</td>
                            <td>'.$value["type"].'</td>
                            <td>'.$value["check_in_time"].'</td>
                            <td>'.$value["check_out_time"].'</td>
                        </tr>';
                    }
                ?>
            </tbody>  
        </table>
    </div>
</div>

</body>
<script src='https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.1.4/Chart.bundle.min.js'></script>
<script>
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

    function myValidation() {
        var from_date = document.getElementById('from_date').value;
        var to_date = document.getElementById('to_date').value;
        var invalid_date = false;

        if(from_date === "" || to_date === ""){
            document.getElementById("from_date_invalid").style.display = "none";
            document.getElementById("to_date_invalid").style.display = "none";
        }

        if(from_date > to_date){
            invalid_date = true;
        }

        if(invalid_date){
            event.preventDefault();
            document.getElementById("from_date_invalid").style.display = "block";
            document.getElementById("to_date_invalid").style.display = "block";
            document.getElementById("from_date").classList.add("custom-invalid-field");
            document.getElementById("to_date").classList.add("custom-invalid-field");
            return false;
        }
        else{
            document.getElementById("from_date_invalid").style.display = "none";
            document.getElementById("to_date_invalid").style.display = "none";
            document.getElementById("from_date").classList.remove("custom-invalid-field");
            document.getElementById("to_date").classList.remove("custom-invalid-field");
        }
        return true;
    }

    $(document).ready(function(){
        $('#from_date').datepicker({
            format: 'yyyy-mm-dd',
            todayHighlight: true
        });

        $('#to_date').datepicker({
            format: 'yyyy-mm-dd',
            todayHighlight: true
        });
    });

    $(document).ready(function(){
        $('#group_by').change(checkGroupBy);
        checkGroupBy();
        function checkGroupBy() {
            var group_by = $('#group_by').val();

            if(group_by === "organization"){
                $("#class_id_input").prop('disabled', true);
                $("#class_id_input").prop('required', false);
                $("#class_id_input").val("");
                $('#class_info').val("");

                $("#student_id_input").prop('disabled', true);
                $("#student_id_input").prop('required', false);
                $("#student_id_input").val("");
                $('#student_info').val("");

            }
            else if(group_by === "class"){
                $("#class_id_input").prop('disabled', false);
                $("#class_id_input").prop('required', true);

                $("#student_id_input").prop('disabled', true);
                $("#student_id_input").prop('required', false);
                $("#student_id_input").val("");
                $('#student_info').val("");
            }
            else{
                $("#class_id_input").prop('disabled', true);
                $("#class_id_input").prop('required', false);
                $("#class_id_input").val("");
                $('#class_info').val("");

                
                $("#student_id_input").prop('disabled', false);
                $("#student_id_input").prop('required', true);
            }
        }
    });

    $(document).ready(function(){
        var extracted_student_arr = <?php echo json_encode($extracted_student_arr);?>;
        var extracted_class_arr = <?php echo json_encode($extracted_class_arr);?>;
        
        $('#class_id_input').on('input',fillClassInfo);
        fillClassInfo();

        $('#student_id_input').on('input',fillStudentInfo);
        fillStudentInfo();

        function fillClassInfo(){
            if(extracted_class_arr[$('#class_id_input').val()] !== undefined)
            $('#class_info').val(extracted_class_arr[$('#class_id_input').val()]['class_internal_id'] + " - " + extracted_class_arr[$('#class_id_input').val()]['name']);
            else
            $('#class_info').val("");
        }

        function fillStudentInfo(){
            if(extracted_student_arr[$('#student_id_input').val()] !== undefined)
                $('#student_info').val(extracted_student_arr[$('#student_id_input').val()]['student_internal_id'] + " - " + extracted_student_arr[$('#student_id_input').val()]['name']);
            else
                $('#student_info').val("");
        }
    });

    $(document).ready(function(){
        $('#attendance').dataTable();
    });
    
    $(document).ready(function(){
        var info = <?php echo json_encode($info); ?>;
        var label = info['label'];
        var on_time = [];
        var late = [];
        var absent = [];

        for (const [key, value] of Object.entries(info['dates'])) {
            if(info["on_time"][value] !== undefined)
                on_time.push(info["on_time"][value]);
            else
                on_time.push(0);
                
            if(info["late"][value] !== undefined)
                late.push(info["late"][value]);
            else
                late.push(0);

            if(info["absent"][value] !== undefined)
                absent.push(info["absent"][value]);
            else
                absent.push(0);    
        }

        var ctx = $("#chart-line");
        var myLineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: label,
                datasets: [{
                    data: on_time,
                    label: "On Time",
                    borderColor: "rgba(100, 255, 0, 1)",
                    fill: false
                }, {
                    data: late,
                    label: "Late",
                    borderColor: "rgba(255, 193, 7, 1)",
                    fill: false
                }, {
                    data: absent,
                    label: "Absent",
                    borderColor: "rgba(255, 0, 0, 1)",
                    fill: false
                }]
            },
            options: {
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            callback: function(value) {if (value % 1 === 0) {return value;}}
                        }
                    }]
                }
            }
        });
    });
</script>
</html>