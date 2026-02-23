<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

function setLanguage($id)
{
    global $CMSNT;
    if ($row = $CMSNT->get_row_safe("SELECT * FROM `languages` WHERE `id` = ? AND `status` = 1", [$id])) {
        $isSet = setcookie('language', $row['lang'], time() + (31536000 * 30), "/");
        return $isSet ? true : false;
    }
    return false;
}

function getLanguage()
{
    global $CMSNT;
    if (isset($_COOKIE['language'])) {
        $language = check_string($_COOKIE['language']);
        $rowLang = $CMSNT->get_row_safe("SELECT * FROM `languages` WHERE `lang` = ? AND `status` = 1", [$language]);
        if ($rowLang) {
            return $rowLang['lang'];
        }
    }
    $rowLang = $CMSNT->get_row_safe("SELECT * FROM `languages` WHERE `lang_default` = 1", []);
    if ($rowLang) {
        return $rowLang['lang'];
    }
    return 'vi';
}

function __($name)
{
    global $CMSNT;
    if (isset($_COOKIE['language'])) {
        $language = check_string($_COOKIE['language']);
        $rowLang = $CMSNT->get_row_safe("SELECT * FROM `languages` WHERE `lang` = ? AND `status` = 1", [$language]);
        if ($rowLang) {
            $rowTran = $CMSNT->get_row_safe("SELECT * FROM `translate` WHERE `lang_id` = ? AND `name` = ?", [$rowLang['id'], $name]);
            if ($rowTran) {
                return $rowTran['value'];
            }
        }
    }
    $rowLang = $CMSNT->get_row_safe("SELECT * FROM `languages` WHERE `lang_default` = 1", []);
    if ($rowLang) {
        $rowTran = $CMSNT->get_row_safe("SELECT * FROM `translate` WHERE `lang_id` = ? AND `name` = ?", [$rowLang['id'], $name]);
        if ($rowTran) {
            return $rowTran['value'];
        }
    }
    return $name;
}
