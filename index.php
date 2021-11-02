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
    $guardian_arr   = getGuardiansData($users, $marshaler, $dynamodb);

    $checked_in     = 0;
    $checked_out    = 0;
    $unassigned     = 0;
    $absent         = 0;
    $processed      = 0;
    $unprocessed    = 0;

    $check_in_time = strtotime(array_values($organization['check_in_time'])[0]);
    $check_out_time = strtotime(array_values($organization['check_out_time'])[0]);
    $late_threshold = array_values($organization['late_threshold'])[0];

    $check_in_time = date("H:i", strtotime('+'.$late_threshold.' minutes', $check_in_time));
    $check_out_time = date("H:i", strtotime('+'.$late_threshold.' minutes', $check_out_time));
    
    $today_attendance = array();
    $present_attendance_arr = getYearAttendancesData($marshaler, $dynamodb, 'present', date("Y-m-d"),date("Y-m-d"));
    foreach($present_attendance_arr as $key => $value){
        $sid = array_values($value['student_id'])[0];
        if(!isset($student_arr[$sid])) continue;

        $cid = array_values($student_arr[$sid]['class_id'])[0];
        if(empty($cid) || !isset($class_arr[$cid])) continue;

        if(!isset($today_attendance[$cid])){
            $today_attendance[$cid]['name']     = array_values($class_arr[$cid]['name'])[0];
            $today_attendance[$cid]['present']  = 0;
            $today_attendance[$cid]['absent']   = 0;
            $today_attendance[$cid]['late']     = 0;
        }

        $today_attendance[$cid]['present']++;

        $check_in = array_values(end(array_values($value['check_in'])[0]))[0];
        if(date("H:i", strtotime($check_in)) > $check_in_time){
            $today_attendance[$cid]['late']++;
            $today_attendance[$cid]['present']--;
        }
    }
    
    $absent_attendance_arr = getYearAttendancesData($marshaler, $dynamodb, 'absent', date("Y-m-d"),date("Y-m-d"));
    foreach($absent_attendance_arr as $key => $value){
        $sid = array_values($value['student_id'])[0];
        if(!isset($student_arr[$sid])) continue;

        $cid = array_values($student_arr[$sid]['class_id'])[0];
        if(empty($cid) || !isset($class_arr[$cid])) continue;

        if(!isset($today_attendance[$cid])){
            $today_attendance[$cid]['name']     = array_values($class_arr[$cid]['name'])[0];
            $today_attendance[$cid]['present']  = 0;
            $today_attendance[$cid]['absent']   = 0;
            $today_attendance[$cid]['late']     = 0;
        }
        $today_attendance[$cid]['absent']++;
    }

    $today_request = array();
    $today_request_arr = getYearRequestsData($marshaler, $dynamodb, 'pick up', date("Y-m-d"), date("Y-m-d"));
    foreach($today_request_arr as $key => $value){
        foreach(array_values($value['request'])[0] as $index => $request_value){
            $request_time = date('H:00 a', strtotime( array_values(array_values($request_value)[0]['request_time'])[0] ));
            if(isset($today_request[$request_time]["request"])){
                $today_request[$request_time]["request"]++;
            }
            else{
                $today_request[$request_time]["request"] = 1;
            }

            if(!empty( array_values(array_values($request_value)[0]['release_time'])[0] )){
                $release_time = date('H:00 a', strtotime( array_values(array_values($request_value)[0]['release_time'])[0] ));
                if(isset($today_request[$release_time]["release"])){
                    $today_request[$release_time]["release"]++;
                }
                else{
                    $today_request[$release_time]["release"] = 1;
                }
            }
        }
    }
    ksort($today_request);
    


    if(!empty(($students)[0])){
        foreach ($students[0] as $key => $value) {
            if(!isset($student_arr[$key]) || !empty(array_values($student_arr[$key]['deleted_at'])[0])) continue;
            if(isset($attendance_arr[$key][date("Y-m-d")])){
                if(array_values($attendance_arr[$key][date("Y-m-d")]["type"])[0] == "present"){
                    $check_in = array_values($attendance_arr[$key][date("Y-m-d")]["check_in"])[0];
                    $check_out = array_values($attendance_arr[$key][date("Y-m-d")]["check_out"])[0];

                    if(count($check_in) == count($check_out)){
                        $checked_out++;
                    }
                    else{
                        $checked_in++;
                    }
                }
                else{
                    $absent++;
                }
            }
            else{
                if(!empty(array_values(($student_arr[$key])['class_id'])[0]))
                    $unassigned++;
            }
        }
    }

    // header( "refresh:5;url=index.php" );
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

    if(!isset($_SESSION['class_delete_request'])){
        echo '<style type="text/css">
            .alert_delete{
                display: none;
            }
            </style>';
    }
    else{
        unset($_SESSION['class_delete_request']);
    }

