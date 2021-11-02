<?php
    include "include/mailing.php";
    include "include/session.php";
    include "include/dynamoDB_functions.php";

    check_session($dynamodb, $marshaler);

    $guardian_data = getGuardianData($_GET['id'], $marshaler, $dynamodb);

    $email = array_values($guardian_data['email'])[0];
    $full_name = array_values(array_values($guardian_data['info'])[0]['last_name'])[0]." ".array_values(array_values($guardian_data['info'])[0]['first_name'])[0];
    $token = array_values($guardian_data['token'])[0];

    $mail = new PHPMailer\PHPMailer\PHPMailer();
    mailTo($email, $full_name, $token, "http://" . $_SERVER['SERVER_NAME'] , $mail);
    $_SESSION['mail_send'] = true;
    header('Location: userGuardian.php');