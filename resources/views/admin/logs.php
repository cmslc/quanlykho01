<?php
require_once(__DIR__.'/../../../models/is_admin.php');

$page_title = __('Nhật ký hệ thống');

$logs = $CMSNT->get_list_safe("SELECT l.*, u.username FROM `logs` l LEFT JOIN `users` u ON l.user_id = u.id ORDER BY l.create_date DESC LIMIT 200", []);

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Nhật ký hệ thống') ?></h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Nhật ký hoạt động') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table data-table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th><?= __('Người dùng') ?></th>
                                        <th><?= __('Hành động') ?></th>
                                        <th><?= __('Mô tả') ?></th>
                                        <th>IP</th>
                                        <th><?= __('Thời gian') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= $log['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($log['username'] ?? 'System') ?></strong></td>
                                        <td><span class="badge bg-info-subtle text-info"><?= htmlspecialchars($log['action']) ?></span></td>
                                        <td><?= htmlspecialchars($log['description'] ?? '') ?></td>
                                        <td><code><?= htmlspecialchars($log['ip'] ?? '') ?></code></td>
                                        <td><?= $log['create_date'] ?></td>
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
