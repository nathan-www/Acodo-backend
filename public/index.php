<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteCollectorProxy;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpBadRequestException;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../env.php';


$app = AppFactory::create();
$app->setBasePath('/api');


/* For local testing
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
            ->withHeader('Access-Control-Allow-Origin', 'localhost')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});
*/

/* CSRF protection */
$app->add(function ($request, $handler) {

    if($request->hasHeader('X-CSRF') && isset($_COOKIE['acodo_csrf_token']) && $request->getHeaderLine('X-CSRF') == $_COOKIE['acodo_csrf_token'] && strlen($_COOKIE['acodo_csrf_token']) > 5){
        $handler->handle($request);
    } else {
      echo($_COOKIE['acodo_csrf_token'] . "::");
      echo($request->getHeaderLine('X-CSRF'));
      throw new HttpBadRequestException($request, "Invalid CSRF token");
    }
});

//Account routes
$app->group('/account', function (RouteCollectorProxy $group) {
    $group->post('/register', '\App\Controller\AccountController:register')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "username" => "/^(?=.*?[a-z])[a-z0-9]{1,20}$/", //username - alphanumeric 1-20 characters, at least 1 letter
      "email" => fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($e) <= 50, //Email
      "password" => "/^(?=.*?[a-z])(?=.*?[0-9])(.){8,50}$/", //Password: 8-50 characters, at least 1 letter and 1 number
      "recaptcha_token"
    ]), $handler));

    $group->post('/resendVerificationEmail', '\App\Controller\AccountController:resendVerificationEmail')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
        "identifier" => fn ($e) => strlen($e) <= 50, //Email or username
    ]), $handler));

    $group->post('/verifyEmail', '\App\Controller\AccountController:verifyEmail')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
        "email" => fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($e) <= 50, //Email
        "verification_token" => "/^[A-Za-z0-9]{1,100}$/"
    ]), $handler));

    $group->get('/usernameAvailable/{username}', '\App\Controller\AccountController:usernameAvailable');

    $group->post('/login', '\App\Controller\AccountController:login')->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
        "identifier",
        "password",
        "recaptcha_token"
    ]), $handler));

    $group->get('/details', '\App\Controller\AccountController:accountDetails')->add("\App\Class\Session:sessionMiddleware");

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
    $group->get('/', '\App\Controller\CoursesController:listCourses');

    $group->get('/{course_slug}', '\App\Controller\CoursesController:getCourse');

    $group->post('/{course_slug}/enroll', '\App\Controller\CoursesController:courseEnroll')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run");

    $group->post('/{course_slug}/unenroll', '\App\Controller\CoursesController:courseUnenroll')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run");

    $group->get('/{course_slug}/chapters/{chapter_slug}/level/{level_slug}', '\App\Controller\CoursesController:level')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run");

    $group->post('/{course_slug}/chapters/{chapter_slug}/level/{level_slug}/saveDraft', '\App\Controller\CoursesController:saveDraft')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run")->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "code" => "/^[A-Za-z0-9+=\/]{1,30000}$/" //Base64 - max 30k characters
  ]), $handler));

    $group->post('/{course_slug}/chapters/{chapter_slug}/level/{level_slug}/markComplete', '\App\Controller\CoursesController:markComplete')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run");


    //solutions routes
    $group->get('/{course_slug}/chapters/{chapter_slug}/level/{level_slug}/solutions', '\App\Controller\CoursesController:solutions')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run");

    $group->post('/{course_slug}/chapters/{chapter_slug}/level/{level_slug}/solutions/submit', '\App\Controller\CoursesController:submitSolution')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run")->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "code" => "/^[A-Za-z0-9+=\/]{1,30000}$/", //Base64 - max 30k characters
      "v" => "/^[A-Za-z0-9+=\/]{1,300}$/" //Base64 verification hash
  ]), $handler));

    $group->post('/{course_slug}/chapters/{chapter_slug}/level/{level_slug}/solutions/vote', '\App\Controller\CoursesController:voteSolution')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run")->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "solution_id" => "/^[0-9]{8,20}$/",
      "vote_type" => "/^[A-Za-z0-9]{1,10}$/",
      "vote" => fn ($e) => $e==0 || $e==1 || $e==-1
  ]), $handler));


    //messages routes
    $group->get('/{course_slug}/chapters/{chapter_slug}/level/{level_slug}/messages/{since}', '\App\Controller\CoursesController:messages')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run");

    $group->post('/{course_slug}/chapters/{chapter_slug}/level/{level_slug}/messages/send', '\App\Controller\CoursesController:sendMessage')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run")->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "reply_to" => [
        "checker"=>"/^[0-9]{8,20}$/",
        "optional"=>true
      ],
      "message_content" => "/^[A-Za-z0-9+=\/]{1,10000}$/" //Base64 - max 10k characters
  ]), $handler));

    $group->post('/{course_slug}/chapters/{chapter_slug}/level/{level_slug}/messages/edit', '\App\Controller\CoursesController:editMessage')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run")->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "message_id" => "/^[0-9]{8,20}$/",
      "message_content" => "/^[A-Za-z0-9+=\/]{1,10000}$/" //Base64 - max 10k characters
  ]), $handler));


    $group->post('/{course_slug}/chapters/{chapter_slug}/level/{level_slug}/messages/vote', '\App\Controller\CoursesController:voteMessage')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run")->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "message_id" => "/^[0-9]{8,20}$/",
      "vote" => fn ($e) => $e==0 || $e==-1 || $e==1
  ]), $handler));

    $group->post('/{course_slug}/chapters/{chapter_slug}/level/{level_slug}/messages/delete', '\App\Controller\CoursesController:deleteMessage')->add("\App\Class\Session:sessionMiddleware")->add("\App\Middleware\SlugMiddleware:run")->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
      "message_id" => "/^[0-9]{8,20}$/"
  ]), $handler));
});


$app->get('/unread-notifications','\App\Controller\MiscellaneousController:unreadNotifications')->add("\App\Class\Session:sessionMiddleware");
$app->get('/list-notifications','\App\Controller\MiscellaneousController:listNotifications')->add("\App\Class\Session:sessionMiddleware");


$app->get('/profile/{username}', '\App\Controller\MiscellaneousController:profile');
$app->get('/leaderboards', '\App\Controller\LeaderBoardController:leaderboard');

$app->post('/editProfile', '\App\Controller\MiscellaneousController:editProfile')->add("\App\Class\Session:sessionMiddleware")->add(fn ($request, $handler) => App\Middleware\ParameterCheckerMiddleware::run($request->withAttribute('params', [
    "twitter" => [
      "checker" => "/^[a-z0-9_]{4,15}$/",
      "optional" => true
    ],
    "linkedin" => [
      "checker" => "/^[a-z0-9-]{3,100}$/",
      "optional" => true
    ],
    "github" => [
      "checker" => "/^[a-z0-9-_]{1,39}$/",
      "optional" => true
    ],
    "website" => [
      "checker" => fn ($e) => strlen($e)<=200 && filter_var($e, FILTER_VALIDATE_URL),
      "optional" => true
    ],
    "location" => [
      "checker" => "/^[A-Za-z0-9- ,]{1-35}$/",
      "optional" => true
    ],
    "show_email" => [
      "checker" => fn ($e) => is_bool($e),
      "optional" => true
    ],
    "delete_field" => [
      "checker" => "/^(twitter|linkedin|github|website|location)$/",
      "optional" => true
    ]
]), $handler));


//Error handler
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler('App\Controller\ErrorController:errorHandler');

//Kick things off!
$app->run();
