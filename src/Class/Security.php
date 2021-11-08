<?php

namespace App\Class;

class Security
{

    /* Returns IP address of the user */
    public static function getUserIP()
    {
        return $_SERVER['REMOTE_ADDR'];
    }


    /* Returns approximate location (city, country) of user based on IP */
    public static function getUserLocation()
    {
        $ip = self::getUserIP();
        $req = json_decode(file_get_contents('http://ip-api.com/json/'.$ip), true);
        if (!is_null($req) && $req['status'] == "success") {
            return $req['city'] . ", " . $req['country'];
        } else {
            return "Unknown location";
        }
    }

    /* Decodes user agent string */
    public static function getUserAgent()
    {
        $parser = new \donatj\UserAgent\UserAgentParser();
        $ua = $parser->parse();
        if ($ua->platform() == "") {
            return $ua->browser();
        } else {
            return $ua->browser() . " on " . $ua->platform();
        }
    }

    /* Verify submitted recaptcha token with Google (returns bool) */
    public static function verifyRecaptchaToken($token)
    {
        $context  = stream_context_create(['http' =>
          [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                    'secret' => getenv('GOOGLE_RECAPTCHA_SECRET'),
                    'response' => $token
            ])
          ]
        ]);

        return json_decode(file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context), true)['success'];
    }
}
