<?php

namespace App\Controller;

use Slim\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Exception\HttpSpecializedException;

class CoursesController extends Controller
{
    public function listCourses(Request $request)
    {
        $db = new \App\Database\Database();
        $courseRows = array_map(fn ($e) => new \App\Class\Course($e['course_id']), $db->query("SELECT course_id FROM courses"));

        $session = \App\Class\Session::getSession();

        $courses = [];
        foreach ($courseRows as $c) {
            $courses[] = [
            "course_title"=>$c->title,
            "course_slug"=>$c->slug,
            "description"=>$c->description,
            "thumbnail"=>$c->thumbnail,
            "languages"=>$c->get_languages(),
            "difficulty"=>$c->difficulty,
            "authors"=>array_map(fn ($e) => $e->basicInfo(), $c->get_authors()),
            "total_xp"=>$c->total_xp,
            "duration_hours"=>$c->duration_hours,
            "enrolled"=> $session['authenticated'] && in_array($session['session']['user_id'], $c->get_enrollments()),
            "total_enrollments" => count($c->get_enrollments())
          ];
        }

        return $this->jsonResponse([
          "status"=>"success",
          "courses"=>$courses
        ]);
    }

    public function getCourse(Request $request, $response, $args)
    {
        $courseID = $this->db()->query("SELECT course_id FROM courses WHERE course_slug=?", [$args['course_slug']]);

        if (count($courseID) < 1) {
            return $this->jsonResponse([
              "status"=>"fail"
            ]);
        } else {
            $c = new \App\Class\Course($courseID[0]['course_id']);
            $session = \App\Class\Session::getSession();

            $chapters = array_map(function ($chapter) use ($session) {
                return ([
              "chapter_title" => $chapter->title,
              "chapter_slug" => $chapter->slug,
              "chapter_description" => $chapter->description,
              "levels" => array_map(function ($level) use ($session) {
                  return [
                  "level_title" => $level->title,
                  "level_slug" => $level->slug,
                  "complete" => $session['authenticated'] && in_array($session['session']['user_id'], $level->get_completions()),
                ];
              }, $chapter->get_levels())
            ]);
            }, $c->get_chapters());


            return $this->jsonResponse([
            "status"=>"success",
            "course_title"=>$c->title,
            "course_slug"=>$c->slug,
            "description"=>$c->description,
            "thumbnail"=>$c->thumbnail,
            "languages"=>$c->get_languages(),
            "difficulty"=>$c->difficulty,
            "authors"=>array_map(fn ($e) => $e->basicInfo(), $c->get_authors()),
            "total_xp"=>$c->total_xp,
            "duration_hours"=>$c->duration_hours,
            "enrolled"=> $session['authenticated'] && in_array($session['session']['user_id'], $c->get_enrollments()),
            "total_enrollments" => count($c->get_enrollments()),
            "chapters" => $chapters

          ]);
        }
    }

    public function courseEnroll(Request $request)
    {
        $this->db()->delete('course_enrollments', ['course_id'=>$request->getAttribute('course_id'),'user_id'=>$request->getAttribute('user_id')]);
        $this->db()->insert('course_enrollments', ['course_id'=>$request->getAttribute('course_id'),'user_id'=>$request->getAttribute('user_id')]);

        return $this->jsonResponse([
          "status"=>"success"
        ]);
    }

    public function courseUnenroll(Request $request)
    {

        $this->db()->delete('course_enrollments', ['course_id'=>$request->getAttribute('course_id'),'user_id'=>$request->getAttribute('user_id')]);

        return $this->jsonResponse([
          "status"=>"success"
        ]);
    }

    public function level(Request $request)
    {

        $level = new \App\Class\Level($request->getAttribute('level_id'));

        return $this->jsonResponse([
          "status"=>"success",
          "level_title"=>$level->title,
          "level_slug"=>$level->slug,
          "authors"=>array_map(fn ($e) => $e->basicInfo(),$level->get_authors()),
          "complete"=>in_array($request->getAttribute('user_id'),$level->get_completions()),
          "forfeited"=>in_array($request->getAttribute('user_id'),$level->get_forfeited()),
          "difficulty"=>$level->difficulty,
          "language"=>$level->language,
          "xp"=>$level->xp,
          "brief"=>$level->get_brief(),
          "default_code"=>$level->get_default_code(),
          "test_code"=>$level->get_test_code(),
          "unit_tests"=>array_map(fn($e) => array_slice($e,1), $level->get_unit_tests()),
          "feedback_test"=>$level->feedback_test
        ]);

    }

}
