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
$adminId = $_SESSION['user']['id'] ?? 0;

// ======== TẠO BẢNG LƯƠNG THÁNG ========
if ($request === 'generate_monthly') {
    $month = intval(input_post('month'));
    $year = intval(input_post('year'));

    if ($month < 1 || $month > 12 || $year < 2020 || $year > 2099) {
        echo json_encode(['status' => 'error', 'msg' => __('Tháng/năm không hợp lệ')]);
        exit;
    }

    // Lấy tất cả NV active (staff_cn, staff_vn)
    $staffList = $ToryHub->get_list_safe(
        "SELECT id, role FROM `users` WHERE `role` IN ('staff_cn','staff_vn') AND `active` = 1 AND `banned` = 0",
        []
    );

    if (empty($staffList)) {
        echo json_encode(['status' => 'error', 'msg' => __('Không có nhân viên nào')]);
        exit;
    }

    // Lấy danh sách NV đã có record tháng này
    $existing = $ToryHub->get_list_safe(
        "SELECT `user_id` FROM `salaries` WHERE `month` = ? AND `year` = ?",
        [$month, $year]
    );
    $existingIds = array_column($existing, 'user_id');

    // Lấy base_salary từ tháng trước cho mỗi NV
    $prevMonth = $month == 1 ? 12 : $month - 1;
    $prevYear = $month == 1 ? $year - 1 : $year;
    $prevSalaries = $ToryHub->get_list_safe(
        "SELECT `user_id`, `base_salary`, `allowance` FROM `salaries` WHERE `month` = ? AND `year` = ?",
        [$prevMonth, $prevYear]
    );
    $prevMap = [];
    foreach ($prevSalaries as $ps) {
        $prevMap[$ps['user_id']] = $ps;
    }

    $created = 0;
    $ToryHub->beginTransaction();
    try {
        foreach ($staffList as $staff) {
            if (in_array($staff['id'], $existingIds)) continue;

            $currency = $staff['role'] === 'staff_cn' ? 'CNY' : 'VND';
            $baseSalary = isset($prevMap[$staff['id']]) ? $prevMap[$staff['id']]['base_salary'] : 0;
            $allowance = isset($prevMap[$staff['id']]) ? $prevMap[$staff['id']]['allowance'] : 0;
            $netSalary = $baseSalary + $allowance;

            $ToryHub->insert_safe('salaries', [
                'user_id' => $staff['id'],
                'month' => $month,
                'year' => $year,
                'currency' => $currency,
                'base_salary' => $baseSalary,
                'allowance' => $allowance,
                'net_salary' => $netSalary,
                'status' => 'draft',
                'created_by' => $adminId,
            ]);
            $created++;
        }
        $ToryHub->commit();
    } catch (Exception $e) {
        $ToryHub->rollBack();
        echo json_encode(['status' => 'error', 'msg' => __('Lỗi hệ thống')]);
        exit;
    }

    if ($created === 0) {
        echo json_encode(['status' => 'info', 'msg' => __('Tất cả nhân viên đã có bảng lương tháng này')]);
        exit;
    }

    add_log($adminId, 'salary_generate', "Tạo bảng lương tháng $month/$year cho $created nhân viên");
    echo json_encode(['status' => 'success', 'msg' => __('Đã tạo bảng lương cho') . " $created " . __('nhân viên')]);
    exit;
}

// ======== CẬP NHẬT LƯƠNG ========
if ($request === 'update') {
    $id = intval(input_post('id'));
    $baseSalary = floatval(input_post('base_salary'));
    $allowance = floatval(input_post('allowance'));
    $bonus = floatval(input_post('bonus'));
    $deduction = floatval(input_post('deduction'));
    $workDays = input_post('work_days') !== '' ? intval(input_post('work_days')) : null;
    $note = trim(input_post('note') ?? '');

    $salary = $ToryHub->get_row_safe("SELECT * FROM `salaries` WHERE `id` = ?", [$id]);
    if (!$salary) {
        echo json_encode(['status' => 'error', 'msg' => __('Bản ghi không tồn tại')]);
        exit;
    }

    if ($salary['status'] === 'paid') {
        echo json_encode(['status' => 'error', 'msg' => __('Không thể sửa bản ghi đã thanh toán')]);
        exit;
    }

    $netSalary = $baseSalary + $allowance + $bonus - $deduction;

    $ToryHub->update_safe('salaries', [
        'base_salary' => $baseSalary,
        'allowance' => $allowance,
        'bonus' => $bonus,
        'deduction' => $deduction,
        'net_salary' => $netSalary,
        'work_days' => $workDays,
        'note' => $note,
    ], '`id` = ?', [$id]);

    add_log($adminId, 'salary_update', "Cập nhật lương #$id, thực nhận: $netSalary");
    echo json_encode(['status' => 'success', 'msg' => __('Đã cập nhật lương')]);
    exit;
}

