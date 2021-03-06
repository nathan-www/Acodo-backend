<?php

namespace App\Controller;

use Slim\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Exception\HttpSpecializedException;

  class MiscellaneousController extends Controller
  {
      public function profile(Request $request, $response, $args)
      {
          if (!preg_match("/^(?=.*?[a-z])[a-z0-9]{1,20}$/", $args['username'])) {
              return $this->jsonResponse([
                "status"=>"fail",
                "error_message"=>"User does not exist"
              ]);
          }

          $user = new \App\Class\User($args['username']);

          if (!$user->userExists) {
              return $this->jsonResponse([
                "status"=>"fail",
                "error_message"=>"User does not exist"
              ]);
          }


          $socials = [];
          if ($user->user['show_email'] == "true") {
              $socials['email'] = $user->user['email'];
          }

          foreach (["twitter","linkedin","github","website","location"] as $s) {
              if (trim($user->user[$s]) !== "") {
                  $socials[$s] = $user->user[$s];
              }
          }

          $streak = 0;
          if ($user->isStreakValid()) {
              $streak = $user->user['streak_days'];
          }

          $languages = [];
          foreach ($user->get_solutions() as $s) {
              if (!isset($languages[$s['language']])) {
                  $languages[$s['language']] = [
                  "language"=>$s['language'],
                  "solutions"=>0
                ];
              }
              $languages[$s['language']]['solutions'] += 1;
          }

          return $this->jsonResponse([
            "status"=>"success",
            "username"=>$user->user['username'],
            "user_id"=>$user->user['user_id'],
            "xp"=>$user->user['xp'],
            "joined_timestamp"=>$user->user['registration_timestamp'],
            "last_seen"=>+$user->user['last_active_timestamp'],
            "courses"=>array_map(function ($course) use ($user) {
                return [
                  "course_title"=>$course->title,
                  "course_slug"=>$course->slug,
                  "thumbnail"=>$course->thumbnail,
                  "progress"=>$user->get_course_progress($course->course_id),
                  "duration_hours"=>$course->duration_hours,
                  "total_xp"=>$course->total_xp,
                  "languages"=>$course->get_languages()
                ];
            }, $user->get_courses()),
            "languages"=>array_values($languages),
            "socials"=>$socials,
            "stats"=>[
                "solution_upvotes"=> array_reduce($user->get_solutions(), function ($carry, $i) {
                    return $carry + $i['upvotes'];
                }, 0),
                "solutions"=>count($user->get_solutions()),
                "best_solutions"=>count(array_filter($user->get_solutions(), fn ($e) =>$e['best_solution'])),
                "comments"=>count($this->db()->query('SELECT message_id FROM messages WHERE user_id=?', [$user->user['user_id']])),
                "streak"=>$streak
            ],
            "badges"=>$user->get_badges(),
            "solutions"=>$user->get_solutions()
          ]);
      }


      public function unreadNotifications(Request $request)
      {
        return $this->jsonResponse([
          "status"=>"success",
          "count"=> \App\Class\Notification::unreadCount($request->getAttribute('user_id'))
        ]);
      }

      public function listNotifications(Request $request)
      {
        return $this->jsonResponse([
          "status"=>"success",
          "notifications"=> \App\Class\Notification::list($request->getAttribute('user_id'))
        ]);
      }

      public function editProfile(Request $request)
      {
          $json = $this->jsonRequest($request);

          $editField = false;

          if (isset($json['twitter'])) {
              $editField = "twitter";
              $editValue = $json['twitter'];
          } elseif (isset($json['linkedin'])) {
              $editField = "linkedin";
              $editValue = $json['linkedin'];
          } elseif (isset($json['github'])) {
              $editField = "github";
              $editValue = $json['github'];
          } elseif (isset($json['website'])) {
              $editField = "website";
              $editValue = $json['website'];
          } elseif (isset($json['location'])) {
              $editField = "location";
              $editValue = $json['location'];
          } elseif (isset($json['show_email'])) {
              $editField = "show_email";
              if ($json['show_email']) {
                  $editValue = "true";
              } else {
                  $editValue = "false";
              }
          }

          if (isset($json['delete_field'])) {
              $update = [];
              $update[$json['delete_field']] = "";
              $this->db()->update('accounts', [
                "user_id"=>$request->getAttribute('user_id')
              ], $update);
          }
          if ($editField !== false) {
              $update = [];
              $update[$editField] = $editValue;
              $this->db()->update('accounts', [
              "user_id"=>$request->getAttribute('user_id')
            ], $update);
          }

          return $this->jsonResponse([
            "status"=>"success"
          ]);
      }

  }
