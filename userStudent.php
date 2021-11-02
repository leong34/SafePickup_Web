<?php
include "include/session.php";
include "include/user_Functions.php";
include "include/dynamoDB_functions.php";

check_session($dynamodb, $marshaler);

$organization_id = $_SESSION['organization_id'];
$organization = getOrganization($organization_id, $dynamodb, $marshaler);

$students = array_values($organization['student_ids']);
$student_arr = getStudentsData($students, $marshaler, $dynamodb);

$classes = array_values($organization['class_ids']);
$class_arr = getClassesData($classes, $marshaler, $dynamodb);

$attendance_arr = getAttendancesData($students, $marshaler, $dynamodb);

date_default_timezone_set("Asia/Kuala_Lumpur");

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

if(!isset($_SESSION['class_delete'])){
    echo '<style type="text/css">
        .alert_remove{
            display: none;
        }
        </style>';
}
else{
    unset($_SESSION['class_delete']);
}
?>

<!DOCTYPE html>
<html>
<head>
<title>User - Student</title>
<?php include "include/heading.php";?>
</head>
<body>
<?php include "include/navbar.php";?>

<div class="main">
    <div class="row">
        <div class="col-12 heading">
            <p><i class="fas fa-user-graduate" style="margin-right: 10px;"></i>Student</p>
        </div>
    </div>

    <div class="alert alert-success alert_add" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Student Have Been Added</h4>
    </div>

    <div class="alert alert-success alert_remove" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Student Have Been Deleted</h4>
    </div>

    <div class="row">
        <div class="col" style="padding-right: 30px;">
            <a href="userStudentForm.php" class="btn btn-primary" style="float: right; margin: 5px; padding: 5px;" title="Add"><i class="fas fa-plus" style="font-size: 20px; padding: 6px 7px;"></i></a>
            <a href="#" class="btn btn-warning" id="button_absent" style="float: right; margin: 5px; padding: 5px; margin-right: 0" title="Mark Absent"><i class="fas fa-user-times" style="font-size: 20px; padding: 6px 7px; color: #fff;"></i></a>
            <a href="#" class="btn btn-success" id="button_check" style="float: right; margin: 5px; padding: 5px; margin-right: 0" title="Check In"><i class="fas fa-user-clock" style="font-size: 20px; padding: 6px 7px;"></i></a>
        </div>
    </div>
    
    <div style="padding: 0 30px;">
        <table id="myTable" class="display">  
            <thead>  
                <tr>  
                    <th>#</th>  
                    <th>Name</th>  
                    <th>Class</th>  
                    <th>Status</th>
                    <th>Action</th>  
                </tr>  
            </thead>  
            <tbody>  
                <?php
                    $num = 1;
                    foreach ($student_arr as $key => $value) {
                        if(array_values($value['deleted_at'])[0] === ""){
                            $student_class = array_values($value['class_id'])[0] === "" ? "-" : array_values($class_arr[array_values($value['class_id'])[0]]['name'])[0];
                            echo '
                            <tr data-value="'.$key.'">
                              <td>'.array_values($value['student_internal_id'])[0].'</td>
                              <td>'.array_values($value['last_name'])[0].' '.array_values($value['first_name'])[0] .'</td>
                              <td>'.$student_class.'</td>';
                              
                            if(isset($attendance_arr[$key][date("Y-m-d")])){
                                if(array_values($attendance_arr[$key][date("Y-m-d")]["type"])[0] == "present"){
                                    $check_in = array_values($attendance_arr[$key][date("Y-m-d")]["check_in"])[0];
                                    $check_out = array_values($attendance_arr[$key][date("Y-m-d")]["check_out"])[0];
                                    
                                    if(count($check_in) == count($check_out)){
                                        echo '<td>Checked Out</td>';
                                    }
                                    else{
                                        echo '<td>Checked In</td>';
                                    }
                                }
                                else if(array_values($attendance_arr[$key][date("Y-m-d")]["type"])[0] == "absent"){
                                    echo '<td>Absent</td>';
                                }
                            }
                            else{
                                echo '<td>-</td>';
                            }

                            echo '
                              <td>
                                  <a href="userStudentForm.php?id='.$key.'" class="btn btn-info" style="padding: 5px;" title="Edit"><i class="fas fa-edit" style="padding: 6px 7px; color: #fff"></i></a>
                                  <a href="userStudentForm.php?id='.$key.'&type=delete" class="btn btn-danger" style="padding: 5px;" title="Delete" onclick="javascript:confirmationDelete($(this));return false;"><i class="fas fa-trash" style="padding: 6px 7px;"></i></a>
                              </td>
                            </tr>';
                        }
                    }
                ?>
            </tbody>  
        </table>
    </div>

</div>


<script>
    $(document).ready(function(){
        $('.dropdown-toggle').dropdown();

        $('#myTable').dataTable();
        var selected = [];

        $('#myTable tbody').on( 'click', 'tr', function () {
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
            var link = "checkInOut.php?suid[]=";

            $.each(selected, (index, item) => {
                link += item;
                link += "&suid[]=";
            });
            var result = confirm("Are you sure want to check in these student?");
            if(result)
            $(this).attr("href", link);
        } );

        $('#button_absent').click( function () {
            var link = "checkInOut.php?type=absent&suid[]=";

            $.each(selected, (index, item) => {
                link += item;
                link += "&suid[]=";
            });
            var result = confirm("Are you sure want to mark these student absent?");
            if(result)
            $(this).attr("href", link);
        } );
    });

    function confirmationDelete(anchor){
        var result = confirm("Are you sure want to delete this user?");
        if(result){
            window.location = anchor.attr("href");
        }
    }
</script>
</body>
</html>