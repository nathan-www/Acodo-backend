<?php

namespace App\Database;

class Database
{
    protected $db;

    //Connect to database
    public function __construct()
    {
        $db = new \mysqli(getenv("MYSQL_HOST"), getenv("MYSQL_USERNAME"), getenv("MYSQL_PASSWORD"), getenv("MYSQL_DBNAME"), getenv("MYSQL_PORT"));
        if ($db->connect_error) {
            throw new \Exception("Could not connect to database");
        }
        $this->db = $db;
    }

    public function getDB()
    {
        return $this->db;
    }

    public function query($stmt, $values = [])
    {
        $rows = [];

        try {

            $stmt = $this->db->prepare($stmt);
            if (count($values) > 0) {
                //Prepare statement
                $stmt->bind_param(str_repeat("s", count($values)), ...$values);
            }

            $stmt->execute();
            $result = $stmt->get_result();

            if (!is_bool($result) && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
        } catch (Exception $e) {}

        return $rows;
    }

    public function insert($table, $data)
    {
        $stmt = 'INSERT INTO ' . $table . ' (' . implode(",", array_keys($data)) . ') VALUES (' . substr(str_repeat("?,", count($data)), 0, -1) . ') ';
        return $this->query($stmt, array_values($data));
    }

    public function update($table, $where, $data)
    {
        $stmt = 'UPDATE ' . $table . ' SET ' . implode("=?, ", array_keys($data)) . '=? WHERE ' . implode("=? AND ", array_keys($where)) . '=?';
        return $this->query($stmt, array_merge(array_values($data), array_values($where)));
    }

    public function select($table, $where)
    {
        $stmt = 'SELECT * FROM ' . $table . ' WHERE ' . implode("=? AND ", array_keys($where)) . '=?';
        return $this->query($stmt, array_values($where));
    }

    public function delete($table, $where)
    {
        $stmt = 'DELETE FROM ' . $table . ' WHERE ' . implode("=? AND ", array_keys($where)) . '=?';
        return $this->query($stmt, array_values($where));
    }

}
