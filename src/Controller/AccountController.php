<?php

namespace App\Controller;

use Slim\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Exception\HttpSpecializedException;

class AccountController extends Controller
{

    //  Route /account/register
    public function register(Request $request)
    {
        $db = new \App\Database\Database();
        $json = $this->jsonRequest($request);

        //Check email does not already belong to an account
        $user = new \App\Class\User($json['email']);
        if ($user->userExists) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Email already belongs to an account"
            ]);
        }

        //Check username is not already taken
        $user = new \App\Class\User($json['username']);
        if ($user->userExists) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Username is already taken"
            ]);
        }

        //Verify recaptcha recaptcha token
        //TODO: Uncomment in production!!
        /*
        if (!\App\Class\Security::verifyRecaptchaToken($json['recaptcha_token'])) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Invalid ReCaptcha token"
            ]);
        }
        */

        //All good - create user account
        $userid = rand(1000000000, 9999999999);

        $db->insert('accounts', [
          "user_id"=>$userid,
          "username"=>$json['username'],
          "email"=>$json['email'],
          "password_hash"=>password_hash($json['password'], PASSWORD_DEFAULT),
          "registration_ip"=>\App\Class\Security::getUserIP(),
          "email_verified"=>"false",
          "last_verification_email_sent"=>0,
          "last_reset_email_sent"=>0,
          "account_active"=>"false",
          "registration_timestamp"=>time(),
          "xp"=>0,
          "show_email"=>"false",
          "streak_last_timestamp"=>0,
          "streak_days"=>0,
          "last_active_timestamp"=>time()
        ]);

        $user = new \App\Class\User($json['username']);
        $user->sendVerificationEmail();


        return $this->jsonResponse([
          "status"=>"success"
        ]);
    }


    public function resendVerificationEmail(Request $request)
    {
        $json = $this->jsonRequest($request);
        $user = new \App\Class\User($json['identifier']);

        if (!$user->userExists) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Could not request email verification"
            ]);
        }

        return $this->jsonResponse($user->sendVerificationEmail());
    }

    public function verifyEmail(Request $request)
    {
        $db = new \App\Database\Database();
        $json = $this->jsonRequest($request);
        $user = new \App\Class\User($json['email']);

        if (!$user->userExists || $user->user['email_verified'] !== "false" || $user->user['verification_token'] !== $json['verification_token']) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Email verification failed"
            ]);
        } elseif ($user->user['last_verification_email_sent'] < (time() - 900)) {

            //Verification token expires after 15 mins
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Verification link expired"
            ]);
        } else {

            //Mark user account as email verified
            $db->update('accounts', [ "user_id"=>$user->user['user_id'] ], [ "verification_token"=>"", "email_verified"=>"true", "account_active"=>"true" ]);

            return $this->jsonResponse([
              "status"=>"success"
            ]);
        }
    }

    public function usernameAvailable(Request $request, $response, $args)
    {
        if (!preg_match("/^(?=.*?[a-z])[a-z0-9]{1,20}$/", $args['username'])) {
            return $this->jsonResponse([
              "available"=>false,
              "error_message"=>"Username must contain 1-20 alphanumeric characters, including at least 1 letter"
            ]);
        }

        $user = new \App\Class\User($args['username']);

        if ($user->userExists) {
            return $this->jsonResponse([
              "available"=>false,
              "error_message"=>"Username unavailable"
            ]);
        }

        return $this->jsonResponse([
        "available"=>true
      ]);
    }

    public function login(Request $request)
    {
        $json = $this->jsonRequest($request);
        $db = new \App\Database\Database();

        //Verify recaptcha recaptcha token
        //TODO: Uncomment in production!!
        /*
        if (!\App\Class\Security::verifyRecaptchaToken($json['recaptcha_token'])) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Invalid ReCaptcha token"
            ]);
        }
        */

        $user = new \App\Class\User($json['identifier']);

        if (!$user->userExists) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"We couldn't find an account with that email/username",
              "prompt_create_account"=>true
            ]);
        }

        //Remove failed login requests older than 10 mins
        $db->query('DELETE FROM login_requests WHERE timestamp < ' . (time() - 600));

        //Check number of failed logins in the last 10 minutes against this account, or from this IP address
        $failedLogins = $db->query('SELECT * FROM login_requests WHERE user_id=? OR ip=?', [$user->user['user_id'],\App\Class\Security::getUserIP()]);

        //More than 30 failed logins in last 10 minutes, block further requests
        if (count($failedLogins) > 30) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Account temporarily locked for security reasons. Please try again in 10 minutes."
            ]);
        }

        if (!password_verify($json['password'], $user->user['password_hash'])) {

            //Record failed login request in database
            $db->insert('login_requests', [
              "user_id"=>$user->user['user_id'],
              "timestamp"=>time(),
              "ip"=>\App\Class\Security::getUserIP()
            ]);

            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Incorrect username, email or password"
            ]);
        }

        if ($user->user['email_verified'] == "false") {
            return $this->jsonResponse([
              "status"=>"fail",
              "email_verify"=>true,
              "error_message"=>"You must verify your email before logging-in"
            ]);
        }

        \App\Class\Session::newSession($user->user['user_id']);

        return $this->jsonResponse([
              "status"=>"success",
            ]);
    }


    public function accountDetails(Request $request)
    {
        $db = new \App\Database\Database();
        $user = new \App\Class\User($request->getAttribute('user_id'));

        $sessions = [];
        $s = $db->select("sessions", ["user_id"=>$request->getAttribute('user_id')]);
        foreach ($s as $session) {
            $sessions[] = [
            "session_id"=>$session['session_id'],
            "current"=>$request->getAttribute('session_id')==$session['session_id'],
            "ip"=>$session['ip'],
            "geo"=>$session['ip_location'],
            "device"=>$session['device'],
            "last_active"=>$session['last_activity']
          ];
        }

        return $this->jsonResponse([
        "status"=>"success",
        "username"=>$user->user['username'],
        "user_id"=>$user->user['user_id'],
        "email"=>$user->user['email'],
        "xp"=>$user->user['xp'],
        "joined"=>$user->user['registration_timestamp'],
        "sessions"=>$sessions
      ]);
    }

    public function logout(Request $request)
    {
        $json = $this->jsonRequest($request);
        $db = new \App\Database\Database();

        if (isset($json['session_id'])) {
            if ($json['session_id'] == "all") {
                //Delete all sessions
                $db->delete("sessions", ["user_id"=>$request->getAttribute('user_id')]);

                //Remove session cookies
                setcookie("acodo_session_id", "", 1);
                setcookie("acodo_session_token", "", 1);
            } else {
                //Delete specified session
                $db->delete("sessions", ["user_id"=>$request->getAttribute('user_id'),"session_id"=>$json['session_id']]);
            }
        } else {
            //Delete current session
            $db->delete("sessions", ["user_id"=>$request->getAttribute('user_id'),"session_id"=>$request->getAttribute('session_id')]);

            //Remove session cookies
            setcookie("acodo_session_id", "", 1);
            setcookie("acodo_session_token", "", 1);
        }

        return $this->jsonResponse([
          "status"=>"success",
        ]);
    }

    public function changePassword(Request $request)
    {
        $json = $this->jsonRequest($request);
        $db = new \App\Database\Database();
        $user = new \App\Class\User($request->getAttribute('user_id'));

        if (!password_verify($json['old_password'], $user->user['password_hash'])) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Old password incorrect"
            ]);
        } else {

            //Log out of all account for security
            $db->delete('sessions',['user_id'=>$request->getAttribute('user_id')]);

            $db->update('accounts', ["user_id"=>$request->getAttribute('user_id')], ["password_hash"=>password_hash($json['new_password'], PASSWORD_DEFAULT)]);
            return $this->jsonResponse([
              "status"=>"success",
            ]);
        }
    }


    public function requestPasswordReset(Request $request)
    {
        $json = $this->jsonRequest($request);

        //Verify recaptcha recaptcha token
        //TODO: Uncomment in production!!
        /*
        if (!\App\Class\Security::verifyRecaptchaToken($json['recaptcha_token'])) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Invalid ReCaptcha token"
            ]);
        }
        */

        $user = new \App\Class\User($json['email']);
        $db = new \App\Database\Database();

        if (!$user->userExists) {
            //Return success - does not reveal which emails exist or don't in the database (best practice)
            return $this->jsonResponse([
            "status"=>"success"
          ]);
        }

        if ($user->user['last_reset_email_sent'] > (time() - 60)) {
            //Limit to 1 email per minute
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Please wait 1 minute before requesting another email"
            ]);
        }

        //Pseudo random string
        $token = substr(bin2hex(openssl_random_pseudo_bytes(255)), 0, 35);

        //Update database with reset token
        $db->update("accounts", ["user_id"=>$user->user['user_id']], ["last_reset_email_sent"=>time(),"reset_token"=>$token]);

        //Send reset email
        \App\Email\Email::sendEmail([
          "to"=>$user->user['email'],
          "subject"=>"Acodo: Reset your password",
          "template"=>"PasswordReset",
          "variables"=>[
            "username"=>$user->user['username'],
            "email"=>$user->user['email'],
            "ip"=>\App\Class\Security::getUserIP(),
            "ip_location"=>\App\Class\Security::getUserLocation(),
            "device"=>\App\Class\Security::getUserAgent(),
            "token"=>$token
          ]
        ]);

        return $this->jsonResponse([
          "status"=>"success"
        ]);
    }

    public function submitPasswordReset(Request $request)
    {
        $json = $this->jsonRequest($request);
        $db = new \App\Database\Database();

        $reset = $db->select("accounts", ["email"=>$json['email'],"reset_token"=>$json['reset_token']]);

        if (count($reset) < 1) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Invalid reset link"
            ]);
        }
        if ($reset[0]['last_reset_email_sent'] < (time() - 900)) {
            //Reset token expires after 15 mins
            return $this->jsonResponse([
            "status"=>"fail",
            "error_message"=>"Reset link expired"
          ]);
        }

        //All okay, reset password
        $db->update('accounts', ['user_id'=>$reset[0]['user_id']], ['reset_token'=>'','password_hash'=>password_hash($json['new_password'], PASSWORD_DEFAULT)]);
        return $this->jsonResponse([
          "status"=>"success"
        ]);
    }

    public function changeUsername(Request $request)
    {
        $json = $this->jsonRequest($request);
        $db = new \App\Database\Database();

        $user = new \App\Class\User($json['username']);

        if ($user->userExists && $user->user['user_id'] !== $request->getAttribute('user_id')) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Username unavailable"
            ]);
        } else {
            $user = new \App\Class\User($request->getAttribute('user_id'));
            $db->update("accounts", ["user_id"=>$user->user['user_id']], ["username"=>$json["username"]]);
            return $this->jsonResponse([
              "status"=>"success"
            ]);
        }
    }
}
