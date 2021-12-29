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

            $chapters = array_merge(...array_map(function ($chapter) use ($session) {
                return ([$chapter->slug => [
                  "chapter_title" => $chapter->title,
                  "chapter_slug" => $chapter->slug,
                  "chapter_description" => $chapter->description,
                  "levels" => array_merge(...array_map(function ($level) use ($session) {
                      return ([$level->slug => [
                      "level_title" => $level->title,
                      "level_slug" => $level->slug,
                      "complete" => $session['authenticated'] && in_array($session['session']['user_id'], $level->get_completions()),
                    ]]);
                  }, $chapter->get_levels()))
              ]]);
            }, $c->get_chapters()));


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
          "authors"=>array_map(fn ($e) => $e->basicInfo(), $level->get_authors()),
          "complete"=>in_array($request->getAttribute('user_id'), $level->get_completions()),
          "forfeited"=>in_array($request->getAttribute('user_id'), $level->get_forfeited()),
          "difficulty"=>$level->difficulty,
          "language"=>$level->language,
          "xp"=>$level->xp,
          "brief"=>$level->get_brief(),
          "solutions_count"=>count($level->get_solutions()),
          "default_code"=>$level->get_default_code(),
          "draft_code"=>[
            "code"=>$level->get_user_draft($request->getAttribute('user_id'))['code'],
            "timestamp"=>$level->get_user_draft($request->getAttribute('user_id'))['timestamp']
          ],
          "test_code"=>$level->get_test_code(),
          "unit_tests"=>array_map(fn ($e) => array_slice($e, 1), $level->get_unit_tests()),
          "feedback_test"=>$level->feedback_test
        ]);
    }


    public function saveDraft(Request $request)
    {
        $json = $this->jsonRequest($request);

        $level = new \App\Class\Level($request->getAttribute('level_id'));
        $level->set_user_draft($request->getAttribute('user_id'), base64_encode($json['code']));

        return $this->jsonResponse([
          'status'=>'success'
        ]);
    }

    public function markComplete(Request $request)
    {
        $level = new \App\Class\Level($request->getAttribute('level_id'));
        $user = new \App\Class\User($request->getAttribute('user_id'));

        if (in_array($request->getAttribute('user_id'), $level->get_completions())) {
            return $this->jsonResponse([
              'status'=>'fail',
              'error_message'=>'Level is already complete'
            ]);
        } else {

            $user->renewStreak();

            $xp_earned = $level->xp;
            if (in_array($request->getAttribute('user_id'), $level->get_forfeited())) {
                //User has forfeited level, do not award XP
                $xp_earned = 0;
            }

            //Make sure user is enrolled on course
            $this->db()->insert('course_enrollments',['course_id'=>$request->getAttribute('course_id'),'user_id'=>$request->getAttribute('user_id')]);


            $this->db()->insert('level_complete', ['level_id'=>$level->level_id,'user_id'=>$request->getAttribute('user_id'),'timestamp'=>time(),'xp'=>$xp_earned]);
            $this->db()->update('accounts', ['user_id'=>$user->user['user_id']], ['xp'=>($user->user['xp'] + $xp_earned)]);
            return $this->jsonResponse([
              'status'=>'success'
            ]);
        }
    }


    public function solutions(Request $request)
    {
        $level = new \App\Class\Level($request->getAttribute('level_id'));


        if (!in_array($request->getAttribute('user_id'), $level->get_forfeited())) {
            //Mark user as forfeited for this level
            $this->db()->insert('level_forfeit', ['level_id'=>$level->level_id,'user_id'=>$request->getAttribute('user_id')]);
        }

        return $this->jsonResponse([
          "status"=>"success",
          "solutions"=>array_map(function ($solution) use ($request) {
              $user_vote = array_filter($solution->get_votes(), function ($e) use ($request) {
                  return ($e['user_id']==$request->getAttribute('user_id'));
              });
              if (count($user_vote) < 1) {
                  $user_vote = 0;
              } else {
                  $user_vote = +$user_vote[0]['vote'];
              }

              return [
                "solution_id" => $solution->solution_id,
                "user" => $solution->user->basicInfo(),
                "timestamp" => +$solution->timestamp,
                "upvotes" => count(array_filter($solution->get_votes(), fn ($e) => ($e['vote']=="1"))),
                "downvotes" => count(array_filter($solution->get_votes(), fn ($e) => ($e['vote']=="-1"))),
                "user_vote" => $user_vote,
                "code" => $solution->code,
                "badges" => $solution->get_badges()
              ];
          }, $level->get_solutions())
        ]);
    }




    public function submitSolution(Request $request)
    {
        $level = new \App\Class\Level($request->getAttribute('level_id'));
        $json = $this->jsonRequest($request);

        if (in_array($request->getAttribute('user_id'), $level->get_forfeited())) {
            //User is forfeited and cannot submit any solutions
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"You have forfeited this level and cannot submit a solution"
            ]);
        }

        //Verification token to make abuse more difficult
        //TODO: Better solution?
        if($json['v'] !== md5($json['code'] . "super_secret_salt_eq55M4Q2xQ" . $request->getAttribute('user_id'))){

/*
          print_r([
            "v"=>$json['v'],
            "code"=>$json['code'],
            "md5"=>md5($json['code'] . "super_secret_salt_eq55M4Q2xQ")
          ]);
*/

          return $this->jsonResponse([
            "status"=>"fail",
            "error_message"=>"Invalid solution"
          ]);
        }

        \App\Class\Solution::submit($request->getAttribute('level_id'), $request->getAttribute('user_id'), base64_encode($json['code']));

        return $this->jsonResponse([
          "status"=>"success"
        ]);
    }



    public function voteSolution(Request $request)
    {
        $json = $this->jsonRequest($request);
        $solution = new \App\Class\Solution($json['solution_id']);

        if (!$solution->solution_exists) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Invalid solution"
            ]);
        }

        /*
        if($solution->user->user['user_id'] == $request->getAttribute('user_id')){
          return $this->jsonResponse([
            "status"=>"fail",
            "error_message"=>"You cannot vote on your own solution"
          ]);
        }
        */

        if ($json['vote_type'] == 'main') {
            $solution->vote_solution($request->getAttribute('user_id'), $json['vote']);
        } else {
            $solution->vote_badge($request->getAttribute('user_id'), $json['vote_type'], $json['vote']);
        }

        return $this->jsonResponse([
          "status"=>"success"
        ]);
    }



    public function messages(Request $request, $response, $args)
    {
        $level = new \App\Class\Level($request->getAttribute('level_id'));

        if (!preg_match("/^[0-9]{1,}$/", $args['since'])) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Invalid parameter 'since'"
            ]);
        }

        $messages = $level->get_messages($args['since']);

        $last_timestamp = 0;
        foreach ($messages as $m) {
            if ($m->last_change > $last_timestamp) {
                $last_timestamp = $m->last_change;
            }
        }

        return $this->jsonResponse([
          "status"=>"success",
          "last_change"=>$last_timestamp,
          "messages"=>array_map(function ($message) use ($request) {

              $user_vote = array_filter($message->get_votes(), fn ($e) =>$e['user_id']==$request->getAttribute('user_id'));
              if (count($user_vote) < 1) {
                  $user_vote = 0;
              } else {
                  $user_vote = +$user_vote[0]['vote'];
              }

              return [
                "message_id"=>$message->message_id,
                "user"=>$message->user->basicInfo(),
                "message_content"=>$message->message_content,
                "last_edited"=>$message->last_edited,
                "created"=>$message->created,
                "upvotes"=>count(array_filter($message->get_votes(), fn ($e) =>$e['vote']==1)),
                "downvotes"=>count(array_filter($message->get_votes(), fn ($e) =>$e['vote']==-1)),
                "user_vote"=>$user_vote,
                "reply_to"=>$message->reply_to,
                "tags"=>array_map(fn ($e) =>$e['user_id'], $message->get_tags()),
              ];
          }, $messages)
        ]);
    }

    public function sendMessage(Request $request)
    {
        $json = $this->jsonRequest($request);
        $tags = [];

        if (count($this->db()->query('SELECT message_id FROM messages WHERE edited_timestamp>=? AND user_id=?', [
          (time()-2),
          $request->getAttribute('user_id')
        ])) > 0) {
            //Rate limit to 1 message every 2 seconds
            return $this->jsonResponse([
            "status"=>"fail",
            "error_message"=>"You are sending messages too fast"
          ]);
        }

        if (isset($json['reply_to'])) {
            $reply_to = new \App\Class\Message($json['reply_to']);

            if (!$reply_to->message_exists) {
                return $this->jsonResponse([
                  "status"=>"fail",
                  "error_message"=>"The message you are replying to does not exist"
                ]);
            }

            $tags[] = $reply_to->user->user['user_id']; //Tag reply user

            if (trim($reply_to->reply_to) !== "") {
                $tags[] = (new \App\Class\Message($reply_to->reply_to))->user->user['user_id']; //Tag top-level sender
                $reply_to = $reply_to->reply_to; //Reply to top-level message
            } else {
                $reply_to = $reply_to->message_id; //Already replying to top-level messagee
            }
        } else {
            $reply_to = "";
        }

        $tags = array_unique(array_filter($tags, function ($e) use ($request) {
            return $e!==$request->getAttribute('user_id');
        }));

        \App\Class\Message::send($request->getAttribute('user_id'), $request->getAttribute('level_id'), base64_encode($json['message_content']), $tags, $reply_to);


        return $this->jsonResponse([
          "status"=>"success"
        ]);
    }


    public function editMessage(Request $request)
    {
        $json = $this->jsonRequest($request);
        $message = new \App\Class\Message($json['message_id']);

        if (!$message->message_exists) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Message does not exist"
            ]);
        }

        if ($message->user->user['user_id'] !== $request->getAttribute('user_id')) {
            return $this->jsonResponse([
            "status"=>"fail",
            "error_message"=>"You do not have permission to edit this message"
          ]);
        }

        $message->edit(base64_encode($json['message_content']));

        return $this->jsonResponse([
          "status"=>"success"
        ]);
    }

    public function voteMessage(Request $request)
    {
        $json = $this->jsonRequest($request);
        $message = new \App\Class\Message($json['message_id']);

        if (!$message->message_exists) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Message does not exist"
            ]);
        }

        $message->vote($request->getAttribute('user_id'), $json['vote']);

        return $this->jsonResponse([
          "status"=>"success"
        ]);
    }

    public function deleteMessage(Request $request)
    {
        $json = $this->jsonRequest($request);
        $message = new \App\Class\Message($json['message_id']);

        if (!$message->message_exists) {
            return $this->jsonResponse([
              "status"=>"fail",
              "error_message"=>"Message does not exist"
            ]);
        }

        if ($message->user->user['user_id'] !== $request->getAttribute('user_id')) {
            return $this->jsonResponse([
            "status"=>"fail",
            "error_message"=>"You do not have permission to delete this message"
          ]);
        }

        $message->delete();

        return $this->jsonResponse([
          "status"=>"success"
        ]);

    }
}
