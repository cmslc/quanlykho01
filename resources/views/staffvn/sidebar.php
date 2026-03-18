<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$currentAction = $_GET['action'] ?? 'home';

$sidebarMenu = [
    [
        'label' => __('Bảng điều khiển'),
        'icon'  => 'ri-dashboard-2-line',
        'url'   => base_url('staffvn/home'),
        'active' => ['home'],
    ],
    ['type' => 'title', 'label' => __('Quản lý kho hàng')],
    [
        'label' => __('Nhập kho'),
        'icon'  => 'ri-inbox-archive-line',
        'url'   => base_url('staffvn/warehouse-receive'),
        'active' => ['warehouse-receive'],
    ],
    [
        'label' => __('Hàng đang đến'),
        'icon'  => 'ri-login-box-line',
        'url'   => base_url('staffvn/orders-list'),
        'active' => ['orders-list', 'orders-detail'],
    ],
    [
        'label' => __('Quản lý hàng lẻ'),
        'icon'  => 'ri-archive-drawer-line',
        'url'   => base_url('staffvn/orders-retail'),
        'active' => ['orders-retail'],
    ],
    [
        'label' => __('Quản lý hàng lô'),
        'icon'  => 'ri-archive-2-line',
        'url'   => base_url('staffvn/orders-wholesale'),
        'active' => ['orders-wholesale'],
    ],
    ['type' => 'title', 'label' => __('Giao hàng & Báo cáo')],
    [
        'label' => __('Giao hàng'),
        'icon'  => 'ri-truck-line',
        'url'   => base_url('staffvn/orders-delivery'),
        'active' => ['orders-delivery'],
    ],
    [
        'label' => __('Báo cáo'),
        'icon'  => 'ri-bar-chart-box-line',
        'url'   => base_url('staffvn/reports'),
        'active' => ['reports'],
    ],
];

if (isset($getUser['role']) && $getUser['role'] === 'finance_vn') {
    $sidebarMenu[] = ['type' => 'title', 'label' => __('Khách hàng')];
    $sidebarMenu[] = ['label' => __('Danh sách KH'),       'icon' => 'ri-contacts-line',          'url' => base_url('staffvn/customers-list'),     'active' => ['customers-list', 'customers-detail', 'customers-edit']];
    $sidebarMenu[] = ['label' => __('Thêm khách hàng'),    'icon' => 'ri-user-add-line',          'url' => base_url('staffvn/customers-add'),      'active' => ['customers-add']];
    $sidebarMenu[] = ['type' => 'title', 'label' => __('Tài chính')];
    $sidebarMenu[] = ['label' => __('Tổng quan'),         'icon' => 'ri-dashboard-line',         'url' => base_url('staffvn/finance-summary'),     'active' => ['finance-summary']];
    $sidebarMenu[] = ['label' => __('Tính cước'),         'icon' => 'ri-calculator-line',        'url' => base_url('staffvn/shipping-calculator'), 'active' => ['shipping-calculator']];
    $sidebarMenu[] = ['label' => __('Công nợ'),           'icon' => 'ri-money-cny-circle-line',  'url' => base_url('staffvn/debt-list'),           'active' => ['debt-list']];
    $sidebarMenu[] = ['label' => __('Giao dịch'),         'icon' => 'ri-exchange-line',          'url' => base_url('staffvn/transactions'),        'active' => ['transactions', 'transactions-add']];
    $sidebarMenu[] = ['label' => __('Chi phí vận hành'),  'icon' => 'ri-wallet-3-line',          'url' => base_url('staffvn/expenses'),            'active' => ['expenses']];
    $sidebarMenu[] = ['label' => __('Lương Nhân Viên'),   'icon' => 'ri-user-star-line',         'url' => base_url('staffvn/salary-list'),         'active' => ['salary-list', 'salary-detail']];
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
        <a href="<?= base_url('staffvn/home') ?>" class="logo logo-dark">
            <span class="logo-sm"><?php if ($_siteLogo): ?><img src="<?= get_upload_url($_siteLogo) ?>" class="logo-img-sm"><?php else: ?><span class="logo-icon"><?= $_siteInitials ?></span><?php endif; ?></span>
            <span class="logo-lg"><?php if ($_siteLogo): ?><img src="<?= get_upload_url($_siteLogo) ?>" class="logo-img me-2"><?php else: ?><span class="logo-icon me-2"><?= $_siteInitials ?></span><?php endif; ?><b><?= __('Kho Việt Nam') ?></b></span>
        </a>
        <a href="<?= base_url('staffvn/home') ?>" class="logo logo-light">
            <span class="logo-sm"><?php if ($_siteLogo): ?><img src="<?= get_upload_url($_siteLogo) ?>" class="logo-img-sm"><?php else: ?><span class="logo-icon"><?= $_siteInitials ?></span><?php endif; ?></span>
            <span class="logo-lg"><?php if ($_siteLogo): ?><img src="<?= get_upload_url($_siteLogo) ?>" class="logo-img me-2"><?php else: ?><span class="logo-icon me-2"><?= $_siteInitials ?></span><?php endif; ?><b><?= __('Kho Việt Nam') ?></b></span>
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
                    <?php else: ?>
                    <?php $isActive = in_array($currentAction, $menu['active']); ?>
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
