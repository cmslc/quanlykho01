<?php
define('IN_SITE', true);
require_once(__DIR__.'/../../libs/db.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../libs/lang.php');
require_once(__DIR__.'/../../libs/helper.php');
require_once(__DIR__.'/../../libs/session.php');
require_once(__DIR__.'/../../libs/role.php');
require_once(__DIR__.'/../../libs/csrf.php');
require_once(__DIR__.'/../../models/is_admin.php');

header('Content-Type: application/json; charset=utf-8');

$csrf = new Csrf();
if (!$csrf->validate()) {
    echo json_encode(['status' => 'error', 'msg' => 'Token không hợp lệ']);
    exit;
}

$request = input_post('request_name');

// ======== ADD LANGUAGE ========
if ($request === 'add_language') {
    $name = trim(input_post('name') ?? '');
    $lang = trim(input_post('lang') ?? '');
    $status = intval(input_post('status') ?? 1);

    if (!$name || !$lang) {
        echo json_encode(['status' => 'error', 'msg' => 'Vui lòng nhập đầy đủ thông tin']);
        exit;
    }

    // Check duplicate lang code
    $existing = $CMSNT->get_row_safe("SELECT * FROM `languages` WHERE `lang` = ?", [$lang]);
    if ($existing) {
        echo json_encode(['status' => 'error', 'msg' => 'Mã ngôn ngữ "' . $lang . '" đã tồn tại']);
        exit;
    }

    $CMSNT->insert_safe('languages', [
        'name' => $name,
        'lang' => $lang,
        'lang_default' => 0,
        'status' => $status
    ]);

    echo json_encode(['status' => 'success', 'msg' => 'Đã thêm ngôn ngữ "' . $name . '"']);
    exit;
}

// ======== EDIT LANGUAGE ========
if ($request === 'edit_language') {
    $id = intval(input_post('id'));
    $name = trim(input_post('name') ?? '');
    $status = intval(input_post('status') ?? 1);

    if (!$id || !$name) {
        echo json_encode(['status' => 'error', 'msg' => 'Thiếu thông tin']);
        exit;
    }

    $lang = $CMSNT->get_row_safe("SELECT * FROM `languages` WHERE `id` = ?", [$id]);
    if (!$lang) {
        echo json_encode(['status' => 'error', 'msg' => 'Ngôn ngữ không tồn tại']);
        exit;
    }

    $CMSNT->update_safe('languages', ['name' => $name, 'status' => $status], 'id = ?', [$id]);

    echo json_encode(['status' => 'success', 'msg' => 'Đã cập nhật ngôn ngữ']);
    exit;
}

// ======== DELETE LANGUAGE ========
if ($request === 'delete_language') {
    $id = intval(input_post('id'));
    if (!$id) {
        echo json_encode(['status' => 'error', 'msg' => 'Thiếu ID']);
        exit;
    }

    $lang = $CMSNT->get_row_safe("SELECT * FROM `languages` WHERE `id` = ?", [$id]);
    if (!$lang) {
        echo json_encode(['status' => 'error', 'msg' => 'Ngôn ngữ không tồn tại']);
        exit;
    }

    if ($lang['lang_default']) {
        echo json_encode(['status' => 'error', 'msg' => 'Không thể xóa ngôn ngữ mặc định']);
        exit;
    }

    // Delete all translations for this language
    $CMSNT->delete_safe('translate', 'lang_id = ?', [$id]);
    // Delete the language
    $CMSNT->delete_safe('languages', 'id = ?', [$id]);

    echo json_encode(['status' => 'success', 'msg' => 'Đã xóa ngôn ngữ "' . $lang['name'] . '" và tất cả bản dịch']);
    exit;
}

// ======== SET DEFAULT LANGUAGE ========
if ($request === 'set_default') {
    $id = intval(input_post('id'));
    if (!$id) {
        echo json_encode(['status' => 'error', 'msg' => 'Thiếu ID']);
        exit;
    }

    $lang = $CMSNT->get_row_safe("SELECT * FROM `languages` WHERE `id` = ?", [$id]);
    if (!$lang) {
        echo json_encode(['status' => 'error', 'msg' => 'Ngôn ngữ không tồn tại']);
        exit;
    }

    // Remove default from all
    $CMSNT->update_safe('languages', ['lang_default' => 0], '1 = 1', []);
    // Set new default
    $CMSNT->update_safe('languages', ['lang_default' => 1], 'id = ?', [$id]);

    echo json_encode(['status' => 'success', 'msg' => 'Đã đặt "' . $lang['name'] . '" làm mặc định']);
    exit;
}

