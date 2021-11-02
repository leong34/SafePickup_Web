<?php
  include "include/session.php";
  include "include/dynamoDB_functions.php";

  check_session($dynamodb, $marshaler);

  $organization_id = $_SESSION['organization_id'];
  $organization = getOrganization($organization_id, $dynamodb, $marshaler);

  if(isset($_POST['organization_name'])){
    $mailingEnable = isset($_POST["mailing"]) ? 1 : 0;

    $key = $marshaler->marshalJson('
            {
                "organization_id": "'.$organization_id.'"
            }
        ');

    $eav = $marshaler->marshalJson('
            {
                ":name"         : "'.$_POST['organization_name'].'",
                ":address_1"    : "'.$_POST['address_1'].'",
                ":address_2"    : "'.$_POST['address_2'].'",
                ":city"         : "'.$_POST['city'].'",
                ":state"        : "'.$_POST['state'].'",
                ":zip"          : "'.$_POST['zip'].'",
                ":updated_at"   : "'.time().'",
                ":check_in_time" : "'.date('H:i:s', strtotime($_POST['check_in_time'])).'",
                ":check_out_time": "'.date('H:i:s', strtotime($_POST['check_out_time'])).'",
                ":late_threshold": "'.(int)$_POST['late_time'].'",
                ":encryptCode"  : "'.$_POST['encryptCode'].'",
                ":mailing"      : '.$mailingEnable.'
            }
        ');

    $updateExpression = 'set #n = :name, address_1 = :address_1, address_2 = :address_2, city = :city, #s = :state, zip = :zip, updated_at = :updated_at, check_in_time = :check_in_time, check_out_time = :check_out_time, late_threshold = :late_threshold, encryptCode = :encryptCode, mailing = :mailing';
    
    $params = [
        'TableName' => 'Organizations',
        'Key' => $key,
        'UpdateExpression' => $updateExpression,
        'ExpressionAttributeValues'=> $eav,
        'ExpressionAttributeNames' => [ '#n' => 'name', '#s' => 'state' ],
        'ReturnValues' => 'ALL_NEW'
    ];
    $result = update_item($params, $dynamodb);
    $organization = $result;
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
<title>Setting - Organization</title>
<?php include "include/heading.php";?>
    
</head>
<body>
<?php include "include/navbar.php";?>

<div class="main">
    <div class="row">
        <div class="col-12 heading">
        <p><i class="fas fa-building" style="margin-right: 10px;"></i>Organization</p>
        </div>
    </div>    
  
    <div class="alert alert-success" role="alert" style="padding: 20px 20px 20px 40px; color: #0f5132; background-color: #d1e7dd; border-color: #badbcc;">
      <h4 class="alert-heading" style="margin: 0; font-size: 25px; font-weight: bold;">Successfully Edit</h4>
    </div>

    <div class="row" style="padding: 20px 20px 20px 40px; display: flex; justify-content: center;">
        <div class="col-6">
            <form class="needs-validation" action="" method="POST" onsubmit="myValidation();" novalidate autocomplete="off">
                <div class="form-row">
                  <div class="col-md-12 mb-3">
                    <label for="organization_name">Organization Name</label>
                    <div class="input-group">
                      <input type="text" name="organization_name" class="form-control" id="organization_name" placeholder="Organization Name" value="<?php echo array_pop($organization['name']);?>" aria-describedby="inputGroupPrepend" required>
                      <div class="invalid-feedback">
                        Please enter an organization name.
                      </div>
                    </div>
                  </div>
                </div>

                <div class="form-row">
                  <div class="col-md-12 mb-3">
                    <label for="address_1">Address 1</label>
                    <div class="input-group">
                      <input type="text" name="address_1" class="form-control" id="address_1" placeholder="Address 1" value="<?php echo array_pop($organization['address_1']);?>" aria-describedby="inputGroupPrepend" required>
                      <div class="invalid-feedback">
                        Please enter an address.
                      </div>
                    </div>
                  </div>
                </div>

                <div class="form-row">
                  <div class="col-md-12 mb-3">
                    <label for="address_2">Address 2</label>
                    <div class="input-group">
                      <input type="text" name="address_2" class="form-control" id="address_2" placeholder="Address 2" value="<?php echo array_pop($organization['address_2']);?>" aria-describedby="inputGroupPrepend">
                    </div>
                  </div>
                </div>

                <div class="form-row">
                  <div class="col-md-12 mb-3">
                    <label for="city">City</label>
                    <input type="text" name="city" class="form-control" id="city" placeholder="City" value="<?php echo array_pop($organization['city']);?>" required>
                    <div class="invalid-feedback">
                      Please provide a valid city.
                    </div>
                  </div>
                </div>

                <div class="form-row">
                  <div class="col-md-6 mb-3">
                    <label for="state">State</label>
                    <input type="text" name="state" class="form-control" id="state" placeholder="State" value="<?php echo array_pop($organization['state']);?>" required>
                    <div class="invalid-feedback">
                      Please provide a valid state.
                    </div>
                  </div>

                  <div class="col-md-6 mb-3">
                    <label for="zip">Zip</label>
                    <input type="text" name="zip" class="form-control" id="zip" placeholder="Zip" value="<?php echo array_pop($organization['zip']);?>" required>
                    <div class="invalid-feedback">
                      Please provide a valid zip.
                    </div>
                  </div>
                </div>

                <div class="form-row">
                  <div class='col-sm-6'>
                      <div class="form-group">
                        <label for="state">Start Time</label>
                          <div class='input-group date' id='datetimepicker1'>
                              <input type='text' name="check_in_time" id="check_in_time" class="form-control" value="<?php echo date('H:i', strtotime(array_values($organization['check_in_time'])[0]));?>" required/>
                              <span class="input-group-addon">
                                  <span class="glyphicon glyphicon-calendar"></span>
                              </span>
                          </div>
                      </div>
                  </div>
                  <div class='col-sm-6'>
                      <div class="form-group">
                      <label for="state">End Time</label>
                          <div class='input-group date' id='datetimepicker2'>
                              <input type='text' name="check_out_time" id="check_out_time" class="form-control" value="<?php echo date('H:i', strtotime(array_values($organization['check_out_time'])[0]));?>" required/>
                              <span class="input-group-addon">
                                  <span class="glyphicon glyphicon-calendar"></span>
                              </span>
                          </div>
                      </div>
                  </div>

                  <div class='col-sm-12 mb-3'>
                        <div class="custom-invalid-feedback" id="in_time_required">
                            Time is needed.
                        </div>
                        <div class="custom-invalid-feedback" id="invalid_time">
                            End time cannot less than start time.
                        </div>
                        <div class="custom-invalid-feedback" id="invalid_time_over">
                            Start and end time must between 08:00 - 20:00.
                        </div>
                  </div>
                </div>

                <div class="form-row" style="display: inline-block; width: 100%; position: relative;">
                  <div class="col-md-6 mb-3">
                    <label for="state">Late Time Threshold in Minutes</label>
                    <input type="number" name="late_time" class="form-control" id="late_time" placeholder="30" min="0" step="1" max="120" value="<?php echo array_values($organization['late_threshold'])[0];?>" required>
                    <div class="invalid-feedback">
                      Please add a valid threshold time between 0 - 120.
                    </div>
                  </div>

                  <div class="col-md-6 mb-3" style="position: absolute; right: 0; top: 40%;">
                    <div class="form-check">
                      <input class="form-check-input" name="mailing" type="checkbox" value="1" id="mailing" <?php echo array_values($organization['mailing'])[0] ? "checked" : "";?>>
                      <label class="form-check-label" for="mailing">
                        Mail To New Created Guardian
                      </label>
                    </div>
                  </div>
                </div>

                <div class="form-row">
                  <div class="col-md-6 mb-3">
                    <label for="state">Encrypted Check In Code</label>
                    <div style="position: relative;">
                    <input type="text" name="encryptCode" class="form-control" id="encryptCode" placeholder="Encrypted Check In Code" value="<?php echo array_values($organization['encryptCode'])[0];?>" readonly>
                    <i id="refresh" class="fas fa-sync-alt" title="Sync" style="position: absolute; bottom: 30%; right: 3%;"></i>
                    </div>
                    <img src="https://chart.googleapis.com/chart?chs=280x280&cht=qr&chl=<?php echo array_values($organization['encryptCode'])[0]; ?>&choe=UTF-8" title="Check In Qr Code" id="check_in_qr"/>
                  </div>
                </div>

                <div class="form-row">
                  <div class="col-md-12 mb-3">
                  <button class="btn btn-primary btn-primary-default" type="submit" style="padding: 5px 20px;">Edit</button>
                  </div>
                </div>

                

                <script type="text/javascript">
                    $(function () {
                      $("#datetimepicker1").datetimepicker({
                        format: 'HH:mm',
                        stepping: 30         
                      });
                    });

                    $(function () {
                        $('#datetimepicker2').datetimepicker({
                          format: 'HH:mm',
                          stepping: 30
                        });
                    });
                </script>
                
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
    
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
      <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
      <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    
      <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.15.1/moment.min.js"></script>
      <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.43/css/bootstrap-datetimepicker.min.css"> 
      <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.43/css/bootstrap-datetimepicker-standalone.css"> 
      <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.43/js/bootstrap-datetimepicker.min.js"></script>
</div>
</body>
<script src="http://www.myersdaily.org/joseph/javascript/md5.js"></script>
<script>
  function myValidation() {
    var check_in_time = document.getElementById('check_in_time').value;
    var check_out_time = document.getElementById('check_out_time').value;

    if(check_in_time === "" || check_out_time === ""){
      document.getElementById("in_time_required").style.display = "block";
      return false;
    }
    else{
      document.getElementById("in_time_required").style.display = "none";
    }

    var startHour = parseInt(check_in_time.substring(0, 2));
    var startMin = parseInt(check_in_time.substring(4));

    var endHour = parseInt(check_out_time.substring(0, 2));
    var endMin = parseInt(check_out_time.substring(4));
    if((check_in_time >= "08:00" && check_in_time <= "20:00") ||(check_out_time >= "08:00" && check_out_time <= "20:00")){
      document.getElementById("invalid_time_over").style.display = "none";
      document.getElementById("check_in_time").classList.remove("custom-invalid-field");
      document.getElementById("check_out_time").classList.remove("custom-invalid-field");
    }

    if(check_in_time < "08:00" || check_in_time > "20:00"){
      document.getElementById("invalid_time_over").style.display = "block";
      document.getElementById("check_in_time").classList.add("custom-invalid-field");
      document.getElementById("check_out_time").classList.add("custom-invalid-field");
      event.preventDefault();
    }else if(check_out_time < "08:00" || check_out_time > "20:00"){
      document.getElementById("invalid_time_over").style.display = "block";
      document.getElementById("check_in_time").classList.add("custom-invalid-field");
      document.getElementById("check_out_time").classList.add("custom-invalid-field");
      event.preventDefault();
    }
    else if ((startHour == endHour && startMin == endMin)
          || (startHour > endHour)) {
      document.getElementById("invalid_time").style.display = "block";
      document.getElementById("check_in_time").classList.add("custom-invalid-field");
      document.getElementById("check_out_time").classList.add("custom-invalid-field");
      event.preventDefault();
    }else{
      document.getElementById("invalid_time").style.display = "none";
      document.getElementById("check_in_time").classList.remove("custom-invalid-field");
      document.getElementById("check_out_time").classList.remove("custom-invalid-field");
    }

    return true;
  }

  $("#refresh").on("click", function () {
      var characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
      var charactersLength = characters.length;
      var randomString = '';
      for (var i = 0; i < 10; i++) {
        randomString += characters[Math.floor(Math.random() * charactersLength)];
      }
      var encryptedCode = md5(randomString);
      document.getElementById('encryptCode').value = encryptedCode;
      document.getElementById('check_in_qr').src = "https://chart.googleapis.com/chart?chs=280x280&cht=qr&chl=" + encryptedCode + "&choe=UTF-8";
  });
</script>
</html>