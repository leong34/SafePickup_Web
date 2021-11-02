<?php
include "include/mailing.php";
include "include/session.php";
include "include/user_Functions.php";
include "include/dynamoDB_functions.php";

check_session($dynamodb, $marshaler);

$organization_id = $_SESSION['organization_id'];
$organization = getOrganization($organization_id, $dynamodb, $marshaler);

$notices = array_values($organization['notice_ids']);
$notice_arr = getNoticesData($notices, $marshaler, $dynamodb);
$notice = NULL;

if(isset($_GET['type']) && $_GET['type'] == "add"){
    $notice_id = "N".time()."".generateToken()."";
    $item = array(
        "notice_id"        => $notice_id,
        "created_at"       => time(),
        "updated_at"       => time(),
        "deleted_at"       => "",
        "description"      => trim($_GET['description']),
        "title"            => trim($_GET['title']),
        "status"           => $_GET['status'],
        "organization_id"  => $organization_id,
        "view_by"          => array("admin" => "admin")
    );

    $item = $marshaler->marshalJson(json_encode($item));
    
    $params = [
        'TableName' => "Notices",
        'Item' => $item
    ];
    add_item($params, $dynamodb);

    $key = $marshaler->marshalJson('
            {
                "notice_id"      : "'.$notice_id.'"
            }
        ');
    $eav = $marshaler->marshalJson('
            {
                ":view_by"     : {}
            }
        ');

    $updateExpression = 'set view_by = :view_by';

    $params = [
        'TableName' => 'Notices',
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

    $eav = $marshaler->marshalJson('
            {
                ":nids"     : "'.$notice_id.'"
            }
        ');

    $updateExpression = 'set notice_ids.#n = :nids';

    $params = [
        'TableName' => 'Organizations',
        'Key' => $key,
        'UpdateExpression' => $updateExpression,
        'ExpressionAttributeValues'=> $eav,
        'ExpressionAttributeNames'=> ["#n" => $notice_id],
        'ReturnValues' => 'ALL_NEW'
    ];
    $result = update_item($params, $dynamodb);
    $_SESSION['class_added'] = true;
    header('Location: notice.php');
}
else if(isset($_GET['type']) && $_GET['type'] == "edit" && isset($_GET['id'])){
    $key = $marshaler->marshalJson('
            {
                "notice_id"      : "'.$_GET['id'].'"
            }
        ');

    $item = array(
        ":updated_at"       => time(),
        ":description"      => trim($_GET['description']),
        ":title"            => trim($_GET['title']),
        ":status"           => $_GET['status']
    );

    $eav = $marshaler->marshalJson(json_encode($item));

    $updateExpression = 'set updated_at = :updated_at, description = :description, title = :title, #s = :status';

    $params = [
        'TableName' => 'Notices',
        'Key' => $key,
        'UpdateExpression' => $updateExpression,
        'ExpressionAttributeValues'=> $eav,
        'ExpressionAttributeNames'=> ["#s" => "status"],
        'ReturnValues' => 'ALL_NEW'
    ];

    $result = update_item($params, $dynamodb);
    $notice_arr = getNoticesData($notices, $marshaler, $dynamodb);
}
else if(isset($_GET['type']) && $_GET['type'] == "delete" && isset($_GET['id'])){
    $key = $marshaler->marshalJson('
            {
                "notice_id"      : "'.$_GET['id'].'"
            }
        ');

    $item = array(
        ":updated_at"       => time(),
        ":deleted_at"       => time()
    );

    $eav = $marshaler->marshalJson(json_encode($item));

    $updateExpression = 'set updated_at = :updated_at, deleted_at = :deleted_at';

    $params = [
        'TableName' => 'Notices',
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

    $updateExpression = 'remove notice_ids.#n';

    $params = [
        'TableName' => 'Organizations',
        'Key' => $key,
        'UpdateExpression' => $updateExpression,
        'ExpressionAttributeNames'=> ["#n" => $_GET['id']],
        'ReturnValues' => 'ALL_NEW'
    ];
    $result = update_item($params, $dynamodb);

    $_SESSION['class_delete'] = true;
    header('Location: notice.php');
}
else{
    echo '<style type="text/css">
    .alert {
        display: none;
    }
    </style>';
}

if(isset($_GET['id']) && $_GET['id'] !== ""){
    $notice = $notice_arr[$_GET['id']];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Notice</title>
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
            <p><i class="fas fa-clipboard" style="margin-right: 10px;"></i>Notice</p>
        </div>
    </div>

    <div class="alert alert-success" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Successfully Update</h4>
    </div>
    
    <div class="row" style="padding: 20px 20px 20px 40px; display: flex; justify-content: center;">
        <div class="col-6">
            <form class="needs-validation" action="" method="GET" novalidate autocomplete="off">
                <div class="form-row" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                  <div class="col-8">
                    <label for="title">Title</label>
                    <div class="input-group">
                      <input type="hidden" name="id" value="<?php if($notice !== NULL) echo $_GET['id'];?>">

                      <input type="text" name="title" value="<?php if($notice !== NULL) echo htmlentities(array_values($notice['title'])[0]);?>" class="form-control" id="title" placeholder="Title" aria-describedby="inputGroupPrepend" autocomplete="off" required>
                      <div class="invalid-feedback">
                        Title Cant Be Empty.
                      </div>
                    </div>
                  </div>

                  <div class="col-3 form-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <?php 
                        if($notice !== NULL && array_values($notice['status'])[0] == "Enable"){
                            echo '<option value="Enable" selected>Enable</option>
                                  <option value="Disable">Disable</option>';
                        }
                        else if($notice !== NULL && array_values($notice['status'])[0] == "Disable"){
                            echo '<option value="Enable">Enable</option>
                                  <option value="Disable" selected>Disable</option>';
                        }
                        else{
                            echo '<option value="Enable">Enable</option>
                                  <option value="Disable">Disable</option>';
                        }
                        ?>
                    </select>
                  </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" rows="3" name="description"><?php if($notice !== NULL) echo array_values($notice['description'])[0];?></textarea>
                </div>

                <?php 
                    if($notice !== NULL){
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
</script>

</body>
</html>