<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use Slim\Exception\HttpBadRequestException;

class ParameterCheckerMiddleware
{
    public static function run(Request $request, RequestHandler $handler)
    {
        $json = json_decode($request->getBody(), true);

        //First check JSON request is valid
        if (!is_array($json)) {
            throw new HttpBadRequestException($request, "Bad Request");
        }

        //Next check all required parameters are provided
        $params = $request->getAttribute('params');

        foreach ($params as $k=>$v) {
            $optional = false;

            if (is_int($k)) {
                $key = $v;
                $checker = fn ($str) => true;
            } else {
                $key = $k;
                if (is_array($v)) {
                    if (isset($v['optional'])) {
                        $optional = $v['optional'];
                    }
                    $v = $v['checker'];
                }

                if (is_callable($v)) {
                    $checker = $v;
                } else {
                    $checker = fn ($str) =>  preg_match($v, $str);
                }
            }

            if (!isset($json[$key]) && !$optional) {
                throw new HttpBadRequestException($request, "Missing parameter '".$key."'");
            } elseif (isset($json[$key]) && !$checker($json[$key])) {
                throw new HttpBadRequestException($request, "Invalid parameter '".$key."'");
            }
        }

        return $handler->handle($request);
    }
}
