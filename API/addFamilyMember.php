<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";
    include_once "Utilities/mailing.php";    

    $respond["message"]         = "Unable to add new family member";
    $respond["authorized"]      = false;

    function addNewUser($user_token, $creator_id, $user_id, $num, $last_name, $first_name, $tel, $email, $password, $students_assigned, $organization_id, $dynamodb, $marshaler){
        $item = $marshaler->marshalJson('
            {
                "user_id"           : "'.$user_id.'",
                "user_internal_id"  : "'.getInternalId("G", $num).'",
                "created_at"        : "' .time(). '",
                "updated_at"        : "' .time(). '",
                "deleted_at"        : "",
                "email"             : "'.$email.'",
                "verified_at"       : "",
                "password"          : "'.md5($user_token.$password).'",
                "token"             : "'.$user_token.'",
                "message_token"     : "",
                "user_type"         : 2,
                "credential"        : "",
                "family_ids"        : {},
                "organization_id"   : "'.$organization_id.'",
                "student_ids"       : '.json_encode($students_assigned).',
                "face_id"           : "",
                "created_by"        : "'.$creator_id.'",
                "info"              : {
                    "first_name": "'.$first_name.'",
                    "last_name" : "'.$last_name.'",
                    "tel_num"   : "'.$tel.'"
                }
            }
        ');
        $params = [
            'TableName' => "Users",
            'Item' => $item
        ];
        add_item($params, $dynamodb);
    }

    function updateOrganization($user_id, $organization_id, $dynamodb, $marshaler){
        $key = $marshaler->marshalJson('
                {
                    "organization_id"      : "'.$organization_id.'"
                }
            ');

        $eav = $marshaler->marshalJson('
                {
                    ":uids"     : "'.$user_id.'"
                }
            ');

        $updateExpression = 'set user_ids.#uid = :uids';

        $params = [
            'TableName' => 'Organizations',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeValues'=> $eav,
            'ExpressionAttributeNames' => ['#uid' => $user_id],
            'ReturnValues' => 'ALL_NEW'
        ];
        $result = update_item($params, $dynamodb);
    }

    function updateStudentGuardians($user_id, $student_id, $dynamodb, $marshaler){
        $key = $marshaler->marshalJson('
            {
                "student_id"      : "'.$student_id.'"
            }
        ');

        $eav = $marshaler->marshalJson('
            {
                ":guardian_ids"     : "'.$user_id.'"
            }
        ');

        $updateExpression = 'set guardian_ids.#guid = :guardian_ids';
        $params = [
            'TableName' => 'Students',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeValues'=> $eav,
            'ExpressionAttributeNames'=> ['#guid' => $user_id],
            'ReturnValues' => 'ALL_NEW'
        ];
        $result = update_item($params, $dynamodb);
    }

    function updateCreator($creator_id, $user_id, $dynamodb, $marshaler){
        $key = $marshaler->marshalJson('
                {
                    "user_id"      : "'.$creator_id.'"
                }
            ');

        $eav = $marshaler->marshalJson('
                {
                    ":fids"     : "'.$user_id.'"
                }
            ');

        $updateExpression = 'set family_ids.#fid = :fids';

        $params = [
            'TableName' => 'Users',
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeValues'=> $eav,
            'ExpressionAttributeNames' => ['#fid' => $user_id],
            'ReturnValues' => 'ALL_NEW'
        ];
        $result = update_item($params, $dynamodb);
    }
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $organization_id = getValue($result[0]["organization_id"]);
                $organization = getOrganization($organization_id, $dynamodb, $marshaler);
                
                $user_ids = getValue($organization['user_ids']);

                $students_assigned = array();

                $new_user_id = "U".time()."".generateToken()."";
                $user_token = generateToken();

                foreach ($_POST['student_ids'] as $key => $value) {
                    $students_assigned[$value] = $value;
                    updateStudentGuardians($new_user_id, $value, $dynamodb, $marshaler);
                }

                updateOrganization($new_user_id, $organization_id, $dynamodb, $marshaler);
                addNewUser($user_token, getValue($result[0]['user_id']), $new_user_id, count($user_ids), $_POST['last_name'], $_POST['first_name'], $_POST['tel'], $_POST['email'], $_POST['new_password'], $students_assigned, $organization_id, $dynamodb, $marshaler);
                updateCreator(getValue($result[0]['user_id']), $new_user_id, $dynamodb, $marshaler);

                $full_name = $_POST['last_name']." ".$_POST['first_name'];
                
                $mail = new PHPMailer\PHPMailer\PHPMailer();
                $mail_respond = mailTo($_POST['email'], $full_name, $user_token, "http://" . getValue($organization['url_prefix']), $mail);

                $respond["message"] = "Successful added new family member and ". $mail_respond;
                $respond["authorized"] = true;

            }
            else{
                $respond["message"] = "Invalid Credential.";
            }
        }
        else{
            $respond["message"] = "Requried Data is Missing.";
        }
    }
    else{
        $respond["message"] = "Type missmatch.";
    }

    print_r(json_encode($respond));