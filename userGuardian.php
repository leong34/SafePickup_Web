<?php
include "include/session.php";
include "include/user_Functions.php";
include "include/dynamoDB_functions.php";

check_session($dynamodb, $marshaler);

$organization_id = $_SESSION['organization_id'];
$organization = getOrganization($organization_id, $dynamodb, $marshaler);

$users = array_values($organization['user_ids'])[0];
$user_arr = array();

$guardian_arr = getGuardiansData($users, $marshaler, $dynamodb);

// loop get user that is guardian type
foreach($users as $key => $value){
    $key = $marshaler->marshalJson('
        {
            "user_id": "' . $key . '"
        }
    ');
    $params = [
        'TableName' => "Users",
        'Key' => $key
    ];
    $result = get_item($params, $dynamodb);

    if(array_values($result['user_type'])[0] !== "1"){
        $user_arr[array_values($value)[0]] = $result;
    }
}

$extracted_guardian_name = array();
foreach($guardian_arr as $key => $value){
    $extracted_guardian_name[$key] = array_values(array_values($value['info'])[0]['last_name'])[0]." ".array_values(array_values($value['info'])[0]['first_name'])[0];
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

if(!isset($_SESSION['mail_send'])){
    echo '<style type="text/css">
        .alert_email{
            display: none;
        }
        </style>';
}
else{
    unset($_SESSION['mail_send']);
}

if(!isset($_SESSION['class_delete_main'])){
    echo '<style type="text/css">
        .alert_delete_main{
            display: none;
        }
        </style>';
}
else{
    unset($_SESSION['class_delete_main']);
}
?>

<!DOCTYPE html>
<html>
<head>
<title>User - Guardian</title>
<?php include "include/heading.php";?>
</head>
<body>
<?php include "include/navbar.php";?>

<div class="main">
    <div class="row">
        <div class="col-12 heading">
            <p><i class="fas fa-user-tie" style="margin-right: 10px;"></i>Guardian</p>
        </div>
    </div>

    <div class="alert alert-success alert_add" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Guardian Have Been Added</h4>
    </div>

    <div class="alert alert-success alert_remove" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Guardian Have Been Deleted</h4>
    </div>

    <div class="alert alert-success alert_delete_main" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Guardian And All Its Sub Have Been Deleted</h4>
    </div>

    <div class="alert alert-success alert_email" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Invitation Email Have Been Send</h4>
    </div>

    <div class="row">
        <div class="col" style="padding-right: 30px;">
            <a href="userGuardianForm.php" class="btn btn-primary" style="float: right; margin: 10px; padding: 5px;" title="Add"><i class="fas fa-plus" style="font-size: 20px; padding: 6px 7px;"></i></a>
        </div>
    </div>
    
    <div style="padding: 0 30px;">
        <table id="myTable" class="display">  
            <thead>  
                <tr>  
                    <th>#</th>  
                    <th>Name</th>  
                    <th>Email</th>  
                    <th>Tel</th>
                    <th>Created By</th>
                    <th>Activated</th>
                    <th>Action</th>  
                </tr>  
            </thead>  
            <tbody>  
                <?php
                    $num = 1;
                    foreach ($user_arr as $key => $value) {
                        if(array_values($value['deleted_at'])[0] === ""){
                            $verified = (empty(array_values($value['verified_at'])[0]) ? "Inactivated" : "Activated");
                          echo '
                          <tr>
                            <td>'.array_values($value['user_internal_id'])[0].'</td>
                            <td>'.array_values(array_values($value['info'])[0]['last_name'])[0].' '.array_values(array_values($value['info'])[0]['first_name'])[0].'</td>
                            <td>'.array_values($value['email'])[0].'</td>
                            <td>'.array_values(array_values($value['info'])[0]['tel_num'])[0].'</td>';
                            if(isset($value['created_by']) && isset($extracted_guardian_name[array_values($value['created_by'])[0]])){
                                echo '<td><a href="userGuardianForm.php?id='.array_values($value['created_by'])[0].'" class="link" target="blank">'.$extracted_guardian_name[array_values($value['created_by'])[0]].'</a></td>'; 
                            }    
                            else {  
                                echo '<td>Admin</td>';
                            }   

                            if($verified == "Inactivated"){
                                echo '<td><a href="resend_invitation_email.php?id='.$key.'" class="link" title="Send Invitation Email" onclick="javascript:confirmSendEmail($(this));return false;">'.$verified.'</a></td>';
                            }
                            else{
                                echo '<td>'.$verified.'</td>';
                            }

                            if(isset($value['created_by']) && isset($extracted_guardian_name[array_values($value['created_by'])[0]])){
                                echo'<td>
                                        <a href="userGuardianForm.php?id='.$key.'" class="btn btn-info" style="padding: 5px;" title="Edit"><i class="fas fa-edit" style="padding: 6px 7px; color: #fff"></i></a>
                                        <a href="userGuardianForm.php?id='.$key.'&type=delete" class="btn btn-danger" style="padding: 5px;" title="Delete" onclick="javascript:confirmationDeleteSub($(this));return false;"><i class="fas fa-trash" style="padding: 6px 7px;"></i></a>
                                    </td>
                                </tr>';
                            }    
                            else {  
                                echo'<td>
                                        <a href="userGuardianForm.php?id='.$key.'" class="btn btn-info" style="padding: 5px;" title="Edit"><i class="fas fa-edit" style="padding: 6px 7px; color: #fff"></i></a>
                                        <a href="userGuardianForm.php?id='.$key.'&type=delete" class="btn btn-danger" style="padding: 5px;" title="Delete" onclick="javascript:confirmationDelete($(this));return false;"><i class="fas fa-trash" style="padding: 6px 7px;"></i></a>
                                    </td>
                                </tr>';
                            }
                      }
                    }
                ?>
<!--                 
                <a href="resend_invitation_email.php?id='.$key.'" class="btn btn-primary" style="padding: 5px;" title="Send Invitation Email" onclick="javascript:confirmSendEmail($(this));return false;"><i class="fas fa-envelope" style="padding: 6px 7px; color: #fff"></i></a> -->
            </tbody>  
        </table>
    </div>

</div>

<script>
    $(document).ready(function(){
        $('#myTable').dataTable();
    });

    function confirmationDelete(anchor){
        var result = confirm("Are you sure want to delete this user?\nNotice!!!\nAll sub guardian under this user will be delete too!");
        if(result){
            window.location = anchor.attr("href");
        }
    }

    function confirmationDeleteSub(anchor){
        var result = confirm("Are you sure want to delete this user?");
        if(result){
            window.location = anchor.attr("href");
        }
    }

    function confirmSendEmail(anchor){
        var result = confirm("Are you sure want to resend invitation email to this user?");
        if(result){
            window.location = anchor.attr("href");
        }
    }
</script>
</body>
</html>