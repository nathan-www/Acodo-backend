<?php

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Exception\HttpBadRequestException;

abstract class Controller
{

    public function __construct()
    {

    }

    public static function db()
    {
      return new \App\Database\Database();
    }

    public function jsonRequest($request){
      return json_decode($request->getBody(),true);
    }

    public function jsonResponse($data){

      $response = new Response;
      $response->getBody()->write(json_encode($data));
      return $response->withHeader('Content-type', 'application/json');
    }

}
