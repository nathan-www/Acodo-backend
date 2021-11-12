<?php


  namespace App\Class;

  class Solution
  {
      public $solution_id;
      public $solution_exists;
      private $db_row;
      public $user;
      public $timestamp;
      public $code;
      private $votes;
      private $badges;

      protected static function db()
      {
          return new \App\Database\Database();
      }

      public function __construct($solution_id)
      {
          $solution = $this->db()->select('solutions', ['solution_id'=>$solution_id]);
          if (count($solution) < 1) {
              $this->solution_exists = false;
          } else {
              $this->solution_exists = true;
              $this->db_row = $solution[0];
              $this->solution_id = $this->db_row['solution_id'];
              $this->timestamp = $this->db_row['timestamp'];
              $this->code = $this->db_row['code'];
              $this->user = new \App\Class\User($this->db_row['user_id']);
          }
      }

      public function get_votes()
      {
          if ($this->votes == null) {
              $voteResults = $this->db()->select('solution_votes', ['solution_id'=>$this->solution_id]);
              $votes = [];
              foreach ($voteResults as $v) {
                  $votes[] = [
                      "user" => $v['user_id'],
                      "vote" => $v['vote']
                    ];
              }
              $this->votes = $votes;
          }

          return $this->votes;
      }

      public function get_badges()
      {
          if ($this->badges == null) {
              $availableBadges = $this->db()->query('SELECT * FROM available_badges');
              $badges = [];
              foreach ($availableBadges as $b) {
                  $badges[] = [
                  "badge_id" => $b['badge_id'],
                  "icon" => $b['icon'],
                  "name" => $b['name'],
                  "votes" => []
                ];
              }

              $solutionBadges = $this->db()->select('solution_badges', ["solution_id" => $this->solution_id]);

              foreach ($solutionBadges as $b) {
                  $badges[array_search($b['badge_id'], $badges)]['votes'][] = [
                  "user" => new \App\Class\User($b['user_id'])
                ];
              }

              $this->badges = $badges;
          }

          return $this->badges;
      }

      public function vote_badge($user_id, $badge_id, $vote)
      {
          $badge_available = false;
          foreach ($this->get_badges() as $b) {
              if ($b['badge_id'] == $badge_id) {
                  $badge_available = true;
              }
          }

          if ($badge_available) {
              //Delete any existing votes for this badge by user
              $this->db()->delete('solution_badges', ["solution_id"=>$this->solution_id,"user_id"=>$user_id]);

              if ($vote) {
                  //If voting for, insert new vote into database
                  $this->db()->insert('solution_badges', ["solution_id"=>$this->solution_id,"user_id"=>$user_id,"badge_id"=>$badge_id]);
              }
          }
      }


      public function vote_solution($user_id, $vote)
      {
          $this->db()->delete('solution_votes', ['solution_id'=>$this->solution_id,'user_id'=>$user_id]);

          if ($vote == 1 || $vote == -1) {
              $this->db()->insert('solution_votes', ['solution_id'=>$this->solution_id,'user_id'=>$user_id,'vote'=>$vote]);
          }
      }

      public static function submit($level_id, $user_id, $code)
      {
          $solution_id = rand(1000000000, 9999999999);

          //Delete previous solution submissions
          self::db()->delete('solutions', ['level_id'=>$level_id,'user_id'=>$user_id]);

          self::db()-insert('solutions', ['level_id'=>$level_id,'user_id'=>$user_id,'solution_id'=>$solution_id,'timestamp'=>time(),'code'=>$code]);
      }
  }
