<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

class Users extends DB
{
    public function Banned($user_id, $reason = '')
    {
        $this->update_safe('users', [
            'banned' => 1
        ], "`id` = ?", [$user_id]);

        add_log($user_id, 'BANNED', $reason);
    }

    public function Unban($user_id)
    {
        $this->update_safe('users', [
            'banned' => 0
        ], "`id` = ?", [$user_id]);

        add_log($user_id, 'UNBANNED', 'Account unbanned');
    }
}
