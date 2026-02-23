<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

class Csrf
{
    private $_csrf_token_name = 'csrf_token';
    private $_csrf_value = '';

    public function __construct($use_token = true, $token_post = false, $token_get = false)
    {
        if (!$use_token) {
            return;
        }
        $this->__create_csrf_token();
        if ($token_post && !$this->__validate_post()) {
            die('Invalid token');
        }
        if ($token_get && !$this->__validate_get()) {
            die('Invalid token');
        }
    }

    private function __create_csrf_token()
    {
        if (empty($_SESSION[$this->_csrf_token_name])) {
            $_SESSION[$this->_csrf_token_name] = bin2hex(random_bytes(32));
        }
        $this->_csrf_value = $_SESSION[$this->_csrf_token_name];
    }

    private function __validate_post()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (!isset($_POST[$this->_csrf_token_name]) || empty($_SESSION[$this->_csrf_token_name])) {
                return false;
            }
            if (!hash_equals($_SESSION[$this->_csrf_token_name], $_POST[$this->_csrf_token_name])) {
                return false;
            }
        }
        return true;
    }

    private function __validate_get()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            if (!isset($_GET[$this->_csrf_token_name]) || empty($_SESSION[$this->_csrf_token_name])) {
                return false;
            }
            if (!hash_equals($_SESSION[$this->_csrf_token_name], $_GET[$this->_csrf_token_name])) {
                return false;
            }
        }
        return true;
    }

    public function get_token_name()
    {
        return $this->_csrf_token_name;
    }

    public function get_token_value()
    {
        return $this->_csrf_value;
    }

    public function validate()
    {
        return $this->__validate_post();
    }

    public function create_link($url)
    {
        return $url . '?' . $this->_csrf_token_name . '=' . $this->_csrf_value;
    }

    public function regenerate()
    {
        $_SESSION[$this->_csrf_token_name] = bin2hex(random_bytes(32));
        $this->_csrf_value = $_SESSION[$this->_csrf_token_name];
    }
}
