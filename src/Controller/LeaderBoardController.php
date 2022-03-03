<?php

namespace App\Controller;

use Slim\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Exception\HttpSpecializedException;

  class LeaderBoardController extends Controller
  {

    public function leaderboard(Request $request, $response, $args)
    {

      /* Upvotes */
      $upvotes = $this->db()->query("SELECT solutions.user_id, solution_votes.timestamp, solution_votes.vote FROM solution_votes INNER JOIN solutions ON solutions.solution_id=solution_votes.solution_id");
      $user_upvotes_all = [];
      $user_upvotes_today = [];

      foreach($upvotes as $u){
        if(!isset($user_upvotes_all[$u['user_id']])){
            $user_upvotes_all[$u['user_id']] = +$u['vote'];
        } else {
            $user_upvotes_all[$u['user_id']] += +$u['vote'];
        }

        if($u['timestamp'] >= (time() - 86400)){
          //upvoted today
          if(!isset($user_upvotes_today[$u['user_id']])){
              $user_upvotes_today[$u['user_id']] = +$u['vote'];
          } else {
              $user_upvotes_today[$u['user_id']] += +$u['vote'];
          }
        }
      }

      /* XP (all time) and streak */
      $users = $this->db()->query("SELECT * FROM accounts");
      $user_xp_all = [];
      $user_streak = [];

      foreach($users as $user){
        $user = new \App\Class\User($user['user_id']);
        if($user->isStreakValid()){
            $user_streak[$user->user['user_id']] = +$user->user['streak_days'];
        } else {
            $user_streak[$user->user['user_id']] = 0;
        }

        $user_xp_all[$user->user['user_id']] = $user->user['xp'];
      }

      /* XP today */
      $user_xp_today = [];
      $xp = $this->db()->query("SELECT * FROM level_complete WHERE timestamp>?",[ time() - 86400 ]);
      foreach($xp as $u){
        if(isset($user_xp_today[$u['user_id']])){
            $user_xp_today[$u['user_id']] += +$u['xp'];
        } else {
            $user_xp_today[$u['user_id']] = +$u['xp'];
        }
      }

      /* Sort leaderboards */
      arsort($user_upvotes_all);
      arsort($user_upvotes_today);
      arsort($user_xp_all);
      arsort($user_xp_today);
      arsort($user_streak);

      $user_upvotes_all = array_slice($user_upvotes_all, 0, 35, true);
      $user_upvotes_today = array_slice($user_upvotes_today, 0, 35, true);
      $user_xp_all = array_slice($user_xp_all, 0, 35, true);
      $user_xp_today = array_slice($user_xp_today, 0, 35, true);
      $user_streak = array_slice($user_streak, 0, 35, true);

      $user_upvotes_all = array_map(function($score,$user_id){
        $user = new \App\Class\User($user_id);
          return [
              "username"=>$user->basicInfo()['username'],
              "score"=>$score
          ];
      },$user_upvotes_all,array_keys($user_upvotes_all));

      $user_upvotes_today = array_map(function($score,$user_id){
        $user = new \App\Class\User($user_id);
          return [
              "username"=>$user->basicInfo()['username'],
              "score"=>$score
          ];
      },$user_upvotes_today,array_keys($user_upvotes_today));

      $user_xp_all = array_map(function($score,$user_id){
        $user = new \App\Class\User($user_id);
          return [
              "username"=>$user->basicInfo()['username'],
              "score"=>$score
          ];
      },$user_xp_all,array_keys($user_xp_all));

      $user_xp_today = array_map(function($score,$user_id){
        $user = new \App\Class\User($user_id);
          return [
              "username"=>$user->basicInfo()['username'],
              "score"=>$score
          ];
      },$user_xp_today,array_keys($user_xp_today));

      $user_streak = array_map(function($score,$user_id){
        $user = new \App\Class\User($user_id);
          return [
              "username"=>$user->basicInfo()['username'],
              "score"=>$score
          ];
      },$user_streak,array_keys($user_streak));

      return $this->jsonResponse([
        "status"=>"success",
        "leaderboards"=>[
          [
            "name"=>"Upvotes",
            "measure"=>"Votes",
            "leaderboard"=>$user_upvotes_all
          ],
          [
            "name"=>"XP",
            "measure"=>"XP",
            "leaderboard"=>$user_xp_all
          ],
          [
            "name"=>"Streak",
            "measure"=>"Days",
            "leaderboard"=>$user_streak
          ],
          [
            "name"=>"Upvotes today",
            "measure"=>"Votes",
            "leaderboard"=>$user_upvotes_today
          ],
          [
            "name"=>"XP today",
            "measure"=>"XP",
            "leaderboard"=>$user_xp_today
          ]
        ]
      ]);
    }


  }
