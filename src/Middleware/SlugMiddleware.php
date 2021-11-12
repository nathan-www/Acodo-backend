<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use Slim\Exception\HttpBadRequestException;
use Slim\Routing\RouteContext;

class SlugMiddleware
{

    // Validate course, chapter and level slugs passed as API URL arguments
    public static function run(Request $request, RequestHandler $handler)
    {
        $db = new \App\Database\Database();

        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $args = $route->getArguments();

        //$request = $request->withAttribute('params');

        if (isset($args['course_slug'])) {
            $course = $db->query('SELECT course_id from courses WHERE course_slug=?', [$args['course_slug']]);

            if (count($course) < 1) {
                throw new HttpBadRequestException($request, "Invalid course");
            } else {
                $request = $request->withAttribute('course_id', $course[0]['course_id']);
            }
        }

        if (isset($args['course_slug']) && isset($args['chapter_slug'])) {
            $chapter = $db->query('SELECT chapter_id from chapters WHERE chapter_slug=? AND course_id=?', [$args['chapter_slug'],$course[0]['course_id']]);

            if (count($chapter) < 1) {
                throw new HttpBadRequestException($request, "Invalid chapter");
            } else {
                $request = $request->withAttribute('chapter_id', $chapter[0]['chapter_id']);
            }
        }

        if (isset($args['course_slug']) && isset($args['chapter_slug']) && isset($args['level_slug'])) {
            $level = $db->query('SELECT level_id from levels WHERE level_slug=? AND course_id=?', [$args['level_slug'],$course[0]['course_id']]);

            if (count($level) < 1) {
                throw new HttpBadRequestException($request, "Invalid level");
            } else {
                $request = $request->withAttribute('level_id', $level[0]['level_id']);
            }
        }

        return $handler->handle($request);
    }
}
