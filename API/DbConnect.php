<?php
    require '../aws/aws-autoloader.php';

    date_default_timezone_set('UTC');

    use Aws\DynamoDb\Exception\DynamoDbException;
    use Aws\DynamoDb\Marshaler;

    $sdk = new Aws\Sdk([
        'endpoint'   => 'http://localhost:8000',
        'region'   => 'ap-southeast-1',
        'version'  => 'latest',
        'credentials' => [
            'key'     => 'YOUR_KEY',
            'secret'  => 'YOUR_SECRET_KEY',
        ]
    ]);

    function create_table($table_name, $key_schema, $att_def, $dynamodb){
        $params = [
            'TableName' => $table_name,
            'KeySchema' => $key_schema,
            'AttributeDefinitions' => $att_def,
            'ProvisionedThroughput' => [
                'ReadCapacityUnits' => 10,
                'WriteCapacityUnits' => 10
            ]
        ];
        
        try {
            $result = $dynamodb->createTable($params);
            
        
        } catch (DynamoDbException $e) {
            echo "Unable to create table:\n";
            echo $e->getMessage() . "\n";
        }
    }

    function add_item($params, $dynamodb){
        try {
            $result = $dynamodb->putItem($params);
            return $result['Item'];

        } catch (DynamoDbException $e) {
            echo "Unable to add item:\n";
            echo $e->getMessage() . "\n";
        }
    }

    function get_item($params, $dynamodb){
        try {
            $result = $dynamodb->getItem($params);
            return $result["Item"];

        } catch (DynamoDbException $e) {
            echo "Unable to get item:\n";
            echo $e->getMessage() . "\n";
        }
    }

    function query_item($params, $dynamodb){
        try {
            $result = $dynamodb->query($params);
            return $result['Items'];
        
        } catch (DynamoDbException $e) {
            echo "Unable to query:\n";
            echo $e->getMessage() . "\n";
        }
    }

    function update_item($params, $dynamodb){
        try {
            $result = $dynamodb->updateItem($params);
            return($result['Attributes']);

        } catch (DynamoDbException $e) {
            echo "Unable to update item:\n";
            echo $e->getMessage() . "\n";
        }
    }

    function getOrganization($organization_id, $dynamodb, $marshaler){
        $key = $marshaler->marshalJson('
            {
                "organization_id": "' . $organization_id . '"
            }
        ');

        $params = [
        'TableName' => "Organizations",
        'Key' => $key
        ];

        return get_item($params, $dynamodb);
    }

    function getClassData($class_id, $marshaler, $dynamodb){
        $key = $marshaler->marshalJson('
            {
                "class_id": "' . $class_id . '"
            }
        ');
        $params = [
            'TableName' => "Classes",
            'Key' => $key
        ];
        $result = get_item($params, $dynamodb);
        return $result;
    }

    function getStudentsData($students, $marshaler, $dynamodb){
        $student_arr = array();
        foreach ($students as $key => $value) {
            $key = $marshaler->marshalJson('
                {
                    "student_id": "' . $key . '"
                }
            ');
            $params = [
                'TableName' => "Students",
                'Key' => $key
            ];
            $result = get_item($params, $dynamodb);
            $student_arr[array_values($key['student_id'])[0]] = $result;
        }
        return $student_arr;
    }

    function getStudentData($student_id, $marshaler, $dynamodb){
        $key = $marshaler->marshalJson('
            {
                "student_id": "' . $student_id . '"
            }
        ');
        $params = [
            'TableName' => "Students",
            'Key' => $key
        ];
        $result = get_item($params, $dynamodb);
        return $result;
    }

    function getGuardiansData($users, $marshaler, $dynamodb){
        $user_arr = array();
        foreach($users as $key => $value){
            $key = $marshaler->marshalJson('
                {
                    "user_id": "' . $key . '"
                }
            ');
            $params = [
                'TableName' => "Users",
                'Key' => $key
            ];
            $result = get_item($params, $dynamodb);
            if(array_values($result['user_type'])[0] !== "1" && empty(array_values($result['deleted_at'])[0])){
                $user_arr[array_values($value)[0]] = $result;
            }
        }
        return $user_arr;
    }

    function getUserData($user_id, $marshaler, $dynamodb){
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
        return $result;
    }

    function getStudentAttendance($student_id, $marshaler, $dynamodb){
        $eav = $marshaler->marshalJson('
            {
                ":student_id": "'.$student_id.'" 
            }
        ');
        $params = [
            'TableName' => "Attendances",
            'KeyConditionExpression' => 'student_id = :student_id',
            'ExpressionAttributeValues'=> $eav
        ];

        $result = query_item($params, $dynamodb);
        return $result;
    }

    function getStudentTodayRequest($student_id, $marshaler, $dynamodb){
        $eav = $marshaler->marshalJson('
            {
                ":student_id": "'.$student_id.'" ,
                ":date"      : "'.date("Y-m-d").'"
            }
        ');
        $params = [
            'TableName' => "Requests",
            'KeyConditionExpression' => 'student_id = :student_id and #d= :date',
            'ExpressionAttributeValues'=> $eav,
            'ExpressionAttributeNames'=> ['#d' => 'date']
        ];

        $result = query_item($params, $dynamodb);
        return $result;
    }

    function getStudentPickUpRequest($student_id, $marshaler, $dynamodb, $date){
        $eav = $marshaler->marshalJson('
            {
                ":student_id": "'.$student_id.'" ,
                ":date"      : "'.$date.'"
            }
        ');
        $params = [
            'TableName' => "Requests",
            'KeyConditionExpression' => 'student_id = :student_id and #d = :date',
            'ExpressionAttributeValues'=> $eav,
            'ExpressionAttributeNames'=> ['#d' => 'date']
        ];

        $result = query_item($params, $dynamodb);
        return $result;
    }



    

    function getUsersData($users, $marshaler, $dynamodb){
        $user_arr = array();
        foreach($users as $key => $value){
            $key = $marshaler->marshalJson('
                {
                    "user_id": "' . $key . '"
                }
            ');
            $params = [
                'TableName' => "Users",
                'Key' => $key
            ];
            $result = get_item($params, $dynamodb);
            $user_arr[array_values($value)[0]] = $result;
        }
        return $user_arr;
    }

    function getAttendancesData($students, $marshaler, $dynamodb){
        $attendance_arr = array();
        foreach(array_values($students)[0] as $key => $value){        
            $eav = $marshaler->marshalJson('
                {
                    ":suid": "'.array_values($value)[0].'" 
                }
            ');
            $params = [
                'TableName' => "Attendances",
                'KeyConditionExpression' => 'student_id = :suid',
                'ExpressionAttributeValues'=> $eav
            ];
            $result = query_item($params, $dynamodb);

            if(!empty($result)){
                foreach($result as $att_key => $att_value){
                    $attendance_arr[array_values($value)[0]][array_values($att_value['date'])[0]] = $att_value;
                }
            }
        }
        return $attendance_arr;
    }

    function getStudentTodayAttendanceData($student_id, $marshaler, $dynamodb){
        $item = array(
            ":suid"         => $student_id,
            ":today"        => date("Y-m-d")
        );
        $eav = $marshaler->marshalJson(json_encode($item));
        $params = [
            'TableName' => "Attendances",
            'KeyConditionExpression' => 'student_id = :suid and #d = :today',
            'ExpressionAttributeValues'=> $eav,
            'ExpressionAttributeNames'=> ['#d' => 'date']
        ];
        $result = query_item($params, $dynamodb);
        return $result;
    }

    function getYearAttendancesData($marshaler, $dynamodb, $type, $start_date, $end_date){
        $item = array(
            ":type" => $type,
            ":start_date" => $start_date,
            ":end_date" => $end_date,

        );
        $eav = $marshaler->marshalJson(json_encode($item));
        $params = [
            'TableName' => "Attendances",
            'IndexName' => "typeDate-index",
            'KeyConditionExpression' => '#t = :type and #d between :start_date and :end_date ',
            'ExpressionAttributeValues'=> $eav,
            'ExpressionAttributeNames'=> ['#t' => 'type', '#d' => 'date']
        ];
        $result = query_item($params, $dynamodb);
        return $result;
    }

    function getRequestsData($students, $marshaler, $dynamodb){
        $request_arr = array();
        foreach($students[0] as $key => $value){
            $eav = $marshaler->marshalJson('
                {
                    ":suid": "'.array_values($value)[0].'" 
                }
            ');
            $params = [
                'TableName' => "Requests",
                'KeyConditionExpression' => 'student_id = :suid',
                'ExpressionAttributeValues'=> $eav
            ];
            $result = query_item($params, $dynamodb);
            if(!empty($result))
            $request_arr[array_values($value)[0]] = $result;
        }
        return $request_arr;
    }

    function getYearRequestsData($marshaler, $dynamodb, $type, $start_date, $end_date){
        $item = array(
            ":type" => $type,
            ":start_date" => $start_date,
            ":end_date" => $end_date,

        );
        $eav = $marshaler->marshalJson(json_encode($item));
        $params = [
            'TableName' => "Requests",
            'IndexName' => "typeDate-index",
            'KeyConditionExpression' => '#t = :type and #d between :start_date and :end_date ',
            'ExpressionAttributeValues'=> $eav,
            'ExpressionAttributeNames'=> ['#t' => 'type', '#d' => 'date']
        ];
        $result = query_item($params, $dynamodb);
        return $result;
    }

    function getTodayRequestData($student_id, $marshaler, $dynamodb){
        $eav = $marshaler->marshalJson('
            {
                ":suid": "'.$student_id.'" ,
                ":tdy": "'.date("Y-m-d").'"
            }
        ');
        $params = [
            'TableName' => "Requests",
            'KeyConditionExpression' => 'student_id = :suid and #d = :tdy',
            'ExpressionAttributeValues'=> $eav,
            'ExpressionAttributeNames'=> ["#d" => "date"]
        ];
        $result = query_item($params, $dynamodb);
        return $result;
    }

    function getNoticesData($notice_id, $marshaler, $dynamodb){
        $key = $marshaler->marshalJson('
            {
                "notice_id": "' . $notice_id . '"
            }
        ');
        $params = [
            'TableName' => "Notices",
            'Key' => $key
        ];
        $result = get_item($params, $dynamodb);
        return $result;
    }

    function getEventsData($events, $marshaler, $dynamodb){
        $event_arr = array();
        foreach (array_pop($events) as $event_key => $value) {
            $key = $marshaler->marshalJson('
                {
                    "event_id": "' . $event_key . '"
                }
            ');
            $params = [
                'TableName' => "Events",
                'Key' => $key
            ];
            $result = get_item($params, $dynamodb);
            $event_arr[$event_key] = $result;
        }
        return $event_arr;
    }

    function delete_item($params, $dynamodb){
        try {
            $result = $dynamodb->deleteItem($params);

        } catch (DynamoDbException $e) {
            echo "Unable to delete item:\n";
            echo $e->getMessage() . "\n";
        }
    }

    function delete_table($table_name, $dynamodb){
        $params = [
            'TableName' => $table_name
        ];
        
        try {
            $result = $dynamodb->deleteTable($params);
        
        } catch (DynamoDbException $e) {
            echo "Unable to delete table:\n";
            echo $e->getMessage() . "\n";
        }
    }


    $dynamodb = $sdk->createDynamoDb();
    $marshaler = new Marshaler();
    date_default_timezone_set("Asia/Kuala_Lumpur");