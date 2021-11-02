<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";
    include_once "faceRekog.php";

    $respond["message"] = "Unable To Insert Image";
    $respond["image"] = "";
    $respond["authorized"] = false;
    $respond["rekog_message"] = "";
    $respond["face_id"] = "";
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential']) && isset($_FILES['image'])){    
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];
                $respond["authorized"] = true;

                $target_file = basename($_FILES["image"]["name"]);
                $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

                $target_file = "Picture/".$_POST['user_id'].".".$imageFileType;
                $resize_file = "Picture/".$_POST['user_id']."_resize.".$imageFileType;

                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    list($width, $height) = getimagesize($target_file);
                        
                    // Reduce width and height to half
                    $new_width = $width * 0.1;
                    $new_height = $height * 0.1;
                        
                    // Resample the image
                    $image_p = imagecreatetruecolor($new_width, $new_height);
                    $image = imagecreatefromjpeg($target_file);

                    imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

                    imagejpeg($image_p, $resize_file, 100);
                    
                    $respond["message"] = "Image Inserted To Server.";
                    $respond["image"]   = $resize_file;
                    
                    $recog_respond = indexImageFace($resize_file, $client);
                    $respond["rekog_message"] = $recog_respond['message'];

                    if($recog_respond['inserted']){
                        $respond["face_id"] = $recog_respond['face_id'];
                        $key = $marshaler->marshalJson('
                                {
                                    "user_id": "'.$_POST['user_id'].'"
                                }
                            ');
            
                        $eav = $marshaler->marshalJson('
                                {
                                    ":face_id"     : "'.$recog_respond['face_id'].'"
                                }
                            ');
            
                        $updateExpression = 'set face_id = :face_id';
                        
                        $params = [
                            'TableName' => 'Users',
                            'Key' => $key,
                            'UpdateExpression' => $updateExpression,
                            'ExpressionAttributeValues'=> $eav,
                            'ReturnValues' => 'UPDATED_NEW'
                        ];
                        update_item($params, $dynamodb);
                    }
                    unlink($target_file);
                    unlink($resize_file);
                }
                else{
                    $respond["message"] = "Failed to insert to server";
                    $respond["image"]   = $target_file;
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