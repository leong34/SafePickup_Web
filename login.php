<?php
include "include/session.php";
include "include/dynamoDB_functions.php";

unset_all_session();

if(isset($_POST['email']) && isset($_POST['password'])){
    $eav = $marshaler->marshalJson('
        {
            ":email": "'.$_POST['email'].'" 
        }
    ');
    $params = [
        'TableName' => "Users",
        'IndexName' => "user_index",
        'KeyConditionExpression' => 'email = :email',
        'ExpressionAttributeValues'=> $eav
    ];

    $result = query_item($params, $dynamodb);
    if(!empty($result)){
        $result = array_pop($result);
        if(md5(array_pop($result['token']).$_POST['password']) === array_pop($result['password']) && array_pop($result['user_type']) === "1"){
            set_session(
                array(
                    'user_id'           => array_pop($result['user_id']),
                    'credential'        => md5(array_pop($result['user_id']).time()),
                    'organization_id'   => array_pop($result['organization_id'])
                )
            );

            $key = $marshaler->marshalJson('
                    {
                        "user_id": "'.$_SESSION['user_id'].'"
                    }
                ');

            $eav = $marshaler->marshalJson('
                    {
                        ":credential"     : "'.$_SESSION['credential'].'"
                    }
                ');

            $updateExpression = 'set credential = :credential';
            
            $params = [
                'TableName' => 'Users',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ReturnValues' => 'UPDATED_NEW'
            ];
            update_item($params, $dynamodb);
            header('Location: index.php');
        }
    }
}
else {
    echo '<style type="text/css">
            .alert {
                display: none;
            }
            </style>';
}

?>
<!DOCTYPE html>
<html style="height: 100%;">
<head>
<title>Login</title>
<?php include "include/heading.php";?>
</head>
<body style="overflow: hidden; height: 100%; display: grid; align-items: center;">
<div class="alert alert-danger" style="padding: 20px 40px; position: absolute; right: 0; margin: 20px; top: 0">
      <h4 class="alert-heading" style="margin: 0;">Email, Password Missmatch Or Don't Have Authority</h4>
</div>
<div class="row" style="padding: 20px 20px 20px 40px; display: flex; justify-content: center;">
    <div class="col-4 login-form">
        <h4 style="text-align: center; font-size: 30px; margin-bottom: 30px; font-weight: bold;">Safe Pickup</h4>
        <form class="needs-validation" action="" method="POST" autocomplete="off" novalidate>
            <div class="form-row">
                <div class="col-md-12 mb-3">
                <div class="input-group">
                    <input type="email" name="email" class="form-control" id="email" placeholder="Email" value="<?php echo isset($_POST['email'])? $_POST['email']:''; ?>" style="text-align: center;" required>
                    <div class="invalid-feedback" style="text-align: center;">
                        Please enter a valid email.
                    </div>
                </div>
                </div>
            </div>

            <div class="form-row">
                <div class="col-md-12 mb-3">
                <div class="input-group">
                    <input type="password" name="password" class="form-control" id="password" placeholder="Password" style="text-align: center;" required>
                    <div class="invalid-feedback" style="text-align: center;">
                        Password cannot be empty.
                    </div>
                </div>
                </div>
            </div>
            <div style="display: flex; justify-content: center;">
                <button class="btn btn-primary" type="submit" style="padding: 10px 25px; font-weight: bold;">LOGIN</button>
            </div>
            </form>
            
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
    </div>
</div>
</body>
</html>