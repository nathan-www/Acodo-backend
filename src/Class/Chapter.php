<?php

  namespace App\Class;

  class Chapter
  {
      public $chapter_id;
      private $db_row;
      public $chapter_exists;
      public $slug;
      public $title;
      public $description;
      private $levels;


      protected static function db()
      {
          return new \App\Database\Database();
      }

      public function __construct($chapter_id)
      {
          $chapter = $this->db()->select('chapters', ['chapter_id'=>$chapter_id]);
          if (count($chapter) < 1) {
              $this->chapter_exists = false;
          } else {
              $this->chapter_exists = true;
              $this->db_row = $chapter[0];
              $this->chapter_id = $this->db_row['chapter_id'];
              $this->slug = $this->db_row['chapter_slug'];
              $this->title = $this->db_row['title'];
              $this->description = $this->db_row['description'];
          }
      }

      public function get_levels()
      {
          if ($this->levels == null) {
              $this->levels = array_map(fn ($e) => (new \App\Class\Level($e['level_id'])),$this->db()->query('SELECT level_id FROM levels WHERE chapter_id=?',[$this->chapter_id]));          
          }
          return $this->levels;
      }
  }
