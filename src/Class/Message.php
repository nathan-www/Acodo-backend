<?php


  namespace App\Class;

  class Message
  {
      public $message_id;
      public $message_exists;
      private $db_row;
      public $user;
      public $message_content;
      public $edited;
      public $last_edited;
      public $created;
      private $votes;
      private $tags;
      public $reply_to;


      protected static function db()
      {
          return new \App\Database\Database();
      }


      public function __construct($message_id)
      {
          $message = $this->db()->select('messages', ['message_id'=>$message_id]);
          if (count($message) < 1) {
              $this->message_exists = false;
          } else {
              $this->message_exists = true;
              $this->db_row = $message[0];
              $this->user = new \App\Class\User($this->db_row['user_id']);
              $this->message_id = $this->db_row['message_id'];
              $this->message_content = $this->db_row['message_content'];
              $this->last_edited = $this->db_row['edited_timestamp'];
              $this->created = $this->db_row['sent_timestamp'];
              $this->last_change = $this->db_row['changed_timestamp'];
              $this->reply_to = $this->db_row['reply_to'];
          }
      }

      public function get_votes()
      {
          if ($this->votes == null) {
              $this->votes = $this->db()->select('message_votes', ['message_id'=>$this->message_id]);
          }

          return $this->votes;
      }

      public function get_tags()
      {
          if ($this->tags == null) {
              $this->tags = $this->db()->select('message_tags', ['message_tags'], ['message_id'=>$this->message_id]);
          }

          return $this->tags;
      }

      public function edit($newContent)
      {
          //Edit message content, plus set 'edited' to true and updated edited timestamp
          $this->db()->update('messages', ['message_id'=>$this->message_id], [
            "message_content"=>$newContent,
            "edited_timestamp"=>time(),
            "changed_timestamp"=>time()
          ]);
      }

      public function vote($user_id, $vote)
      {

        //Delete any previous vote
          $this->db()->delete('message_votes', ['message_id'=>$this->message_id,'user_id'=>$user_id]);

          if ($vote == 1 || $vote == -1) {
              $this->db()->insert('message_votes', ['message_id'=>$this->message_id,'user_id'=>$user_id,'vote'=>$vote]);
          }

          $this->db()->update('messages', ['message_id'=>$this->message_id], [
              "changed_timestamp"=>time()
          ]);
      }

      public function delete()
      {
          $this->db()->delete('messages', ['message_id'=>$this->message_id]);
          $this->db()->delete('messages', ['reply_to'=>$this->message_id]);
          $this->db()->delete('message_votes', ['message_id'=>$this->message_id]);
          $this->db()->delete('message_tags', ['message_id'=>$this->message_id]);
      }


      public static function send($user_id, $level_id, $message_content, $tags, $reply_to="")
      {
          $message_id = rand(1000000000, 9999999999);

          $sender = new \App\Class\User($user_id);
          $subtitle = strip_tags(base64_decode(base64_decode($message_content)));
          $level_path = (new \App\Class\Level($level_id))->get_path();

          if(strlen($subtitle) > 95){
              $subtitle = substr($subtitle,0,95) . "...";
          }

          $currentTime = time();

          self::db()->insert('messages', [
            'message_id'=>$message_id,
            'level_id'=>$level_id,
            'user_id'=>$user_id,
            'message_content'=>$message_content,
            'sent_timestamp'=>$currentTime,
            'edited_timestamp'=>$currentTime,
            'changed_timestamp'=>$currentTime,
            'reply_to'=>$reply_to
          ]);

          foreach ($tags as $tag) {
              if ((new \App\Class\User($tag))->userExists == true) {

                  \App\Class\Notification::create($tag,[
                    "type"=>"reply",
                    "title"=>"<b>" . $sender->basicInfo()['username'] . "</b> replied to your message",
                    "subtitle"=>$subtitle,
                    "link_text"=>$level_path['course_title'].">".$level_path['level_title'],
                    "link_url"=>"/courses/".$level_path['course_slug']."/".$level_path['chapter_slug']."/".$level_path['level_slug']
                  ]);

                  self::db()->insert('message_tags', [
                    "message_id"=>$message_id,
                    "user_id"=>$tag
                  ]);
              }
          }
      }
  }
