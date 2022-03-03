<?php

  namespace App\Class;

  class Level
  {
      public $level_id;
      public $level_exists;
      public $db_row;
      public $title;
      public $slug;
      private $authors;
      private $completions;
      private $forfeited;
      public $difficulty;
      public $language;
      public $xp;
      private $brief;
      private $default_code;
      private $draft_users;
      private $test_code;
      private $unit_tests;
      public $feedback_test;
      private $solutions;

      protected static function db()
      {
          return new \App\Database\Database();
      }


      public function __construct($level_id)
      {
          $level = $this->db()->query("SELECT level_id,title,level_slug,difficulty,language,xp,feedback_test,course_id,chapter_id FROM levels WHERE level_id=?", [$level_id]);

          if (count($level) < 1) {
              $this->level_exists = false;
          } else {
              $this->level_exists = true;
              $this->db_row = $level[0];
              $this->level_id = $this->db_row['level_id'];
              $this->title = $this->db_row['title'];
              $this->slug = $this->db_row['level_slug'];
              $this->difficulty = $this->db_row['difficulty'];
              $this->language = $this->db_row['language'];
              $this->xp = $this->db_row['xp'];
              $this->feedback_test = $this->db_row['feedback_test'];
          }
      }

      public function get_path()
      {
        return $this->db()->query("SELECT levels.title AS level_title, chapters.title AS chapter_title, courses.title AS course_title, levels.level_slug, chapters.chapter_slug, courses.course_slug FROM levels INNER JOIN chapters ON levels.chapter_id = chapters.chapter_id INNER JOIN courses ON chapters.course_id = courses.course_id WHERE level_id=?", [$this->level_id])[0];
      }

      public function get_authors()
      {
          if ($this->authors == null) {
              $authorIDs = $this->db()->select('level_authors', ['level_id'=>$this->level_id]);
              $authors = [];
              foreach ($authorIDs as $author) {
                  $authors[] = new \App\Class\User($author['user_id']);
              }
              $this->authors = $authors;
          }
          return($this->authors);
      }

      public function get_completions()
      {
          if ($this->completions == null) {
              $this->completions = array_map(fn ($e) => $e['user_id'], $this->db()->select('level_complete', ['level_id'=>$this->level_id]));
          }
          return($this->completions);
      }

      public function get_forfeited()
      {
          if ($this->forfeited == null) {
              $this->forfeited = array_map(fn ($e) => $e['user_id'], $this->db()->select('level_forfeit', ['level_id'=>$this->level_id]));
          }
          return($this->forfeited);
      }

      public function get_brief()
      {
          if ($this->brief == null) {
              $this->brief = $this->db()->query('SELECT brief FROM levels WHERE level_id=?', [$this->level_id])[0]['brief'];
          }
          return($this->brief);
      }

      public function get_default_code()
      {
          if ($this->default_code == null) {
              $this->default_code = $this->db()->query('SELECT default_code FROM levels WHERE level_id=?', [$this->level_id])[0]['default_code'];
          }
          return($this->default_code);
      }

      public function get_test_code()
      {
          if ($this->test_code == null) {
              $this->test_code = $this->db()->query('SELECT test_code FROM levels WHERE level_id=?', [$this->level_id])[0]['test_code'];
          }
          return($this->test_code);
      }

      public function get_draft_users()
      {
          if ($this->draft_users == null) {
              $this->draft_users = array_map(fn ($e) => $e['user_id'], $this->db()->query('SELECT user_id FROM level_drafts WHERE level_id=?', [$this->level_id]));
          }
          return($this->draft_users);
      }

      public function get_user_draft($user_id)
      {
          $draft = $this->db()->select('level_drafts', ['level_id'=>$this->level_id,'user_id'=>$user_id]);
          if (count($draft) < 1) {
              return ["code"=>"","timestamp"=>0];
          } else {
              return $draft[0];
          }
      }

      public function set_user_draft($user_id, $code)
      {
          $this->db()->delete('level_drafts', ['level_id'=>$this->level_id,'user_id'=>$user_id]);
          $this->db()->insert('level_drafts', ['level_id'=>$this->level_id,'user_id'=>$user_id,'code'=>$code,'timestamp'=>time()]);
      }

      public function get_unit_tests()
      {
          if ($this->unit_tests == null) {
              $this->unit_tests = $this->db()->select('unit_tests', ['level_id'=>$this->level_id]);
          }
          return($this->unit_tests);
      }

      public function get_solutions()
      {
          if ($this->solutions == null) {
              $this->solutions = array_map(fn ($e) => (new \App\Class\Solution($e['solution_id'])), $this->db()->query('SELECT solution_id FROM solutions WHERE level_id=?', [$this->level_id]));
          }
          return($this->solutions);
      }

      public function get_messages($since=0)
      {
          return array_map(fn ($e) => (new \App\Class\Message($e['message_id'])), $this->db()->query('SELECT message_id FROM Messages WHERE changed_timestamp>? AND level_id=?', [$since,$this->level_id]));
      }
  }
