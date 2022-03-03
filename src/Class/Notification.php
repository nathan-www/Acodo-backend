<?php

  namespace App\Class;

  class Notification{

    protected static function db()
    {
        return new \App\Database\Database();
    }

    public static function create($user_id,$data)
    {
      self::db()->insert('notifications',[
        'notification_id' => rand(1000000000, 9999999999),
        'user_id' => $user_id,
        'timestamp' => time(),
        'has_read' => 'false',
        'notification_data' => json_encode($data)
      ]);
    }

    public static function unreadCount($user_id)
    {
      return count(self::db()->select('notifications',[
        "user_id" => $user_id,
        "has_read" => 'false'
      ]));
    }

    public static function list($user_id)
    {
      $notifications = self::db()->query('SELECT * FROM notifications WHERE user_id=? AND has_read="false" ORDER BY timestamp DESC', [$user_id]);
      $notifications = array_merge($notifications, self::db()->query('SELECT * FROM notifications WHERE user_id=? AND has_read="true" ORDER BY timestamp DESC LIMIT 20', [$user_id]));

      self::db()->update('notifications',[
        "user_id" => $user_id,
        "has_read" => 'false'
      ],[
        "has_read" => 'true'
      ]);

      return array_map(function($n){
          return [
            'notification_id' => $n['notification_id'],
            'timestamp' => $n['timestamp'],
            'has_read' => $n['has_read'] == 'true',
            'notification_data' => json_decode($n['notification_data'])
          ];
      },$notifications);

    }

  }
