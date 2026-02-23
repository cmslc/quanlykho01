<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
include_once(__DIR__.'/../vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();
session_start();

class DB
{
    public $ketnoi = null;

    public function connect()
    {
        if (!$this->ketnoi) {
            $this->ketnoi = mysqli_connect(
                $_ENV['DB_HOST'],
                $_ENV['DB_USERNAME'],
                $_ENV['DB_PASSWORD'],
                $_ENV['DB_DATABASE']
            ) or die('Database connection failed. Please try again later.');

            mysqli_set_charset($this->ketnoi, "utf8mb4");
            mysqli_query($this->ketnoi, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $offset = date('P');
            mysqli_query($this->ketnoi, "SET time_zone = '$offset'");
        }
    }

    public function dis_connect()
    {
        if ($this->ketnoi) {
            mysqli_close($this->ketnoi);
        }
    }

    public function site($data)
    {
        $this->connect();
        $stmt = mysqli_prepare($this->ketnoi, "SELECT `value` FROM `settings` WHERE `name` = ?");
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 's', $data);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        return $row ? $row['value'] : null;
    }

    public function query($sql)
    {
        $this->connect();
        $row = $this->ketnoi->query($sql);
        return $row;
    }

    // ===== TRANSACTION FUNCTIONS =====

    public function beginTransaction()
    {
        $this->connect();
        mysqli_begin_transaction($this->ketnoi);
    }

    public function commit()
    {
        if ($this->ketnoi) {
            mysqli_commit($this->ketnoi);
        }
    }

    public function rollBack()
    {
        if ($this->ketnoi) {
            mysqli_rollback($this->ketnoi);
        }
    }

    public function affected_rows()
    {
        return $this->ketnoi ? mysqli_affected_rows($this->ketnoi) : 0;
    }

    public function insert_id()
    {
        return $this->ketnoi ? mysqli_insert_id($this->ketnoi) : 0;
    }

    public function escape($string)
    {
        $this->connect();
        return mysqli_real_escape_string($this->ketnoi, $string);
    }

    public function get_row_safe($sql, $params = [])
    {
        $this->connect();
        $stmt = mysqli_prepare($this->ketnoi, $sql);
        if (!$stmt) {
            die('Prepare failed: ' . mysqli_error($this->ketnoi));
        }
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            mysqli_stmt_close($stmt);
            return false;
        }
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row ? $row : false;
    }

    public function get_list_safe($sql, $params = [])
    {
        $this->connect();
        $stmt = mysqli_prepare($this->ketnoi, $sql);
        if (!$stmt) {
            die('Prepare failed: ' . mysqli_error($this->ketnoi));
        }
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            mysqli_stmt_close($stmt);
            return [];
        }
        $return = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $return[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $return;
    }

    public function num_rows_safe($sql, $params = [])
    {
        $this->connect();
        $stmt = mysqli_prepare($this->ketnoi, $sql);
        if (!$stmt) {
            return false;
        }
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            mysqli_stmt_close($stmt);
            return false;
        }
        $count = mysqli_num_rows($result);
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $count ? $count : false;
    }

    public function insert_safe($table, $data)
    {
        $this->connect();
        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $values = array_values($data);
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES ($placeholders)";
        $stmt = mysqli_prepare($this->ketnoi, $sql);
        if (!$stmt) {
            return false;
        }
        $types = str_repeat('s', count($values));
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    public function update_safe($table, $data, $where_sql, $where_params = [])
    {
        $this->connect();
        $set_parts = [];
        $params = [];
        foreach ($data as $key => $value) {
            $set_parts[] = "`$key` = ?";
            $params[] = $value;
        }
        $params = array_merge($params, $where_params);
        $sql = "UPDATE `$table` SET " . implode(', ', $set_parts) . " WHERE " . $where_sql;
        $stmt = mysqli_prepare($this->ketnoi, $sql);
        if (!$stmt) {
            return false;
        }
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    public function update_value_safe($table, $data, $where_sql, $where_params = [], $limit = 1)
    {
        $this->connect();
        $set_parts = [];
        $params = [];
        foreach ($data as $key => $value) {
            $set_parts[] = "`$key` = ?";
            $params[] = $value;
        }
        $params = array_merge($params, $where_params);
        $sql = "UPDATE `$table` SET " . implode(', ', $set_parts) . " WHERE " . $where_sql . " LIMIT " . (int)$limit;
        $stmt = mysqli_prepare($this->ketnoi, $sql);
        if (!$stmt) {
            return false;
        }
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    public function remove_safe($table, $where_sql, $where_params = [])
    {
        $this->connect();
        $sql = "DELETE FROM `$table` WHERE " . $where_sql;
        $stmt = mysqli_prepare($this->ketnoi, $sql);
        if (!$stmt) {
            return false;
        }
        if (!empty($where_params)) {
            $types = str_repeat('s', count($where_params));
            mysqli_stmt_bind_param($stmt, $types, ...$where_params);
        }
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    public function cong_safe($table, $column, $amount, $where_sql, $where_params = [])
    {
        $this->connect();
        $sql = "UPDATE `$table` SET `$column` = `$column` + ? WHERE " . $where_sql;
        $params = array_merge([$amount], $where_params);
        $stmt = mysqli_prepare($this->ketnoi, $sql);
        if (!$stmt) {
            return false;
        }
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }

    public function tru_safe($table, $column, $amount, $where_sql, $where_params = [])
    {
        $this->connect();
        $sql = "UPDATE `$table` SET `$column` = `$column` - ? WHERE " . $where_sql;
        $params = array_merge([$amount], $where_params);
        $stmt = mysqli_prepare($this->ketnoi, $sql);
        if (!$stmt) {
            return false;
        }
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
}
