<?php

  namespace App\Class;

  class User
  {
      public $user;
      public $userExists;

      protected static function db()
      {
          return new \App\Database\Database();
      }

      public function __construct($identifier)
      {
          $user = $this->db()->query("SELECT * FROM accounts WHERE email=? OR username=? OR user_id=?", [$identifier,$identifier,$identifier]);
          if (count($user) < 1) {
              $this->userExists = false;
          } else {
              $this->user = $user[0];
              $this->userExists = true;
          }
      }

      public function basicInfo()
      {
          return [
            "user_id" => $this->user['user_id'],
            "username" => $this->user['username']
          ];
      }



      public function sendVerificationEmail()
      {
          if ($this->user['email_verified'] !== "false") {
              return [
                "status"=>"fail",
                "error_message"=>"Email already verified"
              ];
          } elseif ($this->user['last_verification_email_sent'] > (time() - 60)) {
              //Limit to one email per minute
              return [
                "status"=>"fail",
                "error_message"=>"Please wait a minute before requesting another email"
              ];
          } else {

              //Pseudo random string
              $token = substr(bin2hex(openssl_random_pseudo_bytes(255)), 0, 35);

              //Send verification email
              \App\Email\Email::sendEmail([
                "to"=>$this->user['email'],
                "subject"=>"Acodo: Verify your email",
                "template"=>"EmailVerification",
                "variables"=>[
                  "username"=>$this->user['username'],
                  "ip"=>\App\Class\Security::getUserIP(),
                  "ip_location"=>\App\Class\Security::getUserLocation(),
                  "device"=>\App\Class\Security::getUserAgent(),
                  "token"=>$token
                ]
              ]);

              //Update database with token
              $this->db()->update('accounts', [ "user_id"=>$this->user['user_id'] ], [ "verification_token"=>$token, "last_verification_email_sent"=>time() ]);

              return [
                "status"=>"success"
              ];
          }
      }

      public function sendNotification($notificationData)
      {
          $this->db()->insert('notifications',[
            "notification_id"=>rand(1000000000, 9999999999),
            "user_id"=>$this->user['user_id'],
            "timestamp"=>time(),
            "notification_data"=>json_encode($notificationData)
          ]);
      }

  }
