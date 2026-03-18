<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$currentAction = $_GET['action'] ?? 'home';

$sidebarMenu = [
    [
        'label' => __('Bảng điều khiển'),
        'icon'  => 'ri-dashboard-2-line',
        'url'   => base_url('staffcn/home'),
        'active' => ['home'],
    ],
    ['type' => 'title', 'label' => __('Quản lý kho hàng')],
    [
        'label' => __('Nhập kho'),
        'icon'  => 'ri-add-circle-line',
        'url'   => base_url('staffcn/orders-add'),
        'active' => ['orders-add'],
    ],
    [
        'label' => __('Quản lý hàng lẻ'),
        'icon'  => 'ri-file-list-3-line',
        'url'   => base_url('staffcn/orders-retail'),
        'active' => ['orders-retail'],
    ],
    [
        'label' => __('Quản lý hàng lô'),
        'icon'  => 'ri-inbox-archive-line',
        'url'   => base_url('staffcn/orders-list'),
        'active' => ['orders-list', 'orders-detail'],
    ],
    ['type' => 'title', 'label' => __('Quản lý bao')],
    [
        'label' => __('Danh sách bao'),
        'icon'  => 'ri-archive-drawer-line',
        'url'   => base_url('staffcn/bags-list'),
        'active' => ['bags-list'],
    ],
    [
        'label' => __('Đóng bao mới'),
        'icon'  => 'ri-archive-line',
        'url'   => base_url('staffcn/bags-packing'),
        'active' => ['bags-packing'],
    ],
    ['type' => 'title', 'label' => __('Vận chuyển')],
    [
        'label' => __('Hàng chờ xếp xe'),
        'icon'  => 'ri-time-line',
        'url'   => base_url('staffcn/shipments-pending'),
        'active' => ['shipments-pending'],
    ],
    [
        'label' => __('Danh sách chuyến xe'),
        'icon'  => 'ri-truck-line',
        'url'   => base_url('staffcn/shipments-list'),
        'active' => ['shipments-list', 'shipments-detail'],
    ],
];

if (get_user_role() === 'finance_cn') {
    $sidebarMenu[] = ['type' => 'title', 'label' => __('Khách hàng')];
    $sidebarMenu[] = ['label' => __('Danh sách KH'),       'icon' => 'ri-contacts-line',          'url' => base_url('staffcn/customers-list'),     'active' => ['customers-list', 'customers-detail', 'customers-edit']];
    $sidebarMenu[] = ['label' => __('Thêm khách hàng'),    'icon' => 'ri-user-add-line',          'url' => base_url('staffcn/customers-add'),      'active' => ['customers-add']];
    $sidebarMenu[] = ['type' => 'title', 'label' => __('Tài chính')];
    $sidebarMenu[] = ['label' => __('Tổng quan'),         'icon' => 'ri-dashboard-line',         'url' => base_url('staffcn/finance-summary'),     'active' => ['finance-summary']];
    $sidebarMenu[] = ['label' => __('Tính cước'),         'icon' => 'ri-calculator-line',        'url' => base_url('staffcn/shipping-calculator'), 'active' => ['shipping-calculator']];
    $sidebarMenu[] = ['label' => __('Công nợ'),           'icon' => 'ri-money-cny-circle-line',  'url' => base_url('staffcn/debt-list'),           'active' => ['debt-list']];
    $sidebarMenu[] = ['label' => __('Giao dịch'),         'icon' => 'ri-exchange-line',          'url' => base_url('staffcn/transactions'),        'active' => ['transactions', 'transactions-add']];
    $sidebarMenu[] = ['label' => __('Chi phí vận hành'),  'icon' => 'ri-wallet-3-line',          'url' => base_url('staffcn/expenses'),            'active' => ['expenses']];
    $sidebarMenu[] = ['label' => __('Lương Nhân Viên'),   'icon' => 'ri-user-star-line',         'url' => base_url('staffcn/salary-list'),         'active' => ['salary-list', 'salary-detail']];
}


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
        <a href="<?= base_url('staffcn/home') ?>" class="logo logo-dark">
            <span class="logo-sm"><?php if ($_siteLogo): ?><img src="<?= get_upload_url($_siteLogo) ?>" class="logo-img-sm"><?php else: ?><span class="logo-icon"><?= $_siteInitials ?></span><?php endif; ?></span>
            <span class="logo-lg"><?php if ($_siteLogo): ?><img src="<?= get_upload_url($_siteLogo) ?>" class="logo-img me-2"><?php else: ?><span class="logo-icon me-2"><?= $_siteInitials ?></span><?php endif; ?><b><?= __('Kho Trung Quốc') ?></b></span>
        </a>
        <a href="<?= base_url('staffcn/home') ?>" class="logo logo-light">
            <span class="logo-sm"><?php if ($_siteLogo): ?><img src="<?= get_upload_url($_siteLogo) ?>" class="logo-img-sm"><?php else: ?><span class="logo-icon"><?= $_siteInitials ?></span><?php endif; ?></span>
            <span class="logo-lg"><?php if ($_siteLogo): ?><img src="<?= get_upload_url($_siteLogo) ?>" class="logo-img me-2"><?php else: ?><span class="logo-icon me-2"><?= $_siteInitials ?></span><?php endif; ?><b><?= __('Kho Trung Quốc') ?></b></span>
        </a>
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover" id="vertical-hover">
            <i class="ri-record-circle-line"></i>
        </button>
    </div>

    <div id="scrollbar">
        <div class="container-fluid">
            <div id="two-column-menu"></div>
            <ul class="navbar-nav" id="navbar-nav">

                <?php foreach ($sidebarMenu as $menu): ?>
                    <?php if (($menu['type'] ?? '') === 'title'): ?>
                    <li class="menu-title"><span><?= $menu['label'] ?></span></li>
                    <?php continue; endif; ?>
                    <?php
                    $isActive = in_array($currentAction, $menu['active']);
                    $hasChildren = !empty($menu['children']);
                    $menuId = 'sidebar-' . ($menu['active'][0] ?? _sanitize_menu_id($menu['label']));
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
