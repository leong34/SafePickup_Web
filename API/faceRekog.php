<?php
    require_once '../aws/aws-autoloader.php';

    $client = new Aws\Rekognition\RekognitionClient([
        'region'   => 'ap-southeast-1',
        'version'  => 'latest',
        'credentials' => [
            'key'     => 'YOUR_KEY',
            'secret'  => 'YOUR_SECRET_KEY',
        ]
    ]);

    /**
     * return true if have sunglasses
     */
    function detectImageSunglasses($image, $client){
        $result = $client->detectLabels([
            'Image'         => [
                'Bytes' => file_get_contents($image),
            ],
            "MaxLabels"     => 10,
            "MinConfidence" => 90
        ]);

        $result = $result->toArray();
        $labels = $result['Labels'];

        foreach($labels as $key => $value){
            if($value['Name'] === "Sunglasses"){
                return true;
            }
        }
        return false;
    }
    
    /**
     * return true when have only 1 face detected
     * return false when have 0 or more than 1 face detected
     */
    function detectImageFace($image, $client){
        $result = $client->detectFaces([
            'Image'         => [
                'Bytes' => file_get_contents($image),
            ]
        ]);

        $result = $result->toArray();
        return count($result['FaceDetails']) == 1;
    }

    /**
     * return true if duplicate face detected
     * return false if no duplicate face found
     */
    function duplicateFace($image, $client){
        $result = $client->searchFacesByImage([
            'CollectionId'      => "user-photos",
            "FaceMatchThreshold"=> 99,
            'Image'             => [
                'Bytes' => file_get_contents($image),
            ],
            'MaxFaces'          => 1
        ]);

        $result = $result->toArray();

        foreach($result['FaceMatches'] as $key => $value){
            if($value['Similarity'] >= 99){
                return true;
            }
        }

        return false;
    }

    function faceCover($image, $client){
        $result = $client->detectProtectiveEquipment([
            'Image'         => [
                'Bytes' => file_get_contents($image),
            ]
        ]);

        $result = $result->toArray();

        foreach($result["Persons"][0]["BodyParts"] as $key => $value){
            if($value["Name"] == "FACE"){
                foreach($value["EquipmentDetections"] as $key => $value){
                    if($value["Type"] == "FACE_COVER" && $value['CoversBodyPart']['Value'])
                        return true;
                }
            }    
        }

        return false;
    }

    function indexImageFace($image, $client){
        $respond = array();
        $respond['message'] =  "";
        $respond['face_id'] =  "";
        $respond['inserted'] = false;
        if(!detectImageFace($image, $client)){
            $respond['message'] =  "No or more than 1 face detected";
            return $respond;
        }

        if(detectImageSunglasses($image, $client)){
            $respond['message'] =  "Sunglasses detected";
            return $respond;
        }

        if(faceCover($image, $client)){
            $respond['message'] =  "Face is being covered";
            return $respond;
        }

        if(duplicateFace($image, $client)){
            $respond['message'] =  "Duplicate face detected";
            return $respond;
        }

        $result = $client->indexFaces([
            'CollectionId'      => "user-photos",
            'Image'             => [
                'Bytes' => file_get_contents($image),
            ],
            'MaxFaces'          => 1,
            'QualityFilter'     => "MEDIUM"
        ]);
        
        if(empty($result)){
            $respond['message'] =  "Image is with a bad quality";
            return $respond;
        }

        $respond['message'] =  "Image inserted into rekognition";
        $respond['face_id'] =  $result["FaceRecords"][0]["Face"]["FaceId"];
        $respond['inserted'] = true;
        return $respond;
    }

    function searchFaceByImage($image, $client, $face_id){
        $respond = array();
        $respond['message'] =  "";
        $respond['validated'] = false;
        if(!detectImageFace($image, $client)){
            $respond['message'] =  "No or more than 1 face detected";
            return $respond;
        }

        if(detectImageSunglasses($image, $client)){
            $respond['message'] =  "Sunglasses detected";
            return $respond;
        }

        if(faceCover($image, $client)){
            $respond['message'] =  "Face is being covered";
            return $respond;
        }

        $result = $client->searchFacesByImage([
            'CollectionId'      => "user-photos",
            "FaceMatchThreshold"=> 99,
            'Image'             => [
                'Bytes' => file_get_contents($image),
            ],
            'MaxFaces'          => 1,
            'QualityFilter'     => "MEDIUM"
        ]);

        $result = $result->toArray();

        if(empty($result)){
            $respond['message'] =  "Image is with bad quality";
            return $respond;
        }

        if(empty($result['FaceMatches'])){
            // no face detected
            $respond['message'] =  "No same face detected";
            return $respond;
        }

        if($result['FaceMatches'][0]["Face"]["FaceId"] !== $face_id){
            // not same face id
            $respond['message'] =  "Not same face id detected";
            return $respond;
        }
        else{
            // same face id
            $respond['message'] =  "validated";
            $respond['validated'] =  true;
            return $respond;
        }
    }
    