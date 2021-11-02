<?php
    function sendGCM($message, $to) {
        $url = 'https://fcm.googleapis.com/fcm/send';
    
        $fields = array (
                'to' => $to,
                'notification' => $message
        );
        $fields = json_encode ( $fields );
    
        $headers = array (
                'Authorization: key=' . "AAAAk7e_OpU:APA91bFD1_NGaP8PHNdKjr-55uiLZwMtienI3baayfYWO2HC62tQgaoHwu-T0O0vHVZOz7XyZ85fKvpb8xo4372aTH8jUU-YObp_tUF3S1ccXHXlywOaZLUIaFeSczIdLsibkClSg6D6",
                'Content-Type: application/json'
        );
    
        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_URL, $url );
        curl_setopt ( $ch, CURLOPT_POST, true );
        curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );
    
        $result = curl_exec ( $ch );
        curl_close ( $ch );
        return json_decode($result, true);
    }

    function broadCastNotification($messaging_tokens, $data){
        foreach($messaging_tokens as $key => $value){
            sendGCM($data, $key);
        }
    }
?>