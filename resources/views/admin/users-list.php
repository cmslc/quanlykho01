<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Quản lý nhân viên');
$users = $ToryHub->get_list_safe("SELECT * FROM `users` ORDER BY `id` DESC", []);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>

        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Quản lý nhân viên') ?></h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><?= __('Danh sách nhân viên') ?></h5>
                        <a href="<?= base_url('admin/users-add') ?>" class="btn btn-sm btn-primary">
                            <i class="las la-plus"></i> <?= __('Thêm mới') ?>
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table data-table table-hover mb-0">
                                <thead class="table-color-heading">
                                    <tr>
                                        <th>ID</th>
                                        <th><?= __('Username') ?></th>
                                        <th><?= __('Họ tên') ?></th>
                                        <th><?= __('Email') ?></th>
                                        <th><?= __('Vai trò') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Online') ?></th>
                                        <th><?= __('Hành động') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= $user['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                                        <td><?= htmlspecialchars($user['fullname'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                                        <td><?= display_role($user['role']) ?></td>
                                        <td><?= display_banned($user['banned']) ?></td>
                                        <td><?= display_online($user['time_session']) ?></td>
                                        <td>
                                            <div class="d-flex">
                                                <a href="<?= base_url('admin/users-edit?id=' . $user['id']) ?>" class="btn btn-sm btn-warning me-1" title="Edit">
                                                    <i class="las la-edit"></i>
                                                </a>
                                                <?php if ($user['id'] != $getUser['id']): ?>
                                                <button class="btn btn-sm btn-danger btn-delete-user" data-id="<?= $user['id'] ?>" title="Delete">
                                                    <i class="las la-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$(document).on('click', '.btn-delete-user', function(){
    var id = $(this).data('id');
    if(confirm('<?= __('Bạn có chắc muốn xóa?') ?>')){
        $.post('<?= base_url('ajaxs/admin/users.php') ?>', {
            request_name: 'delete',
            id: id,
            csrf_token: '<?= $csrf->get_token_value() ?>'
        }, function(res){
            if(res.status == 'success'){
                location.reload();
            } else {
                alert(res.msg);
            }
        }, 'json');
    }
});
</script>
