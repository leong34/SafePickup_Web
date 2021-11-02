<?php
include "include/mailing.php";
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

$event = NULL;


if(isset($_GET['type']) && $_GET['type'] == "add"){
    $event_id = "EV".time()."".generateToken()."";

    $item = array(
        "event_id"         => $event_id,
        "created_at"       => time(),
        "updated_at"       => time(),
        "deleted_at"       => "",
        "description"      => $_GET['description'],
        "title"            => $_GET['title'],
        "date"             => $_GET['date'],
        "organization_id"  => $organization_id,
        "class_id"         => $_GET['class_id'],
        "type"             => "event"
    );

    $item = $marshaler->marshalJson(json_encode($item));
    $params = [
        'TableName' => "Events",
        'Item' => $item
    ];
    add_item($params, $dynamodb);

    $key = $marshaler->marshalJson('
            {
                "organization_id"      : "'.$organization_id.'"
            }
        ');

    $eav = $marshaler->marshalJson('
            {
                ":eids"     : "'.$event_id.'"
            }
        ');

    $updateExpression = 'set event_ids.#eid = :eids';

    $params = [
        'TableName' => 'Organizations',
        'Key' => $key,
        'UpdateExpression' => $updateExpression,
        'ExpressionAttributeValues'=> $eav,
        'ExpressionAttributeNames'=> ["#eid" => $event_id],
        'ReturnValues' => 'ALL_NEW'
    ];
    $result = update_item($params, $dynamodb);
    $_SESSION['class_added'] = true;
    header('Location: event.php');
}
else if(isset($_GET['type']) && $_GET['type'] == "edit" && isset($_GET['id'])){
    $key = $marshaler->marshalJson('
            {
                "event_id"      : "'.$_GET['id'].'"
            }
        ');

    $item = array(
        ":updated_at"       => time(),
        ":description"      => trim($_GET['description']),
        ":title"            => trim($_GET['title']),
        ":date"             => $_GET['date'],
        ":cid"              => $_GET['class_id']
    );

    $eav = $marshaler->marshalJson(json_encode($item));

    $updateExpression = 'set updated_at = :updated_at, description = :description, title = :title, class_id = :cid, #d = :date';

    $params = [
        'TableName' => 'Events',
        'Key' => $key,
        'UpdateExpression' => $updateExpression,
        'ExpressionAttributeValues'=> $eav,
        'ExpressionAttributeNames'=> ["#d" => "date"],
        'ReturnValues' => 'ALL_NEW'
    ];

    $result = update_item($params, $dynamodb);
    $event_arr = getEventsData($events, $marshaler, $dynamodb);
}
else if(isset($_GET['type']) && $_GET['type'] == "delete" && isset($_GET['id'])){
    $key = $marshaler->marshalJson('
            {
                "event_id"      : "'.$_GET['id'].'"
            }
        ');

    $item = array(
        ":updated_at"       => time(),
        ":deleted_at"       => time()
    );

    $eav = $marshaler->marshalJson(json_encode($item));

    $updateExpression = 'set updated_at = :updated_at, deleted_at = :deleted_at';

    $params = [
        'TableName' => 'Events',
        'Key' => $key,
        'UpdateExpression' => $updateExpression,
        'ExpressionAttributeValues'=> $eav,
        'ReturnValues' => 'ALL_NEW'
    ];

    $result = update_item($params, $dynamodb);

    $key = $marshaler->marshalJson('
            {
                "organization_id"      : "'.$organization_id.'"
            }
        ');

    $updateExpression = 'remove event_ids.#e';

    $params = [
        'TableName' => 'Organizations',
        'Key' => $key,
        'UpdateExpression' => $updateExpression,
        'ExpressionAttributeNames'=> ["#e" => $_GET['id']],
        'ReturnValues' => 'ALL_NEW'
    ];
    $result = update_item($params, $dynamodb);

    $_SESSION['class_delete'] = true;
    header('Location: event.php');
}
else{
    echo '<style type="text/css">
    .alert {
        display: none;
    }
    </style>';
}

if(isset($_GET['id']) && $_GET['id'] !== ""){
    $event = $event_arr[$_GET['id']];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Event</title>
<?php include "include/heading.php";?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>
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
            <p><i class="fas fa-calendar" style="margin-right: 10px;"></i>Event</p>
        </div>
    </div>

    <div class="alert alert-success" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Successfully Update</h4>
    </div>
    
    <div class="row" style="padding: 20px 20px 20px 40px; display: flex; justify-content: center;">
        <div class="col-6">
            <form class="needs-validation" action="" method="GET" novalidate autocomplete="off">
                <div class="form-row" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                  <div class="col">
                    <label for="title">Title</label>
                    <div class="input-group">
                      <input type="hidden" name="id" value="<?php if($event !== NULL) echo $_GET['id'];?>">

                      <input type="text" name="title" value="<?php if($event !== NULL) echo htmlentities(array_values($event['title'])[0]);?>" class="form-control" id="title" placeholder="Title" aria-describedby="inputGroupPrepend" autocomplete="off" required>
                      <div class="invalid-feedback">
                        Title Cant Be Empty.
                      </div>
                    </div>
                  </div>
                </div>

                <div class="form-row" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div class="col-7">
                        <label for="date">Date</label>
                        <div class="input-group">

                        <input type="text" name="date" class="form-control" id="datepicker" value="<?php if($event !== NULL) echo array_values($event['date'])[0];?>" required>
                        <div class="invalid-feedback">
                            Date Cant Be Empty.
                        </div>
                        </div>
                    </div>

                    <div class="col-4">
                    <label for="class_id">Class</label>
                    <select class="form-select" name="class_id" required>
                        <?php
                            $event_class = "";
                            if($event !== NULL){
                                $event_class = array_values($event['class_id'])[0];
                            }
                            
                            foreach($class_arr as $key => $value){
                                if($event_class === $key)
                                    echo '<option value='.$key.' selected>'.array_values($value['name'])[0].'</option>';
                                else
                                    echo '<option value='.$key.'>'.array_values($value['name'])[0].'</option>';
                            }
                        ?>
                    </select>
                  </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" rows="3" name="description"><?php if($event !== NULL) echo array_values($event['description'])[0];?></textarea>
                </div>

                <?php 
                    if($event !== NULL){
                        echo '<button class="btn btn-warning" type="submit" style="padding: 5px 20px; margin-top: 20px;" name="type" value="edit">Edit</button>';
                    }
                    else{
                        echo '<button class="btn btn-success" type="submit" style="padding: 5px 20px; margin-top: 20px;" name="type" value="add">Submit</button>';
                    }
                ?>
              </form>
        </div>
    </div>
</div>

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

    $(document).ready(function(){
        $('#datepicker').datepicker({
            format: 'yyyy-mm-dd',
            startDate: 'Today',
            todayHighlight: true
        });
    });
</script>

</body>
</html>