?>
<!DOCTYPE html>
<html>
<head>
<title>Dashboard</title>
<?php include "include/heading.php";?>
</head>
<body>
<?php include "include/navbar.php";?>
<div class="main">
    <div class="row">
        <div class="col-12 heading">
        <p><i class="fas fa-tachometer-alt" style="margin-right: 10px;"></i>Dashboard</p>
        </div>
    </div>

    <div class="alert alert-success alert_add" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Student Released</h4>
    </div>

    <div class="alert alert-success alert_delete" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Request Deleted</h4>
    </div>

    <div class="row d-none" style="justify-content: center; margin-top: 20px;">
        <div class="col-4 overview">
            <div class="card" style="width: 18rem; color: #fff; background-color: #20d480; border-color: #20d480;">
                <div class="card-body">
                    <h3 class="card-title">Attendance</h3>
                    <p class="card-text">50<span style="display:flex; align-items: flex-end; font-size: 30px; margin-bottom: 5px">/50</span></p>
                    <!-- <a href="#" class="btn btn-primary">Go somewhere</a> -->
                </div>
            </div>
        </div>

        <div class="col-4 overview">
            <div class="card" style="width: 18rem; color: #fff; background-color: #ec4a5a; border-color: #ec4a5a!important;">
                <div class="card-body">
                    <h3 class="card-title">Unprocess Request</h3>
                    <p class="card-text">25</p>
                    <!-- <a href="#" class="btn btn-primary">Go somewhere</a> -->
                </div>
            </div>
        </div>
    </div>

    <div class="page-content page-container" id="page-content" style="padding: 50px 50px 50px 50px;">
        <div class="padding">
            <div class="row">
                <div class="container-fluid d-flex" style="justify-content: space-around;">
                    <div class="col-5">
                        <div class="card">
                        <div class="card-header">Attendance</div>
                            <div class="card-body" style="height: 100%">
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

                    <div class="col-5">
                        <div class="card">
                        <div class="card-header">Pick Up</div>
                            <div class="card-body" style="height: 100%">
                                <div class="chartjs-size-monitor" style="position: absolute; left: 0px; top: 0px; right: 0px; bottom: 0px; overflow: hidden; pointer-events: none; visibility: hidden; z-index: -1;">
                                    <div class="chartjs-size-monitor-expand" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;">
                                        <div style="position:absolute;width:1000000px;height:1000000px;left:0;top:0"></div>
                                    </div>
                                    <div class="chartjs-size-monitor-shrink" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;">
                                        <div style="position:absolute;width:200%;height:200%;left:0; top:0"></div>
                                    </div>
                                </div> <canvas id="chart-line1" width="299" height="200" class="chartjs-render-monitor" style="display: block; width: 299px; height: 200px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="page-content page-container" id="page-content" style="padding: 50px 50px 50px 50px;">
        <div class="padding">
            <div class="row">
                <div class="container-fluid d-flex" style="justify-content: space-around;">
                <div class="col-10">
                        <div class="card">
                        <div class="card-header">Class Attendance</div>
                            <div class="card-body" style="height: 100%">
                                <div class="chartjs-size-monitor" style="position: absolute; left: 0px; top: 0px; right: 0px; bottom: 0px; overflow: hidden; pointer-events: none; visibility: hidden; z-index: -1;">
                                    <div class="chartjs-size-monitor-expand" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;">
                                        <div style="position:absolute;width:1000000px;height:1000000px;left:0;top:0"></div>
                                    </div>
                                    <div class="chartjs-size-monitor-shrink" style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;">
                                        <div style="position:absolute;width:200%;height:200%;left:0; top:0"></div>
                                    </div>
                                </div> <canvas id="attendance_chart" width="299" height="125" class="chartjs-render-monitor" style="display: block; width: 299px; height: 200px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="page-content page-container d-none" id="page-content" style="padding: 50px 50px 50px 50px;">
        <div class="padding">
            <div class="row">
                <div class="container-fluid d-flex" style="justify-content: space-around;">
                    <div class="col-5">
                        <div class="card">
                        <div class="card-header">Pick Up Request</div>
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
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="">
        <div class="col" style="padding-left: 30px;">
            <h1 style="margin-bottom: 20px;">Request</h1>
        </div>
        <div class="col" style="padding-right: 30px;">
            <a href="#" class="btn btn-danger" id="button_delete" style="float: right; margin: 5px; padding: 5px; margin-right: 0" title="Delete"><i class="fas fa-trash" style="font-size: 20px; padding: 6px 7px;"></i></a>
            <a href="#" class="btn btn-success" id="button_check" style="float: right; margin: 5px; padding: 5px; margin-right: 0" title="Release"><i class="fas fa-check" style="font-size: 20px; padding: 6px 7px;"></i></a>
        </div>
        
    </div>

    <div style="padding: 30px;">
        <table id="request" class="display">  
            <thead>  
                <tr>  
                    <th>#</th>  
                    <th>Guardian Name</th>  
                    <th>Student Name</th>  
                    <th>Class</th>  
                    <th>Request Time</th>
                    <th>Release Time</th>
                    <th>Status</th>
                    <th>Action</th>  
                </tr>  
            </thead>  
            <tbody>  
                <?php
                    $num = 1;
                    foreach ($request_arr as $key => $value) {
                        foreach($value as $key => $request_value){
                            $student_id = array_values($request_value['student_id'])[0];

                            if(!empty(array_values($student_arr[$student_id]["deleted_at"])[0])) continue;

                            $request = array_values(end(array_values($request_value['request'])[0]))[0];
                            $user_id = array_values($request['user_id'])[0];
                            $request_time = array_values($request['request_time'])[0];
                            $release_time = array_values($request['release_time'])[0];

                            if($release_time != ""){
                                $status = "Released";
                                $processed++;
                            }
                            else{
                                $release_time = "-";
                                $status = "Requested";
                                $unprocessed++;
                            }

                            echo '
                                <tr data-value="'.$student_id.'">
                                    <td>'.$num++.'</td>
                                    <td><a href="userGuardianForm.php?id='.$user_id.'" class="link" target="blank">'.array_values(array_values($guardian_arr[$user_id]["info"])[0]['last_name'])[0].' '.array_values(array_values($guardian_arr[$user_id]["info"])[0]['first_name'])[0].'</a></td>
                                    <td><a href="userStudentForm.php?id='.$student_id.'" class="link" target="blank">'.array_values($student_arr[$student_id]['last_name'])[0].' '.array_values($student_arr[$student_id]['first_name'])[0].'</a></td>
                                    <td>'.array_values($class_arr[array_values($student_arr[$student_id]['class_id'])[0]]['name'])[0].'</td>
                                    <td>'.$request_time.'</td>
                                    <td>'.$release_time.'</td>
                                    <td>'.$status.'</td>
                                    <td class="d-flex justify-content-center">';
                                        
                                if($status == "Requested"){
                                    echo '<a href="release.php?suid[]='.$student_id.'" class="btn btn-success" style="padding: 5px;" title="Release" onclick="javascript:confirmationDelete($(this));return false;"><i class="fas fa-check" style="padding: 6px 7px;"></i></a>';
                                }
                                else{
                                    echo '<a href="release.php?suid[]='.$student_id.'" class="btn btn-success disabled" style="padding: 5px;" title="Release" onclick="javascript:confirmationDelete($(this));return false;"><i class="fas fa-check" style="padding: 6px 7px;"></i></a>';
                                }           
                                echo '</td></tr>';
                        }
                    }
                ?>
            </tbody>  
        </table>
    </div>

