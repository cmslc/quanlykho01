<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$currentAction = $_GET['action'] ?? 'home';

$sidebarMenu = [
    [
        'label' => 'Dashboard',
        'icon'  => 'ri-dashboard-2-line',
        'url'   => base_url('customer/home'),
        'active' => ['home'],
    ],
    [
        'label' => __('Đơn hàng'),
        'icon'  => 'ri-shopping-cart-2-line',
        'url'   => base_url('customer/orders'),
        'active' => ['orders', 'orders-detail'],
    ],
    [
        'label' => __('Lịch sử giao dịch'),
        'icon'  => 'ri-exchange-funds-line',
        'url'   => base_url('customer/transactions'),
        'active' => ['transactions'],
    ],
    [
        'label' => __('Tra cứu'),
        'icon'  => 'ri-search-line',
        'url'   => base_url('customer/tracking'),
        'active' => ['tracking'],
    ],
    [
        'label' => __('Hồ sơ cá nhân'),
        'icon'  => 'ri-user-settings-line',
        'url'   => base_url('customer/profile'),
        'active' => ['profile'],
    ],
    [
        'label' => __('Đổi mật khẩu'),
        'icon'  => 'ri-lock-password-line',
        'url'   => base_url('customer/change-password'),
        'active' => ['change-password'],
    ],
];
?>

<!-- ========== Layout Wrapper ========== -->
<div id="layout-wrapper">

<?php require_once(__DIR__.'/nav.php'); ?>

<!-- ========== App Menu ========== -->
<div class="app-menu navbar-menu">
    <div class="navbar-brand-box">
        <a href="<?= base_url('customer/home') ?>" class="logo logo-dark">
            <span class="logo-sm"><b>CMS</b></span>
            <span class="logo-lg"><b>CMS01</b> Portal</span>
        </a>
        <a href="<?= base_url('customer/home') ?>" class="logo logo-light">
            <span class="logo-sm"><b>CMS</b></span>
            <span class="logo-lg"><b>CMS01</b> Portal</span>
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
                    <?php $isActive = in_array($currentAction, $menu['active']); ?>
                    <li class="nav-item">
                        <a class="nav-link menu-link <?= $isActive ? 'active' : '' ?>" href="<?= $menu['url'] ?>">
                            <i class="<?= $menu['icon'] ?>"></i>
                            <span><?= $menu['label'] ?></span>
                        </a>
                    </li>
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
