<?php

namespace App\Email;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class Email
{
    /* Send an email using SMTP
      $obj = ["to"=>"...", "subject"=>"...", "template"=>"...", "variables"=>["key"=>"value"] ]
    */
    public static function sendEmail($obj)
    {

        /* Connect to SMTP server*/
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = getenv("SMTP_HOST");
        $mail->SMTPAuth = true;
        $mail->Username = getenv("SMTP_USERNAME");
        $mail->Password = getenv("SMTP_PASSWORD");
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        /* Set sender */
        $mail->setFrom(getenv("SMTP_FROM_EMAIL"), getenv("SMTP_FROM_NAME"));

        /* Set recipient */
        $mail->addAddress($obj['to']);

        /* Set content */
        $body = file_get_contents(__DIR__ . "/" . $obj['template'] . ".html");
        foreach($obj['variables'] as $k=>$v){
          $body = str_replace("{{".$k."}}",$v,$body);
        }
        $mail->isHTML(true);
        $mail->Subject = $obj['subject'];
        $mail->Body = $body;
        $mail->send();


    }
}