</div>

</body>
<script src='https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.1.4/Chart.bundle.min.js'></script>
<script>
    
    $(document).ready(function() {
        var checked_in  = <?php echo $checked_in;?>;
        var checked_out = <?php echo $checked_out;?>;
        var absent      = <?php echo $absent;?>;
        var unassigned  = <?php echo $unassigned;?>;
        
        var ctx = $("#chart-line");
        var myLineChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ["Absent", "In School", "Released", "Undefined"],
                datasets: [{
                    data: [absent, checked_in, checked_out, unassigned],
                    backgroundColor: ["rgba(255, 0, 0, 0.5)", "rgba(100, 255, 0, 0.5)", "rgba(200, 50, 255, 0.5)", "rgba(0, 100, 255, 0.5)"]
                }]
            }
        });
    });

    $(document).ready(function() {
        var ctx = $("#chart-line1");
        var myLineChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ["Request", "Release"],
                datasets: [{
                    data: [<?php echo $unprocessed;?>, <?php echo $processed;?>],
                    backgroundColor: ["rgba(255, 0, 0, 0.5)", "rgba(100, 255, 0, 0.5)"]
                }]
            }
        });
    });
    
    $(document).ready(function(){
        $('#request').dataTable();
        var selected = [];

        $('#request tbody').on( 'click', 'tr', function () {
            $(this).toggleClass('selected');
            if(selected.indexOf($(this).data('value')) !== -1){
                var index = selected.indexOf($(this).data('value'));
                selected.splice(index, 1);
            }
            else{
                selected.push($(this).data('value'));
            }
        } );

        $('#button_check').click( function () {
            var link = "release.php?suid[]=";
            console.log(selected);
            $.each(selected, (index, item) => {
                link += item;
                link += "&suid[]=";
            });
            var result = confirm("Confirm to release?");
            if(result)
            $(this).attr("href", link);
        } );

        $('#button_delete').click( function () {
            var link = "reject.php?suid[]=";
            $.each(selected, (index, item) => {
                link += item;
                link += "&suid[]=";
            });
            var result = confirm("Confirm to delete these request?");
            if(result)
            $(this).attr("href", link);
        } );
    });
    
    $(document).ready(function() {
        var today_attendance = <?php echo json_encode($today_attendance);?>;
        var label = [];
        var present = [];
        var absent = [];
        var late = [];
        for (const [key, value] of Object.entries(today_attendance)) {
            label.push(value['name']);
            present.push(value['present']);
            absent.push(value['absent']);
            late.push(value['late']);
        }

        var ctx = $("#attendance_chart");
        var myLineChart = new Chart(ctx, {
            type: 'bar',
            data: {
            labels: label,
            datasets: [{
                label: "On Time",
                type: "bar",
                backgroundColor: "rgba(100, 255, 0, 0.5)",
                data: present,
                }, {
                label: "Late",
                type: "bar",
                backgroundColor: "rgba(255, 193, 7, 0.6)",
                data: late
                }, {
                label: "Absent",
                type: "bar",
                backgroundColor: "rgba(255, 0, 0, 0.5)",
                data: absent
                }
            ]
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

    $(document).ready(function() {
        var today_request = <?php echo json_encode($today_request); ?>;

        var labels = [];
        var request_data = [];
        var release_data = [];

        for (const [key, value] of Object.entries(today_request)) {
            labels.push(key);

            if(value["request"] !== undefined)
                request_data.push(value["request"]);
            else{
                request_data.push(0);
            }

            if(value["release"] !== undefined)
                release_data.push(value["release"]);
            else{
                release_data.push(0);
            }
        }

        var ctx = $("#pickup_chart");
        var myLineChart = new Chart(ctx, {
            type: 'bar',
            data: {
            labels: labels,
            datasets: [{
                label: "Request",
                type: "bar",
                backgroundColor: "rgba(66,133,244,0.5)",
                data: request_data,
                }, {
                label: "Release",
                type: "bar",
                backgroundColor: "rgba(0, 225, 7, 0.5)",
                data: release_data,
                }
            ]},
            options: {
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        });
    });
</script>
</html>