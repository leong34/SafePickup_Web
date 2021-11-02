<?php
include "include/dynamoDB_functions.php";

$verified = 2;

if(isset($_GET['email']) && isset($_GET['token'])){
    $eav = $marshaler->marshalJson('
        {
            ":email": "'.$_GET['email'].'" 
        }
    ');
    $params = [
        'TableName' => "Users",
        'IndexName' => "user_index",
        'KeyConditionExpression' => 'email = :email',
        'ExpressionAttributeValues'=> $eav
    ];

    $result = query_item($params, $dynamodb);
    $result = array_pop($result);

    if(md5(array_pop($result['token'])) == $_GET['token']){
        if(array_pop($result['verified_at']) == ""){
            $user_id = array_pop($result['user_id']);
            $key = $marshaler->marshalJson('
                    {
                        "user_id": "'.$user_id.'"
                    }
                ');

            $eav = $marshaler->marshalJson('
                    {
                        ":updated_at"     : "'.time().'",
                        ":verified_at"    : "'.time().'"
                    }
                ');

            $updateExpression = 'set verified_at = :verified_at, updated_at = :updated_at';
            
            $params = [
                'TableName' => 'Users',
                'Key' => $key,
                'UpdateExpression' => $updateExpression,
                'ExpressionAttributeValues'=> $eav,
                'ReturnValues' => 'UPDATED_NEW'
            ];
            update_item($params, $dynamodb);
            $verified = 0;
        }
        else{
            $verified = 1;
        }
    }    
}
?>
<!DOCTYPE html>
<html style="height: 100%;">
<head>
<title>Account Verification</title>
<?php include "include/heading.php";?>
</head>
<body style="overflow: hidden; height: 100%; display: grid; align-items: center;">
<div class="row" style="padding: 20px 20px 20px 40px; display: flex; justify-content: center;">
    <div class="col-4 verify-form" style="text-align: center;">
        <h4 style="text-align: center; font-size: 30px; margin-bottom: 30px; font-weight: bold;">Safe Pickup</h4>
        <?php
            if($verified === 0){
                echo '<h5 class="text-success" style="text-align: center; font-size: 20px; font-weight: bold;">Congratulation!!!</h5>
                <p>Your Account Have Successfully Activated.<br>You Can Login With The App Now.</p>
                <button class="btn btn-primary" style="padding: 10px 25px; font-weight: bold;" onclick="window.close();">CLOSE</button>';
            }
            else if($verified === 1){
                echo '<h5 class="text-warning" style="text-align: center; font-size: 20px; font-weight: bold;">Duplicate Activation Detected.</h5>
                <p>You Already Can Login With The App.</p>
                <button class="btn btn-primary" style="padding: 10px 25px; font-weight: bold;" onclick="window.close();">CLOSE</button>';
            }
            else{
                echo '<h5 class="text-danger" style="text-align: center; font-size: 20px; font-weight: bold;">Verification Failed.</h5>
                <p>Contact Administrator To Provide New Link.</p>
                <button class="btn btn-primary" style="padding: 10px 25px; font-weight: bold;" onclick="window.close();">CLOSE</button>';
            }
        ?>
    </div>
</div>
</body>
</html>