// ======== XÁC NHẬN ========
if ($request === 'confirm') {
    $id = intval(input_post('id'));
    $salary = $ToryHub->get_row_safe("SELECT * FROM `salaries` WHERE `id` = ?", [$id]);
    if (!$salary) {
        echo json_encode(['status' => 'error', 'msg' => __('Bản ghi không tồn tại')]);
        exit;
    }
    if ($salary['status'] !== 'draft') {
        echo json_encode(['status' => 'error', 'msg' => __('Chỉ có thể xác nhận bản ghi nháp')]);
        exit;
    }

    $ToryHub->update_safe('salaries', ['status' => 'confirmed'], '`id` = ?', [$id]);
    add_log($adminId, 'salary_confirm', "Xác nhận lương #$id");
    echo json_encode(['status' => 'success', 'msg' => __('Đã xác nhận')]);
    exit;
}

// ======== ĐÁNH DẤU ĐÃ TRẢ ========
if ($request === 'mark_paid') {
    $id = intval(input_post('id'));
    $salary = $ToryHub->get_row_safe("SELECT * FROM `salaries` WHERE `id` = ?", [$id]);
    if (!$salary) {
        echo json_encode(['status' => 'error', 'msg' => __('Bản ghi không tồn tại')]);
        exit;
    }
    if ($salary['status'] === 'paid') {
        echo json_encode(['status' => 'error', 'msg' => __('Đã thanh toán rồi')]);
        exit;
    }

    $ToryHub->update_safe('salaries', ['status' => 'paid', 'paid_date' => gettime()], '`id` = ?', [$id]);
    add_log($adminId, 'salary_paid', "Đánh dấu đã trả lương #$id");
    echo json_encode(['status' => 'success', 'msg' => __('Đã đánh dấu thanh toán')]);
    exit;
}

// ======== XÓA (chỉ draft) ========
if ($request === 'delete') {
    $id = intval(input_post('id'));
    $salary = $ToryHub->get_row_safe("SELECT * FROM `salaries` WHERE `id` = ?", [$id]);
    if (!$salary) {
        echo json_encode(['status' => 'error', 'msg' => __('Bản ghi không tồn tại')]);
        exit;
    }
    if ($salary['status'] !== 'draft') {
        echo json_encode(['status' => 'error', 'msg' => __('Chỉ có thể xóa bản ghi nháp')]);
        exit;
    }

    $ToryHub->remove_safe('salaries', '`id` = ?', [$id]);
    add_log($adminId, 'salary_delete', "Xóa lương #$id");
    echo json_encode(['status' => 'success', 'msg' => __('Đã xóa')]);
    exit;
}

// ======== XÁC NHẬN HÀNG LOẠT ========
if ($request === 'bulk_confirm') {
    $ids = array_filter(array_map('intval', explode(',', input_post('ids') ?? '')));
    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'msg' => __('Chưa chọn bản ghi nào')]);
        exit;
    }

    $ph = implode(',', array_fill(0, count($ids), '?'));
    $ToryHub->query_safe("UPDATE `salaries` SET `status` = 'confirmed' WHERE `id` IN ($ph) AND `status` = 'draft'", $ids);
    add_log($adminId, 'salary_bulk_confirm', "Xác nhận hàng loạt: " . implode(',', $ids));
    echo json_encode(['status' => 'success', 'msg' => __('Đã xác nhận hàng loạt')]);
    exit;
}

// ======== THANH TOÁN HÀNG LOẠT ========
if ($request === 'bulk_paid') {
    $ids = array_filter(array_map('intval', explode(',', input_post('ids') ?? '')));
    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'msg' => __('Chưa chọn bản ghi nào')]);
        exit;
    }

    $ph = implode(',', array_fill(0, count($ids), '?'));
    $now = gettime();
    $params = array_merge([$now], $ids);
    $ToryHub->query_safe("UPDATE `salaries` SET `status` = 'paid', `paid_date` = ? WHERE `id` IN ($ph) AND `status` IN ('draft','confirmed')", $params);
    add_log($adminId, 'salary_bulk_paid', "Thanh toán hàng loạt: " . implode(',', $ids));
    echo json_encode(['status' => 'success', 'msg' => __('Đã đánh dấu thanh toán hàng loạt')]);
    exit;
}

echo json_encode(['status' => 'error', 'msg' => __('Yêu cầu không hợp lệ')]);
