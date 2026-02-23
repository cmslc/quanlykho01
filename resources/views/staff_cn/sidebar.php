<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$currentAction = $_GET['action'] ?? 'home';

$sidebarMenu = [
    [
        'label' => 'Dashboard',
        'icon'  => 'ri-dashboard-2-line',
        'url'   => base_url('staff_cn/home'),
        'active' => ['home'],
    ],
    [
        'label' => __('Đơn hàng cần xử lý'),
        'icon'  => 'ri-shopping-cart-2-line',
        'url'   => base_url('staff_cn/orders-list'),
        'active' => ['orders-list'],
    ],
    [
        'label' => __('Quét mã nhập kho'),
        'icon'  => 'ri-qr-scan-2-line',
        'url'   => base_url('staff_cn/orders-scan'),
        'active' => ['orders-scan'],
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
<div class="app-menu navbar-menu">
    <div class="navbar-brand-box">
        <a href="<?= base_url('staff_cn/home') ?>" class="logo logo-dark">
            <span class="logo-sm"><b>KTQ</b></span>
            <span class="logo-lg"><b>Kho Trung Quốc</b> - ToryHub</span>
        </a>
        <a href="<?= base_url('staff_cn/home') ?>" class="logo logo-light">
            <span class="logo-sm"><b>KTQ</b></span>
            <span class="logo-lg"><b>Kho Trung Quốc</b> - ToryHub</span>
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
