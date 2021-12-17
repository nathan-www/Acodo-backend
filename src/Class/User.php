<?php

  namespace App\Class;

  class User
  {
      public $user;
      public $userExists;
      private $solutions;
      private $badges;

      protected static function db()
      {
          return new \App\Database\Database();
      }

      public function __construct($identifier)
      {
          $user = $this->db()->query("SELECT * FROM accounts WHERE email=? OR username=? OR user_id=?", [$identifier,$identifier,$identifier]);
          if (count($user) < 1) {
              $this->userExists = false;
          } else {
              $this->user = $user[0];
              $this->userExists = true;
          }
      }

      public function basicInfo()
      {
          return [
            "user_id" => $this->user['user_id'],
            "username" => $this->user['username'],
            "xp" => $this->user['xp']
          ];
      }

      //Checks if current streak is valid
      public function isStreakValid()
      {
        $streak_last_timestamp = $this->user['streak_last_timestamp']; //last streak renewal
        $lastDoW = date('N', $streak_last_timestamp); //last streak renewal day of week (0-7)
        $nowDoW = date('N', time()); //current day of week (0-7)

        return (!(($streak_last_timestamp < time()-172800) || ($nowDoW == 0 && $lastDoW > $nowDoW && $lastDoW !== 7) || ($nowDoW > 0 && ($lastDoW < $nowDoW-1 || $lastDoW>$nowDoW))));
      }

      //Renew streak, eg. on completion of a level
      public function renewStreak()
      {
        if(!$this->isStreakValid()){
            //failed streak
            $this->db()->update('accounts',['user_id'=>$this->user['user_id']],['streak_last_timestamp'=>time(),'streak_days'=>0]);
          }
          else{
            //successful streak
            $streak_days = $this->user['streak_days'];
            if($lastDoW !== $nowDoW){
                $streak_days += 1;
            }
            $this->db()->update('accounts',['user_id'=>$this->user['user_id']],['streak_last_timestamp'=>time(),'streak_days'=>$streak_days]);
          }

      }



      public function sendVerificationEmail()
      {
          if ($this->user['email_verified'] !== "false") {
              return [
                "status"=>"fail",
                "error_message"=>"Email already verified"
              ];
          } elseif ($this->user['last_verification_email_sent'] > (time() - 60)) {
              //Limit to one email per minute
              return [
                "status"=>"fail",
                "error_message"=>"Please wait a minute before requesting another email"
              ];
          } else {

              //Pseudo random string
              $token = substr(bin2hex(openssl_random_pseudo_bytes(255)), 0, 35);

              //Send verification email
              \App\Email\Email::sendEmail([
                "to"=>$this->user['email'],
                "subject"=>"Acodo: Verify your email",
                "template"=>"EmailVerification",
                "variables"=>[
                  "username"=>$this->user['username'],
                  "email"=>$this->user['email'],
                  "ip"=>\App\Class\Security::getUserIP(),
                  "ip_location"=>\App\Class\Security::getUserLocation(),
                  "device"=>\App\Class\Security::getUserAgent(),
                  "token"=>$token
                ]
              ]);

              //Update database with token
              $this->db()->update('accounts', [ "user_id"=>$this->user['user_id'] ], [ "verification_token"=>$token, "last_verification_email_sent"=>time() ]);

              return [
                "status"=>"success"
              ];
          }
      }

      //Send a notification to a user
      public function sendNotification($notificationData)
      {
          $this->db()->insert('notifications', [
            "notification_id"=>rand(1000000000, 9999999999),
            "user_id"=>$this->user['user_id'],
            "timestamp"=>time(),
            "notification_data"=>json_encode($notificationData)
          ]);
      }

      //Get the user's progress through a given course (percentage of levels completed)
      public function get_course_progress($course_id)
      {
          $levels = $this->db()->query('SELECT level_id FROM levels WHERE course_id=?', [$course_id]);
          $levels_complete_count = 0;

          foreach ($levels as $level) {
              if (count($this->db()->select('level_complete', ['level_id'=>$level['level_id'],'user_id'=>$this->user['user_id']])) > 0) {
                  $levels_complete_count += 1;
              }
          }

          return ceil(($levels_complete_count/count($levels))*100);
      }

      //Get all the courses enrolled by this user
      public function get_courses()
      {
          $courses = array_map(fn ($e) =>new \App\Class\Course($e['course_id']), $this->db()->select("course_enrollments", ['user_id'=>$this->user['user_id']]));
          return $courses;
      }


      //Get all the solutions submitted by this user
      public function get_solutions()
      {
          if ($this->solutions == null) {
              $this->solutions = array_map(function ($s) {

                  //Get all the details about the solution
                  $solution = $this->db()->query('SELECT solutions.solution_id, solutions.timestamp, levels.level_id, levels.language, levels.level_slug, levels.title as level_title, levels.xp, chapters.chapter_slug, chapters.title as chapter_title, courses.course_slug, courses.title as course_title FROM solutions INNER JOIN levels ON solutions.level_id=levels.level_id INNER JOIN chapters ON levels.chapter_id=chapters.chapter_id INNER JOIN courses ON levels.course_id=courses.course_id WHERE solution_id=?', [$s['solution_id']])[0];

                  //Get alternative solutions, to calculate best solution
                  $altSolutionsRes = $this->db()->query('SELECT solutions.solution_id, vote FROM solutions INNER JOIN solution_votes ON solutions.solution_id=solution_votes.solution_id WHERE level_id=?',[$solution['level_id']]);

                  //Get badges for this solution
                  $badgesRes = $this->db()->query('SELECT * FROM solution_badges INNER JOIN available_badges ON solution_badges.badge_id=available_badges.badge_id WHERE solution_id=?', [$s['solution_id']]);

                  //Count votes for this solution
                  $upvotes = 0;
                  $downvotes = 0;

                  $altSolutions = [];
                  foreach ($altSolutionsRes as $as) {
                      if(!isset($altSolutions[$as['solution_id']])){
                        $altSolutions[$as['solution_id']] = 0;
                      }
                      if($as['solution_id'] == $solution['solution_id']){
                          if($as['vote'] == 1){
                            $upvotes += 1;
                          }
                          else{
                            $downvotes += 1;
                          }
                      }
                      $altSolutions[$as['solution_id']] += $as['vote'];
                  }
                  arsort($altSolutions);

                  //Is this solution the best solution for the level?
                  $solution['best_solution'] = count(array_keys($altSolutions))>0 && (array_keys($altSolutions)[0] == $solution['solution_id']) && (count(array_keys($altSolutions))==1 || $altSolutions[array_keys($altSolutions)[1]] < $altSolutions[array_keys($altSolutions)[0]]);

                  $solution['upvotes'] = $upvotes;
                  $solution['downvotes'] = $downvotes;

                  $badges = [];
                  foreach ($badgesRes as $b) {
                      if (!isset($badges[$b['badge_id']])) {
                          $badges[$b['badge_id']] = [
                            "badge_id"=>$b['badge_id'],
                            "icon"=>$b['icon'],
                            "name"=>$b['name'],
                            "votes"=>0
                          ];
                      }
                      $badges[$b['badge_id']]['votes'] += 1;
                  }

                  $solution['solution_badges'] = array_values($badges);

                  return $solution;

              }, $this->db()->query("SELECT * FROM solutions WHERE user_id=? ORDER BY timestamp DESC", [$this->user['user_id']]));
          }
          return $this->solutions;
      }

      //Get all the badges awarded to the user (across all submittd solutions)
      public function get_badges()
      {
          if ($this->badges == null) {
              $badges = [];
              foreach ($this->get_solutions() as $s) {
                  foreach ($s['solution_badges'] as $badge) {
                      if (!isset($badges[$badge['badge_id']])) {
                          $badges[$badge['badge_id']] = [
                            "badge_id"=>$badge['badge_id'],
                            "name"=>$badge['name'],
                            "icon"=>$badge['icon'],
                            "votes"=>0
                          ];
                      }
                      $badges[$badge['badge_id']]['votes'] += $badge['votes'];
                  }
              }
              $this->badges = array_values($badges);
          }
          return $this->badges;
      }
  }
