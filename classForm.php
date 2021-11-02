<?php
    include "include/session.php";
    include "include/user_Functions.php";
    include "include/dynamoDB_functions.php";

    check_session($dynamodb, $marshaler);

    $organization_id = $_SESSION['organization_id'];
    $organization = getOrganization($organization_id, $dynamodb, $marshaler);

    $class = NULL;
    $students = array_values($organization['student_ids']);
    $student_arr = array();

    $classes = array_values($organization['class_ids']);
    $class_arr = array();

    $class_arr = getClassesData($classes, $marshaler, $dynamodb);
    $student_arr = getStudentsData($students, $marshaler, $dynamodb);
    $attendance_arr = getAttendancesData($students, $marshaler, $dynamodb);

    $present = 0;
    $absent = 0;

    if(isset($_GET['type']) && $_GET['type'] == "add" && isset($_GET['class_name'])){
        $new_class_id = 'CLS'.time();
        $students_assigned = array();

        if(isset($_GET["student_ids"])){
            foreach ($_GET["student_ids"] as $key => $value) {
                if($value === "") continue;
                if(!isset($student_arr[$value]) || isset($students_assigned[$value])) continue;
                $students_assigned[$value] = $value;

                if($student_arr[$value]["class_id"]["S"] !== ""){
                    $remove_from_class_id = $student_arr[$value]["class_id"]["S"];
                    // remove student from the class
                    $key = $marshaler->marshalJson('
                            {
                                "class_id"      : "'.$remove_from_class_id.'"
                            }
                        ');

                    $updateExpression = 'remove student_ids.#cids';
                    $params = [
                        'TableName' => 'Classes',
                        'Key' => $key,
                        'UpdateExpression' => $updateExpression,
                        'ExpressionAttributeNames'=> ['#cids' => $value],
                        'ReturnValues' => 'ALL_NEW'
                    ];
                    $result = update_item($params, $dynamodb);
                }
            }
        }

        $class_id = "CLS".time().generateToken();
        if(!empty($students_assigned))
            $item = $marshaler->marshalJson('
                {
                    "class_id"          : "'.$class_id.'",
                    "created_at"        : "' . time() . '",
                    "updated_at"        : "' . time() . '",
                    "deleted_at"        : "",
                    "name"              : "'.$_GET['class_name'].'",
                    "organization_id"   : "'.$organization_id.'",
                    "student_ids"       : '.json_encode($students_assigned).',
                    "class_internal_id" : "'.getInternalId("C", count($classes[0])).'"
                }
            ');
        else
            $item = $marshaler->marshalJson('
                {
                    "class_id"          : "'.$class_id.'",
                    "created_at"        : "' . time() . '",
                    "updated_at"        : "' . time() . '",
                    "deleted_at"        : "",
                    "name"              : "'.$_GET['class_name'].'",
                    "organization_id"   : "'.$organization_id.'",
                    "student_ids"       : {},
                    "class_internal_id" : "'.getInternalId("C", count($classes[0])).'"
                }
            ');

        $params = [
            'TableName' => "Classes",
            'Item' => $item
        ];
        // add new class
        $result = add_item($params, $dynamodb);

        $key = $marshaler->marshalJson('
                {
                    "organization_id"      : "'.$organization_id.'"
                }
            ');

        $eav = $marshaler->marshalJson('
                {
                    ":class_id"     : "'.$class_id.'"
                }
            ');

        $updateExpression = 'set class_ids.#cids = :class_id';
        $params = [
            'TableName' => 'Organizations',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeValues'=> $eav,
            'ExpressionAttributeNames'=> ['#cids' => $class_id],
            'ReturnValues' => 'ALL_NEW'
        ];
        // update class into organization
        $result = update_item($params, $dynamodb);

        // loop set student to this class
        foreach($students_assigned as $student_key => $student_value){
            $key = $marshaler->marshalJson('
                {
                    "student_id"      : "'.$student_key.'"
                }
            ');

            $eav = $marshaler->marshalJson('
                {
                    ":new_class_id"     : "'.$class_id.'"
                }
            ');

            $updateExpression = 'set class_id = :new_class_id';
            $params = [
                'TableName' => 'Students',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }
        
        $_SESSION['class_added'] = true;
        header('Location: class.php');
    }
    else if(isset($_GET['type']) && $_GET['type'] == "edit" && isset($_GET['id']) && isset($_GET['class_name'])){
        $students_assigned = array();
        $class_id = $_GET['id'];
        $class = $class_arr[$class_id];

        $need_remove_student = isset($class['student_ids']['M']) ? $class['student_ids']['M'] : array();

        if(isset($_GET["student_ids"])){
            foreach ($_GET["student_ids"] as $key => $value) {
                if($value === "") continue;
                if(!isset($student_arr[$value]) || isset($students_assigned[$value])) continue;
                if(isset($need_remove_student[$value])) unset($need_remove_student[$value]);
                
                $students_assigned[$value] = $value;
            }
        }

        foreach($need_remove_student as $key => $value){
            $need_remove_student_id = $value["S"];
            // unset student class id
            $key = $marshaler->marshalJson('
                    {
                        "student_id"      : "'.$need_remove_student_id.'"
                    }
                ');
            $eav = $marshaler->marshalJson('
                {
                    ":cid"     : ""
                }
            ');

            $updateExpression = 'set class_id = :cid';
            $params = [
                'TableName' => 'Students',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }

        // loop update student class id
        foreach($students_assigned as $student_key => $student_value){
            // remove student from previous class
            if(!empty(array_values($student_arr[$student_key]['class_id'])[0])){
                $key = $marshaler->marshalJson('
                    {
                        "class_id"      : "'.array_values($student_arr[$student_key]['class_id'])[0].'"
                    }
                ');

                $updateExpression = 'remove student_ids.#sid';
                $params = [
                    'TableName' => 'Classes',
                    'Key' => $key,
                    'UpdateExpression' => $updateExpression,
                    'ExpressionAttributeNames' => ['#sid' => $student_key],
                    'ReturnValues' => 'ALL_NEW'
                ];
                $result = update_item($params, $dynamodb);
            }

            $key = $marshaler->marshalJson('
                {
                    "student_id"      : "'.$student_key.'"
                }
            ');

            $eav = $marshaler->marshalJson('
                {
                    ":new_class_id"     : "'.$class_id.'"
                }
            ');

            $updateExpression = 'set class_id = :new_class_id';
            $params = [
                'TableName' => 'Students',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }

        $key = $marshaler->marshalJson('
                {
                    "class_id"      : "'.$class_id.'"
                }
            ');
        if(!empty($students_assigned)){
            $eav = $marshaler->marshalJson('
                {
                    ":student_ids"     : '.json_encode($students_assigned).',
                    ":class_name"      : "'.$_GET['class_name'].'"
                }
            ');
        }
        else{
            $eav = $marshaler->marshalJson('
                {
                    ":student_ids"     : {},
                    ":class_name"      : "'.$_GET['class_name'].'"
                }
            ');
        }

        $updateExpression = 'set student_ids = :student_ids, #n = :class_name';
        $params = [
            'TableName' => 'Classes',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeValues'=> $eav,
            'ExpressionAttributeNames' => ['#n' => 'name'],
            'ReturnValues' => 'ALL_NEW'
        ];
        // update class info
        $result = update_item($params, $dynamodb);

        $class_arr = getClassesData($classes, $marshaler, $dynamodb);
        $student_arr = getStudentsData($students, $marshaler, $dynamodb);
    }
    else if(isset($_GET['type']) && $_GET['type'] == "delete" && isset($_GET['id'])){
        $class_id = $_GET['id'];
        $class = $class_arr[$class_id];

        $need_remove_student = $class['student_ids']['M'];
        
        // loop to unset student's class id
        foreach($need_remove_student as $key => $value){
            $need_remove_student_id = $value["S"];
            $key = $marshaler->marshalJson('
                    {
                        "student_id"      : "'.$need_remove_student_id.'"
                    }
                ');
            $eav = $marshaler->marshalJson('
                {
                    ":cid"     : ""
                }
            ');

            $updateExpression = 'set class_id = :cid';
            $params = [
                'TableName' => 'Students',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ReturnValues' => 'ALL_NEW'
            ];
            $result = update_item($params, $dynamodb);
        }

        // update class's deleted_at
        $key = $marshaler->marshalJson('
                {
                    "class_id"      : "'.$class_id.'"
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
            'TableName' => 'Classes',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeValues'=> $eav,
            'ReturnValues' => 'ALL_NEW'
        ];
        $result = update_item($params, $dynamodb);

        // remove class from organization
        $key = $marshaler->marshalJson('
                {
                    "organization_id"      : "'.$organization_id.'"
                }
            ');

        $updateExpression = 'remove class_ids.#cids';
        $params = [
            'TableName' => 'Organizations',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeNames'=> ['#cids' => $class_id],
            'ReturnValues' => 'ALL_NEW'
        ];
        
        // $result = update_item($params, $dynamodb);

        $_SESSION['class_delete'] = true;
        header('Location: class.php');
    }
    else{
        echo '<style type="text/css">
        .alert {
            display: none;
        }
        </style>';
    }

    if(isset($_GET['id'])){
        $key = $marshaler->marshalJson('
            {
                "class_id": "' . $_GET['id'] . '"
            }
        ');
        $params = [
            'TableName' => "Classes",
            'Key' => $key
        ];
        $result = get_item($params, $dynamodb);
        $class = $result;
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
?>

<!DOCTYPE html>
<html>
<head>
<title>Setting - Class</title>
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
            <p><i class="fas fa-home" style="margin-right: 10px;"></i>Class</p>
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
            <form class="needs-validation" action="" method="GET" novalidate>
                <div class="form-row">
                  <div class="col-md-12 mb-3">
                    <label for="class_name">Class Name</label>
                    <div class="input-group">
                      <input type="hidden" name="id" value="<?php if($class !== NULL){echo $class['class_id']["S"]; }?>">

                      <input type="text" name="class_name" value="<?php if($class !== NULL){echo $class['name']["S"];} ?>" class="form-control" id="class_name" placeholder="Class Name" aria-describedby="inputGroupPrepend" autocomplete="off" required>
                      <div class="invalid-feedback">
                        Please enter an class name.
                      </div>
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
                    if($class !== NULL){
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
                    <th>Name</th>
                    <th>Date</th>
                    <th>Attendance</th>
                    <th>Check In Time</th>
                    <th>Check Out Time</th>  
                </tr>  
            </thead>  
            <tbody>  
                <?php
                    $num = 1;
                    if(isset($_GET['id']))
                    $class_students = array_values($class['student_ids'])[0];
                    
                    foreach($attendance_arr as $student_key => $date_value){
                        foreach ($date_value as $key => $value) {
                            if(isset($class_students[array_values($value['student_id'])[0]])){
                                $check_in = array_values($value['check_in'])[0];
                                $check_out = array_values($value['check_out'])[0];

                                echo '
                                <tr>
                                    <td>'.$num++.'</td>
                                    <td>'.$extracted_student_arr[$student_key]['name'].'</td>
                                    <td>'.date('d-m-Y', strtotime(array_values($value['date'])[0])).'</td>';

                                if(array_values($value['type'])[0] == "present"){
                                    $present++;
                                    if(count($check_in) == count($check_out)){
                                        $check_in = array_values(end(array_values($value['check_in'])[0]))[0];
                                        $check_out = array_values(end(array_values($value['check_out'])[0]))[0];
                                    }else{
                                        $check_in = array_values(end(array_values($value['check_in'])[0]))[0];
                                        $check_out = '-';
                                    }
                                    echo '
                                            <td>Present</td>
                                            <td>'.$check_in.'</td>
                                            <td>'.$check_out.'</td>
                                        </tr>';
                                }
                                else{
                                    $absent++;
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

    function confirmationDelete(anchor){
        var result = confirm("Are you sure want to delete this user?");
        if(result){
            window.location = anchor.attr("href");
        }
    }
    

    $(document).ready(function () {
        $('#attendance').dataTable();
        
        var counter = 0;
        var student_assigned = <?php if($class !== NULL) echo json_encode($class['student_ids']['M']); else echo "[]"?>;

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
                        cols += '<option value="'+ key +'">'+ value["student_internal_id"] + ' - ' + value["name"];
                    else
                        cols += '<option value="'+ key +'">'+ value["student_internal_id"] + ' - ' + value["name"] + ' - ' + class_list[value["class_id"]];
                    
                }

                cols +='</datalist></td>';

                cols += '<td class="col-5"><input class="form-control" name="student_name[]" readonly></td>';
                cols += '<td class="col-1" style="padding-right: 0;"><input type="button" class="ibtnDel btn btn-md btn-danger"  value="Delete"></td>';
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
                    cols += '<option value="'+ key +'">'+ value["name"];
                else
                    cols += '<option value="'+ key +'">'+ value["name"] + ' - ' + class_list[value["class_id"]];
                
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

    $(document).ready(function () {
        var absent = <?php echo $absent;?>;
        var present = <?php echo $present;?>;

        var ctx = $("#attendance_chart");
        var myLineChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ["Absent", "Present"],
                datasets: [{
                    data: [absent, present],
                    backgroundColor: ["rgba(255, 0, 0, 0.5)", "rgba(100, 255, 0, 0.5)"]
                }]
            }
        });
    });
</script>

</body>
</html>