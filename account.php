<?php
include "include/session.php";
include "include/user_Functions.php";
include "include/dynamoDB_functions.php";

check_session($dynamodb, $marshaler);

$user_id = $_SESSION['user_id'];
$key = $marshaler->marshalJson('
    {
        "user_id": "' . $user_id . '"
    }
');

$params = [
  'TableName' => "Users",
  'Key' => $key
];

$result = get_item($params, $dynamodb);
$user = $result;

if(isset($_POST['old_password']) && isset($_POST['new_password']) && isset($_POST['re_password']) && isset($_SESSION["user_id"])){
  if(md5(array_pop($user["token"]).$_POST['old_password']) === array_pop($user['password'])){
    $new_token = generateToken();

    $key = $marshaler->marshalJson('
            {
                "user_id": "'.$user_id.'"
            }
        ');

    $eav = $marshaler->marshalJson('
            {
                ":updated_at" : "'.time().'",
                ":token"      : "'.$new_token.'",
                ":password"   : "'.md5($new_token.$_POST['new_password']).'"
            }
        ');

    $updateExpression = 'set updated_at = :updated_at, #t = :token, password = :password';
    
    $params = [
        'TableName' => 'Users',
        'Key' => $key,
        'UpdateExpression' => $updateExpression,
        'ExpressionAttributeValues'=> $eav,
        'ExpressionAttributeNames' => ['#t' => 'token'],
        'ReturnValues' => 'ALL_NEW'
    ];
    $result = update_item($params, $dynamodb);
    $user = $result;

    echo '<style type="text/css">
        .alert-danger {
            display: none;
        }
        </style>';
  }
  else{
    echo '<style type="text/css">
    .alert:not(.alert-danger) {
        display: none;
    }
    </style>';
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
<html>
<head>
<title>Setting - Account</title>
<?php include "include/heading.php";?>
</head>
<body>
<?php include "include/navbar.php";?>

<div class="main">
    <div class="row">
        <div class="col-12 heading">
        <p><i class="fas fa-user" style="margin-right: 10px;"></i>Account</p>
        </div>
    </div>

    <div class="alert alert-success" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Successfully Update</h4>
    </div>

    <div class="alert alert-danger" role="alert" style="padding: 20px 20px 20px 40px;">
      <h4 class="alert-heading" style="margin: 0;">Old Password Is Not Match</h4>
    </div>

    <div class="row" style="padding: 20px 20px 20px 40px; display: flex; justify-content: center;">
        <div class="col-6">
            <form class="needs-validation" action="" method="POST" onsubmit="myValidation();" novalidate>
                <div class="form-row">
                  <div class="col-md-12 mb-3">
                    <label for="email_address">Email Address</label>
                    <div class="input-group">
                      <input type="text" name="email_address" class="form-control-plaintext" id="email_address" placeholder="Email Address" value="<?php echo array_pop($user['email']);?>" aria-describedby="inputGroupPrepend" readonly>
                    </div>
                  </div>
                </div>

                <div class="form-row">
                    <div class="col-md-12 mb-3">
                        <label for="old_password">Old Password</label>
                        <input type="password" name="old_password" class="form-control" id="old_password" required>
                        <div class="invalid-feedback">
                            Password cannot be empty.
                        </div>
                    </div>
                </div>

                <div class="form-row">
                  <div class="col-md-12 mb-3">
                    <label for="new_password">New Password</label>
                    <input type="password" name="new_password" class="form-control" id="new_password" required>
                    <div class="invalid-feedback">
                        Password cannot be empty.
                    </div>
                  </div>
                </div>

                <div class="form-row">
                    <div class="col-md-12 mb-3">
                        <label for="re_password">Re-type Password</label>
                        <input type="password" name="re_password" class="form-control" id="re_password" required>
                        <div class="invalid-feedback">
                            Password cannot be empty.
                        </div>
                        <div class="custom-invalid-feedback" id="re_password_invalid">
                          Password is not same.
                        </div>
                    </div>
                </div>
                
                <button class="btn btn-primary" type="submit" style="padding: 5px 20px;">Edit</button>
              </form>
              
              <script>
                function textOnChange(param){
                  var textChanger = param;
                  textChanger.addEventListener("input", function(){
                    myValidation();
                  })
                }

                textOnChange(document.getElementById('re_password'));
                textOnChange(document.getElementById('new_password'));

                function myValidation() {
                  var new_password = document.getElementById('new_password').value;
                  var re_password = document.getElementById('re_password').value;

                  if(re_password === ""){
                    document.getElementById("re_password_invalid").style.display = "none";
                  }
                  else if (new_password != re_password) {
                    event.preventDefault();
                    document.getElementById("re_password_invalid").style.display = "block";
                    document.getElementById("re_password").classList.add("custom-invalid-field");
                    return false;
                  }
                  else{
                    document.getElementById("re_password_invalid").style.display = "none";
                    document.getElementById("re_password").classList.remove("custom-invalid-field");
                  }
                  return true;
                }

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

</div>
</body>
</html>