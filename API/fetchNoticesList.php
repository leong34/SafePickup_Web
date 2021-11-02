<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";

    $respond["message"] = "Unable to fetch notice list";
    $respond["authorized"] = false;
    $respond["notices"] = array();
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $respond["message"] = "Notice list is being fetched";
                $respond["authorized"] = true;
                $organization_id = getValue($result[0]["organization_id"]);
                
                $notice_ids = getValue(getOrganization($organization_id, $dynamodb, $marshaler)["notice_ids"]);

                foreach($notice_ids as $notice_key => $value){
                    $notice_data = getNoticesData($notice_key, $marshaler, $dynamodb);
                    if(getValue($notice_data["status"]) === "Enable"){
                        $viewed_by = getValue($notice_data["view_by"]);

                        $viewed = false;
                        foreach($viewed_by as $key => $value){
                            if($key == $_POST['user_id']){
                                $viewed = true;
                                break;
                            }
                        }

                        $notice_temp = array(
                            "updated_at"    => date("Y-m-d", getValue($notice_data["updated_at"])),
                            "title"         => getValue($notice_data["title"]),
                            "notice_id"     => $notice_key,
                            "description"   => getValue($notice_data["description"]),
                            "viewed"        => $viewed
                        );

                        array_push($respond["notices"], $notice_temp);
                    }
                }
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