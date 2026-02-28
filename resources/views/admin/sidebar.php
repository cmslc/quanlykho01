<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$currentAction = $_GET['action'] ?? 'home';

$sidebarMenu = [
    [
        'label' => 'Dashboard',
        'icon'  => 'ri-dashboard-2-line',
        'url'   => base_url('admin/home'),
        'active' => ['home'],
    ],
    [
        'label' => __('Kho hàng'),
        'icon'  => 'ri-inbox-archive-line',
        'active' => ['orders-list', 'orders-retail', 'orders-add', 'orders-edit', 'orders-detail'],
        'children' => [
            ['label' => __('Hàng lẻ'),   'url' => base_url('admin/orders-retail'), 'active' => ['orders-retail']],
            ['label' => __('Hàng lô'),   'url' => base_url('admin/orders-list'),   'active' => ['orders-list', 'orders-detail']],
            ['label' => __('Nhập kho'), 'url' => base_url('admin/orders-add'),  'active' => ['orders-add']],
        ],
    ],
    [
        'label' => __('Bao hàng lẻ'),
        'icon'  => 'ri-archive-drawer-line',
        'active' => ['bags-list', 'bags-packing'],
        'children' => [
            ['label' => __('Danh sách bao'), 'url' => base_url('admin/bags-list'), 'active' => ['bags-list']],
            ['label' => __('Đóng bao mới'),  'url' => base_url('admin/bags-packing'), 'active' => ['bags-packing']],
        ],
    ],
    [
        'label' => __('Vận chuyển'),
        'icon'  => 'ri-truck-line',
        'active' => ['shipments-pending', 'shipments-list', 'shipments-detail'],
        'children' => [
            ['label' => __('Hàng chờ xếp xe'), 'url' => base_url('admin/shipments-pending'), 'active' => ['shipments-pending']],
            ['label' => __('Danh sách chuyến xe'), 'url' => base_url('admin/shipments-list'), 'active' => ['shipments-list', 'shipments-detail']],
        ],
    ],
    [
        'label' => __('Khách hàng'),
        'icon'  => 'ri-user-heart-line',
        'active' => ['customers-list', 'customers-add', 'customers-edit', 'customers-detail'],
        'children' => [
            ['label' => __('Danh sách khách hàng'), 'url' => base_url('admin/customers-list'), 'active' => ['customers-list', 'customers-detail']],
            ['label' => __('Thêm khách hàng'),      'url' => base_url('admin/customers-add'),  'active' => ['customers-add']],
        ],
    ],
    [
        'label' => __('Nhân viên'),
        'icon'  => 'ri-team-line',
        'active' => ['users-list', 'users-add', 'users-edit'],
        'children' => [
            ['label' => __('Danh sách nhân viên'), 'url' => base_url('admin/users-list'), 'active' => ['users-list']],
            ['label' => __('Thêm nhân viên'),      'url' => base_url('admin/users-add'),  'active' => ['users-add']],
        ],
    ],
    [
        'label' => __('Tài chính'),
        'icon'  => 'ri-money-cny-circle-line',
        'active' => ['transactions', 'transactions-add', 'finance-summary', 'salary-list', 'salary-detail', 'shipping-calculator', 'expenses'],
        'children' => [
            ['label' => __('Tổng quan'),         'url' => base_url('admin/finance-summary'),       'active' => ['finance-summary']],
            ['label' => __('Tính cước'),         'url' => base_url('admin/shipping-calculator'),   'active' => ['shipping-calculator']],
            ['label' => __('Giao dịch'),         'url' => base_url('admin/transactions'),          'active' => ['transactions', 'transactions-add']],
            ['label' => __('Chi phí vận hành'),  'url' => base_url('admin/expenses'),              'active' => ['expenses']],
            ['label' => __('Lương Nhân Viên'),   'url' => base_url('admin/salary-list'),           'active' => ['salary-list', 'salary-detail']],
        ],
    ],
    [
        'label' => __('Báo cáo'),
        'icon'  => 'ri-bar-chart-box-line',
        'active' => ['reports', 'reports-revenue', 'reports-orders', 'reports-customers'],
        'children' => [
            ['label' => __('Doanh thu'),       'url' => base_url('admin/reports-revenue'),   'active' => ['reports-revenue']],
            ['label' => __('Đơn hàng'),        'url' => base_url('admin/reports-orders'),    'active' => ['reports-orders']],
            ['label' => __('Khách hàng'),      'url' => base_url('admin/reports-customers'), 'active' => ['reports-customers']],
        ],
    ],
    [
        'label' => __('Quản lý dịch'),
        'icon'  => 'ri-translate-2',
        'url'   => base_url('admin/translation-manage'),
        'active' => ['translation-manage'],
    ],
    [
        'label' => __('Cài đặt'),
        'icon'  => 'ri-settings-3-line',
        'url'   => base_url('admin/settings'),
        'active' => ['settings'],
    ],
    [
        'label' => __('Nhật ký'),
        'icon'  => 'ri-history-line',
        'url'   => base_url('admin/logs'),
        'active' => ['logs'],
    ],
];

