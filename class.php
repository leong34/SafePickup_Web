<?php
include "include/session.php";
include "include/user_Functions.php";
include "include/dynamoDB_functions.php";

check_session($dynamodb, $marshaler);
$organization_id = $_SESSION['organization_id'];
$organization = getOrganization($organization_id, $dynamodb, $marshaler);

$classes = $organization['class_ids'];
$students = array_values($organization['student_ids']);
$attendance_arr = getAttendancesData($students, $marshaler, $dynamodb);

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

if(!isset($_SESSION['class_checked_in'])){
    echo '<style type="text/css">
        .alert_checked_in{
            display: none;
        }
        </style>';
}
else{
    unset($_SESSION['class_checked_in']);
}

if(!isset($_SESSION['class_checked_out'])){
    echo '<style type="text/css">
        .alert_checked_out{
            display: none;
        }
        </style>';
}
else{
    unset($_SESSION['class_checked_out']);
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Setting - Class</title>
<?php include "include/heading.php";?>
</head>
<body>
<?php include "include/navbar.php";?>

<div class="main">
    <div class="row">
        <div class="col-12 heading">
            <p><i class="fas fa-home" style="margin-right: 10px;"></i>Class</p>
        </div>
    </div>

    <div class="alert alert-success alert_add" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Class Have Be Added</h4>
    </div>
    
    <div class="alert alert-success alert_remove" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Class Have Been Remove</h4>
    </div>

    <div class="alert alert-success alert_checked_in" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Class Student Have Been Checked In</h4>
    </div>

    <div class="alert alert-success alert_checked_out" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Class Student Have Been Checked Out</h4>
    </div>

    <div class="row">
        <div class="col" style="padding-right: 30px;">
            <a href="classForm.php" class="btn btn-primary" style="float: right; margin: 5px; padding: 5px;" title="Add"><i class="fas fa-plus" style="font-size: 20px; padding: 6px 7px;"></i></a>
            <a href="#" class="btn btn-warning d-none" id="button_out" style="float: right; margin: 5px; padding: 5px; margin-right: 0" title="Check All Out"><i class="far fa-calendar-times" style="font-size: 20px; padding: 6px 7px; color: #fff"></i></a>
            <a href="#" class="btn btn-success" id="button_in" style="float: right; margin: 5px; padding: 5px; margin-right: 0" title="Check All In"><i class="far fa-calendar-check" style="font-size: 20px; padding: 6px 7px;"></i></a>
        </div>
    </div>
    
    <div style="padding: 0 30px;">
        <table id="myTable" class="display">  
            <thead>  
                <tr>  
                    <th>#</th>  
                    <th>Name</th>  
                    <th>Student Number</th>
                    <th>Checked In</th>
                    <th>Checked Out</th>
                    <th>Absent</th>
                    <th>Unassigned</th>
                    <th>Action</th>  
                </tr>  
            </thead>  
            <tbody>  
                <?php
                    $num = 1;
                    foreach (array_pop($classes) as $key => $value) {
                        $key = $marshaler->marshalJson('
                            {
                                "class_id": "' . $key . '"
                            }
                        ');
                        $params = [
                            'TableName' => "Classes",
                            'Key' => $key
                        ];
                        $result = get_item($params, $dynamodb);
                        $class = $result;

                        $student_ids = array_values($class['student_ids']);
                        $class_id = array_values($class['class_id']);
                        $i = 0;
                        $unassigned = 0;
                        $absent = 0;
                        $present = 0;
                        $checked_out = 0;
                        $checked_in = 0;

                        if(!empty($student_ids[0])){
                            foreach(($student_ids[0]) as $key => $value){
                                $i++;
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
                                        $present++;
                                    }
                                    else{
                                        $absent++;
                                    }
                                }
                                else{
                                    $unassigned++;
                                }
                            }
                        }   

                        if(array_pop($class['deleted_at']) === ""){
                        echo '
                          <tr data-value="'.$class_id[0].'">
                            <td>'.$num++.'</td>
                            <td>'.array_pop($class['name']).'</td>
                            <td>'.$i.'</td>
                            <td>'.$checked_in.'</td>
                            <td>'.$checked_out.'</td>
                            <td>'.$absent.'</td>
                            <td>'.$unassigned.'</td>
                            <td>
                                <a href="classForm.php?id='.$class_id[0].'" class="btn btn-info" style="padding: 5px;" title="Edit"><i class="fas fa-edit" style="padding: 6px 7px; color: #fff"></i></a>
                                <a href="classForm.php?type=delete&id='.$class_id[0].'" class="btn btn-danger" style="padding: 5px;" title="Delete" onclick="javascript:confirmationDelete($(this));return false;"><i class="fas fa-trash" style="padding: 6px 7px;"></i></a>
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
    
        $('#button_in').click( function () {
            var link = "checkInOut.php?type=check_in&cuid[]=";

            $.each(selected, (index, item) => {
                link += item;
                link += "&cuid[]=";
            });
            var result = confirm("Are you sure want to check in the whole class?");
            if(result)
            $(this).attr("href", link)
        } );

        $('#button_out').click( function () {
            var link = "checkInOut.php?type=check_out&cuid[]=";

            $.each(selected, (index, item) => {
                link += item;
                link += "&cuid[]=";
            });
            var result = confirm("Are you sure want to check out the whole class?");
            if(result)
            $(this).attr("href", link)
        } );
    });

    function confirmationDelete(anchor){
        var result = confirm("Caution!!!\nAre you sure want to delete this class?\nYou may lose the data for this class.");
        if(result){
            window.location = anchor.attr("href");
        }
    }
</script>
</body>
</html>