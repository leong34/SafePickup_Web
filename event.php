<?php
include "include/session.php";
include "include/user_Functions.php";
include "include/dynamoDB_functions.php";

check_session($dynamodb, $marshaler);

$organization_id = $_SESSION['organization_id'];
$organization = getOrganization($organization_id, $dynamodb, $marshaler);

$events = array_values($organization['event_ids']);
$event_arr = getEventsData($events, $marshaler, $dynamodb);

$classes = array_values($organization['class_ids']);
$class_arr = getClassesData($classes, $marshaler, $dynamodb);


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
<title>Event</title>
<?php include "include/heading.php";?>
</head>
<body>
<?php include "include/navbar.php";?>

<div class="main">
    <div class="row">
        <div class="col-12 heading">
            <p><i class="fas fa-calendar" style="margin-right: 10px;"></i>Event</p>
        </div>
    </div>

    <div class="alert alert-success alert_add" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Event Have Been Created</h4>
    </div>

    <div class="alert alert-success alert_remove" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Event Have Been Deleted</h4>
    </div>

    <div class="row">
        <div class="col" style="padding-right: 30px;">
            <a href="noticeForm.php?id[]='.$key.'&type=delete" class="btn btn-danger d-none" style="float: right; margin: 5px; padding: 5px;" title="Delete" onclick="javascript:confirmationDelete($(this));return false;"><i class="fas fa-trash" style="font-size: 20px; padding: 6px 7px;""></i></a>
            <a href="eventForm.php" class="btn btn-primary" style="float: right; margin: 5px; padding: 5px;" title="Add"><i class="fas fa-plus" style="font-size: 20px; padding: 6px 7px;"></i></a>
        </div>
    </div>
    
    <div style="padding: 0 30px;">
        <table id="myTable" class="display">  
            <thead>  
                <tr>  
                    <th>#</th>  
                    <th>Title</th>  
                    <th>Date</th>  
                    <th>Class</th>
                    <th>Status</th>
                    <th>Action</th>  
                </tr>  
            </thead>  
            <tbody>  
                <?php
                    $num = 1;
                    foreach ($event_arr as $key => $value) {
                        if(array_values($value['deleted_at'])[0] === ""){
                            echo '
                            <tr data-value="'.$key.'">
                              <td>'.$num++.'</td>
                              <td>'.array_values($value['title'])[0].'</td>
                              <td>'.array_values($value['date'])[0].'</td>
                              <td>'.array_values($class_arr[array_values($value['class_id'])[0]]['name'])[0].'</td>';

                              if(array_values($value['date'])[0] < date("Y-m-d")){
                                  echo "<td>Passed</td>";
                              }
                              else if(array_values($value['date'])[0] > date("Y-m-d")){
                                  echo "<td>Upcoming</td>";
                              }
                              else{
                                  echo "<td>Ongoing</td>";
                              }
                            echo '
                              <td>
                                  <a href="eventForm.php?id='.$key.'" class="btn btn-info" style="padding: 5px;" title="Edit"><i class="fas fa-edit" style="padding: 6px 7px; color: #fff"></i></a>
                                  <a href="eventForm.php?id='.$key.'&type=delete" class="btn btn-danger" style="padding: 5px;" title="Delete" onclick="javascript:confirmationDelete($(this));return false;"><i class="fas fa-trash" style="padding: 6px 7px;"></i></a>
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
    });

    function confirmationDelete(anchor){
        var result = confirm("Are you sure want to delete this event?");
        if(result){
            window.location = anchor.attr("href");
        }
    }
</script>
</body>
</html>