function _sanitize_menu_id($label) {
    return preg_replace('/[^a-zA-Z0-9]/', '-', strtolower($label));
}
?>

<!-- ========== Layout Wrapper ========== -->
<div id="layout-wrapper">

<?php require_once(__DIR__.'/nav.php'); ?>

<!-- ========== App Menu ========== -->
<?php
$_siteLogo = $ToryHub->site('site_logo');
$_siteName = $ToryHub->site('site_brand_name') ?: 'ToryHub';
$_siteInitials = mb_strtoupper(mb_substr($_siteName, 0, 2));
?>
<div class="app-menu navbar-menu">
    <div class="navbar-brand-box">
        <a href="<?= base_url('admin/home') ?>" class="logo logo-dark">
            <span class="logo-sm"><?php if ($_siteLogo): ?><img src="<?= get_upload_url($_siteLogo) ?>" class="logo-img-sm"><?php else: ?><span class="logo-icon"><?= $_siteInitials ?></span><?php endif; ?></span>
            <span class="logo-lg"><?php if ($_siteLogo): ?><img src="<?= get_upload_url($_siteLogo) ?>" class="logo-img me-2"><?php else: ?><span class="logo-icon me-2"><?= $_siteInitials ?></span><?php endif; ?><b><?= htmlspecialchars($_siteName) ?></b></span>
        </a>
        <a href="<?= base_url('admin/home') ?>" class="logo logo-light">
            <span class="logo-sm"><?php if ($_siteLogo): ?><img src="<?= get_upload_url($_siteLogo) ?>" class="logo-img-sm"><?php else: ?><span class="logo-icon"><?= $_siteInitials ?></span><?php endif; ?></span>
            <span class="logo-lg"><?php if ($_siteLogo): ?><img src="<?= get_upload_url($_siteLogo) ?>" class="logo-img me-2"><?php else: ?><span class="logo-icon me-2"><?= $_siteInitials ?></span><?php endif; ?><b><?= htmlspecialchars($_siteName) ?></b></span>
        </a>
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover" id="vertical-hover">
            <i class="ri-record-circle-line"></i>
        </button>
    </div>

    <div id="scrollbar">
        <div class="container-fluid">
            <div id="two-column-menu"></div>
            <ul class="navbar-nav" id="navbar-nav">
                <li class="menu-title"><span><?= __('Menu') ?></span></li>

                <?php foreach ($sidebarMenu as $menu): ?>
                    <?php
                    $isActive = in_array($currentAction, $menu['active']);
                    $hasChildren = !empty($menu['children']);
                    $menuId = 'sidebar-' . _sanitize_menu_id($menu['label']);
                    ?>

                    <?php if ($hasChildren): ?>
                    <li class="nav-item">
                        <a class="nav-link menu-link <?= $isActive ? '' : 'collapsed' ?>" href="#<?= $menuId ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= $isActive ? 'true' : 'false' ?>">
                            <i class="<?= $menu['icon'] ?>"></i>
                            <span><?= $menu['label'] ?></span>
                        </a>
                        <div class="collapse menu-dropdown <?= $isActive ? 'show' : '' ?>" id="<?= $menuId ?>">
                            <ul class="nav nav-sm flex-column">
                                <?php foreach ($menu['children'] as $child): ?>
                                    <?php $childActive = in_array($currentAction, $child['active']); ?>
                                    <li class="nav-item">
                                        <a href="<?= $child['url'] ?>" class="nav-link <?= $childActive ? 'active' : '' ?>">
                                            <?= $child['label'] ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link menu-link <?= $isActive ? 'active' : '' ?>" href="<?= $menu['url'] ?>">
                            <i class="<?= $menu['icon'] ?>"></i>
                            <span><?= $menu['label'] ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="sidebar-background"></div>
</div>
<!-- End App Menu -->

<div class="vertical-overlay"></div>

<!-- ========== Main Content ========== -->
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">
