<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

function set_logged($username, $role)
{
    session_set('ss_user_token', array(
        'username' => $username,
        'role' => $role
    ));
}

function set_logout()
{
    session_delete('ss_user_token');
}

function is_logged()
{
    $user = session_get('ss_user_token');
    return $user;
}

function is_admin()
{
    $user = is_logged();
    if (!empty($user['role']) && $user['role'] == 'admin') {
        return true;
    }
    return false;
}

function is_staff_cn()
{
    $user = is_logged();
    if (!empty($user['role']) && $user['role'] == 'staff_cn') {
        return true;
    }
    return false;
}

function is_staff_vn()
{
    $user = is_logged();
    if (!empty($user['role']) && $user['role'] == 'staff_vn') {
        return true;
    }
    return false;
}

function is_customer()
{
    $user = is_logged();
    if (!empty($user['role']) && $user['role'] == 'customer') {
        return true;
    }
    return false;
}

function has_role($roles)
{
    $user = is_logged();
    if (!empty($user['role']) && in_array($user['role'], $roles)) {
        return true;
    }
    return false;
}

function get_user_role()
{
    $user = is_logged();
    return !empty($user['role']) ? $user['role'] : false;
}
