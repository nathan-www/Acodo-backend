<?php

namespace App\Controller;

use Slim\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

class errorController extends Controller{

  public function errorHandler(Request $request, $exception){

    $response = new Response;
    $response = $response->withStatus($exception->getCode())->withHeader('Content-Type', 'application/json');

    $response->getBody()->write(json_encode([
      "status"=>"fail",
      "error_message"=>$exception->getMessage()
    ]));

    return $response;

  }

}
