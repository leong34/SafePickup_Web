<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';


function mailTo($email, $name, $token, $domainUrl, $mail){
    $message = '
    
    Thanks for signing up!
    Your account has been created, you can login with the following credentials after you have activated your account by pressing the url below.<br>
    <br>
    ------------------------<br>
    Username: '.$name.'<br>
    ------------------------<br><br>
    
    Please click this link to activate your account:<br>
    '.$domainUrl.'/fyp_web/verify.php?email='.$email.'&token='.md5($token).'
    
    ';
    
    $mail->IsSMTP(); // enable SMTP

    $mail->SMTPDebug = 1; // debugging: 1 = errors and messages, 2 = messages only
    $mail->SMTPAuth = true; // authentication enabled
    $mail->SMTPSecure = 'ssl'; // secure transfer enabled REQUIRED for Gmail
    $mail->Host = "smtp.gmail.com";
    $mail->Port = 465; // or 587
    $mail->IsHTML(true);
    $mail->Username = "yongpeng0304@gmail.com";
    $mail->Password = "czbssldjzyfpynke";
    $mail->SetFrom("yongpeng0304@gmail.com");
    $mail->Subject = "Account Verification";
    $mail->Body = "$message";
    $mail->AddAddress("yongpeng0304@gmail.com"); // send to email
    ob_start();
    if(!$mail->Send()) {
        ob_end_clean();
        return "message fail to send, please contact admin to resent the invitation mail.";
    } else {
        ob_end_clean();
        return "message has been sent.";
    }
}