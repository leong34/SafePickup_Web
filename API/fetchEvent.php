<?php
    require_once "DbConnect.php";
    include_once "Utilities/getValue.php";

    $respond["message"]         = "Unable fetch events";
    $respond["authorized"]      = false;
    $respond["event"]           = array();

    function getEventBasedOnClassId($class_id, $dynamodb, $marshaler){
        $eav = $marshaler->marshalJson('
            {
                ":class_id": "'.$class_id.'" 
            }
        ');
        $params = [
            'TableName' => "Events",
            'IndexName' => "classDate-index",
            'KeyConditionExpression' => 'class_id = :class_id',
            'ExpressionAttributeValues'=> $eav
        ];
    
        $result = query_item($params, $dynamodb);
        return $result;
    }
    
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['user_id']) && isset($_POST['credential'])){
            $credential_result = checkCredential($_POST['user_id'], $_POST['credential'], $dynamodb, $marshaler);
            $valid = $credential_result['valid'];

            if($valid){
                $result = $credential_result['result'];

                $students = getStudentsData(getValue($result[0]["student_ids"]), $marshaler, $dynamodb);
                $student_in_class = array();

                $classes = array();
                $events = array();
                foreach($students as $key => $value){
                    $class_id = getValue($value['class_id']);
                    $name = getValue($value['last_name'])." ".getValue($value['first_name']);
                    $student_id = getValue($value['student_id']);
                    $student_internal_id = getValue($value['student_internal_id']);
                    $classes[$class_id] = $class_id;

                    if(isset($student_in_class[$class_id])){
                        array_push($student_in_class[$class_id], $name);
                    }
                    else{
                        $student_in_class[$class_id] = array($name);
                    }
                }
                
                $list = array();
                foreach ($classes as $class_key => $class_value) {
                    $class_data = getClassData($class_key, $marshaler, $dynamodb);
                    $event_list = getEventBasedOnClassId($class_key, $dynamodb, $marshaler);

                    $events = array();
                    foreach ($event_list as $event_key => $event_value) {
                        $temp_event = array(
                            "date"              => getValue($event_value['date']),
                            "description"       => getValue($event_value['description']),
                            "title"             => getValue($event_value['title'])
                        );
                        array_push($events, $temp_event);
                    }
                    $temp_list = array(
                        "class_id"          => $class_key,
                        "class_name"        => getValue($class_data["name"]),
                        "student_in_class"  => $student_in_class[$class_key],
                        "details"           => $events
                    ); 
                    array_push($list, $temp_list);
                }
                
                $respond["message"]         = "Successful retrieve events";
                $respond["authorized"]      = true;
                $respond["event"]           = $list;

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