// ======== SCAN KEYS FROM SOURCE CODE ========
if ($request === 'scan_keys') {
    $baseDir = realpath(__DIR__ . '/../../');
    $scanDirs = [
        $baseDir . '/resources/views/admin/*.php',
        $baseDir . '/resources/views/customer/*.php',
        $baseDir . '/resources/views/staff_cn/*.php',
        $baseDir . '/resources/views/staff_vn/*.php',
        $baseDir . '/resources/views/common/*.php',
    ];

    $keys = [];
    foreach ($scanDirs as $pattern) {
        $files = glob($pattern);
        if (!$files) continue;
        foreach ($files as $file) {
            $content = file_get_contents($file);
            preg_match_all("/__\\(['\"](.+?)['\"]\\)/", $content, $matches);
            foreach ($matches[1] as $key) {
                $keys[$key] = true;
            }
        }
    }

    $allKeys = array_keys($keys);
    sort($allKeys);

    // Get existing keys in translate table
    $existing = $CMSNT->get_list_safe("SELECT DISTINCT `name` FROM `translate`", []);
    $existingKeys = array_column($existing, 'name');

    $newKeys = array_diff($allKeys, $existingKeys);

    echo json_encode([
        'status' => 'success',
        'total_scanned' => count($allKeys),
        'new_keys' => count($newKeys),
        'keys' => $allKeys,
        'msg' => 'Đã quét ' . count($allKeys) . ' key, ' . count($newKeys) . ' key mới'
    ]);
    exit;
}

// ======== SAVE TRANSLATION (single) ========
if ($request === 'save_translation') {
    $langId = intval(input_post('lang_id'));
    $name = trim(input_post('name') ?? '');
    $value = trim(input_post('value') ?? '');

    if (!$langId || !$name) {
        echo json_encode(['status' => 'error', 'msg' => 'Thiếu thông tin']);
        exit;
    }

    // Check language exists
    $lang = $CMSNT->get_row_safe("SELECT * FROM `languages` WHERE `id` = ?", [$langId]);
    if (!$lang) {
        echo json_encode(['status' => 'error', 'msg' => 'Ngôn ngữ không tồn tại']);
        exit;
    }

    $existing = $CMSNT->get_row_safe("SELECT * FROM `translate` WHERE `lang_id` = ? AND `name` = ?", [$langId, $name]);

    if ($value === '') {
        // Delete if empty
        if ($existing) {
            $CMSNT->delete_safe('translate', 'id = ?', [$existing['id']]);
        }
    } else {
        if ($existing) {
            $CMSNT->update_safe('translate', ['value' => $value], 'id = ?', [$existing['id']]);
        } else {
            $CMSNT->insert_safe('translate', ['lang_id' => $langId, 'name' => $name, 'value' => $value]);
        }
    }

    echo json_encode(['status' => 'success', 'msg' => 'Đã lưu']);
    exit;
}

// ======== SAVE BULK ========
if ($request === 'save_bulk') {
    $data = json_decode(input_post('translations') ?? '[]', true);
    if (!is_array($data) || empty($data)) {
        echo json_encode(['status' => 'error', 'msg' => 'Không có dữ liệu']);
        exit;
    }

    $saved = 0;
    $deleted = 0;
    foreach ($data as $item) {
        $langId = intval($item['lang_id'] ?? 0);
        $name = trim($item['name'] ?? '');
        $value = trim($item['value'] ?? '');

        if (!$langId || !$name) continue;

        $existing = $CMSNT->get_row_safe("SELECT * FROM `translate` WHERE `lang_id` = ? AND `name` = ?", [$langId, $name]);

        if ($value === '') {
            if ($existing) {
                $CMSNT->delete_safe('translate', 'id = ?', [$existing['id']]);
                $deleted++;
            }
        } else {
            if ($existing) {
                if ($existing['value'] !== $value) {
                    $CMSNT->update_safe('translate', ['value' => $value], 'id = ?', [$existing['id']]);
                    $saved++;
                }
            } else {
                $CMSNT->insert_safe('translate', ['lang_id' => $langId, 'name' => $name, 'value' => $value]);
                $saved++;
            }
        }
    }

    echo json_encode(['status' => 'success', 'msg' => 'Đã lưu ' . $saved . ' bản dịch' . ($deleted ? ', xóa ' . $deleted : '')]);
    exit;
}

// ======== DELETE TRANSLATION ========
if ($request === 'delete_translation') {
    $id = intval(input_post('id'));
    if (!$id) {
        echo json_encode(['status' => 'error', 'msg' => 'Thiếu ID']);
        exit;
    }
    $CMSNT->delete_safe('translate', 'id = ?', [$id]);
    echo json_encode(['status' => 'success', 'msg' => 'Đã xóa']);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid request']);
