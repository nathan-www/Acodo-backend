<?php

namespace App\Class;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use Slim\Exception\HttpUnauthorizedException;

class Session
{
    public function __construct()
    {
    }

    protected static function db()
    {
        return new \App\Database\Database();
    }

    //Create a new session for a given user_id
    public static function newSession($user_id)
    {
        $session_id = substr(bin2hex(openssl_random_pseudo_bytes(255)), 0, 10);
        $session_token = substr(bin2hex(openssl_random_pseudo_bytes(255)), 0, 35);

        self::db()->insert('sessions', [
        "session_id"=>$session_id,
        "user_id"=>$user_id,
        "created"=>time(),
        "last_activity"=>time(),
        "ip"=>\App\Class\Security::getUserIP(),
        "ip_location"=>\App\Class\Security::getUserLocation(),
        "device"=>\App\Class\Security::getUserAgent(),
        "token"=>$session_token
      ]);

        //Set session cookies
        setcookie("acodo_session_id", $session_id, time()+31000000, "/");
        setcookie("acodo_session_token", $session_token, time()+31000000, "/");
    }

    public static function getSession()
    {
        if (!isset($_COOKIE['acodo_session_id']) || !isset($_COOKIE['acodo_session_token'])) {
            return [
              "authenticated"=>false,
              "error"=>"Missing session credentials"
            ];
        }

        $session = self::db()->select('sessions', ['session_id'=>$_COOKIE['acodo_session_id'],'token'=>$_COOKIE['acodo_session_token']]);
        if (count($session) < 1) {
            return [
              "authenticated"=>false,
              "error"=>"Invalid session credentials"
            ];
        }

        //Update last session activity in database
        self::db()->update('sessions', ['session_id'=>$_COOKIE['acodo_session_id']], ["last_activity"=>time()]);

        //Update last account activity in database
        self::db()->update('accounts', ['user_id'=>$session[0]['user_id']], ["last_active_timestamp"=>time()]);


        return [
          "authenticated"=>true,
          "session"=>$session[0]
        ];
    }

    //Session validation middleware for all authenticated endpoints
    public static function sessionMiddleware(Request $request, RequestHandler $handler)
    {

        $sess = self::getSession();

        if(!$sess['authenticated']){
          throw new HttpUnauthorizedException($request, $sess['error']);
        }
        else{
          return $handler->handle($request->withAttribute('user_id', $sess['session']['user_id'])->withAttribute('session_id', $_COOKIE['acodo_session_id']));
        }

    }
}
