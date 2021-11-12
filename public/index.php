<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteCollectorProxy;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../env.php';


$app = AppFactory::create();



//Account routes
$app->group('/account', function (RouteCollectorProxy $group) {
    $group->post('/register', '\App\Controller\AccountController:register')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "username" => "/^(?=.*?[a-z])[a-z0-9]{1,20}$/", //username - alphanumeric 1-20 characters, at least 1 letter
      "email" => fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($e) <= 50, //Email
      "password" => "/^(?=.*?[a-z])(?=.*?[0-9])(.){8,50}$/", //Password: 8-50 characters, at least 1 letter and 1 number
      "recaptcha_token"
    ]), $handler));

    $group->post('/resendVerificationEmail', '\App\Controller\AccountController:resendVerificationEmail')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
        "email" => fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($e) <= 50, //Email
    ]), $handler));

    $group->post('/verifyEmail', '\App\Controller\AccountController:verifyEmail')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
        "email" => fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($e) <= 50, //Email
        "verification_token" => "/^[A-Za-z0-9]{1,100}$/"
    ]), $handler));

    $group->get('/usernameAvailable/{username}','\App\Controller\AccountController:usernameAvailable');

    $group->post('/login', '\App\Controller\AccountController:login')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
        "identifier",
        "password",
        "recaptcha_token"
    ]), $handler));

    $group->get('/details','\App\Controller\AccountController:accountDetails')->add("\App\Class\Session:sessionMiddleware");

    $group->post('/logout', '\App\Controller\AccountController:logout')->add("\App\Class\Session:sessionMiddleware");

    $group->post('/changePassword', '\App\Controller\AccountController:changePassword')->add("\App\Class\Session:sessionMiddleware")->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
        "old_password",
        "new_password" => "/^(?=.*?[a-z])(?=.*?[0-9])(.){8,50}$/", //Password: 8-50 characters, at least 1 letter and 1 number
    ]), $handler));

    $group->post('/requestPasswordReset', '\App\Controller\AccountController:requestPasswordReset')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
        "email" => fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($e) <= 50,
        "recaptcha_token"
    ]), $handler));

    $group->post('/submitPasswordReset', '\App\Controller\AccountController:submitPasswordReset')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
        "email" => fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($e) <= 50,
        "reset_token" => "/^[A-Za-z0-9]{1,100}$/",
        "new_password" => "/^(?=.*?[a-z])(?=.*?[0-9])(.){8,50}$/", //Password: 8-50 characters, at least 1 letter and 1 number
    ]), $handler));

    $group->post('/changeUsername', '\App\Controller\AccountController:changeUsername')->add("\App\Class\Session:sessionMiddleware")->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
        "username" => "/^(?=.*?[a-z])[a-z0-9]{1,20}$/", //username - alphanumeric 1-20 characters, at least 1 letter
    ]), $handler));

});


//courses routes
$app->group('/courses', function (RouteCollectorProxy $group) {

  $group->get('/','\App\Controller\CoursesController:listCourses');

  $group->get('/{course_slug}','\App\Controller\CoursesController:getCourse');

  $group->post('/{course_slug}/enroll','\App\Controller\CoursesController:courseEnroll')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run");

  $group->post('/{course_slug}/unenroll','\App\Controller\CoursesController:courseUnenroll')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run");

  $group->get('/{course_slug}/chapters/{chapter_slug}/level/{level_slug}','\App\Controller\CoursesController:level')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run");

  $group->post('/{course_slug}/chapters/{chapter_slug}/level/{level_slug}/saveDraft','\App\Controller\CoursesController:saveDraft')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run")->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "code" //TODO: Length limit?
  ]), $handler));;


});




//Error handler
//$errorMiddleware = $app->addErrorMiddleware(true, true, true);
//$errorMiddleware->setDefaultErrorHandler('App\Controller\ErrorController:errorHandler');

//Kick things off!
$app->run();
