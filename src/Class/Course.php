<?php

  namespace App\Class;

  class Course
  {
      public $course_id;
      private $db_row;
      public $course_exists;
      public $title;
      public $slug;
      public $description;
      public $thumbnail;
      private $languages;
      public $difficulty;
      private $authors;
      public $total_xp;
      public $duration_hours;
      private $enrollments;
      private $chapters;

      protected static function db()
      {
          return new \App\Database\Database();
      }

      public function __construct($course_id)
      {
          $course = $this->db()->select('courses',['course_id'=>$course_id]);
          if(count($course) < 1){
              $this->course_exists = false;
          }
          else{
              $this->course_exists = true;
              $this->db_row = $course[0];
              $this->course_id = $this->db_row['course_id'];
              $this->title = $this->db_row['title'];
              $this->slug = $this->db_row['course_slug'];
              $this->description = $this->db_row['description'];
              $this->thumbnail = $this->db_row['thumbnail'];
              $this->difficulty = $this->db_row['difficulty'];
              $this->total_xp = $this->db_row['total_xp'];
              $this->duration_hours = $this->db_row['duration_hours'];
          }
      }

      public function get_languages()
      {
        if($this->languages == null){
            $this->languages = array_map(fn ($e) => $e['language'], $this->db()->select('course_languages',['course_id'=>$this->course_id]));
        }
        return $this->languages;
      }

      public function get_authors()
      {
        if($this->authors == null){
            $this->authors = array_map(fn ($e) => (new \App\Class\User($e['user_id'])), $this->db()->select('course_authors',['course_id'=>$this->course_id]));
        }
        return $this->authors;
      }

      public function get_enrollments()
      {
        if($this->enrollments == null){
            $this->enrollments = array_map(fn ($e) => $e['user_id'], $this->db()->select('course_enrollments',['course_id'=>$this->course_id]));
        }
        return $this->enrollments;
      }

      public function get_chapters()
      {
        if($this->chapters == null){
            $this->chapters = array_map(fn ($e) => (new \App\Class\Chapter($e['chapter_id'])), $this->db()->select('chapters',['course_id'=>$this->course_id]));
        }
        return $this->chapters;
      }
  }
