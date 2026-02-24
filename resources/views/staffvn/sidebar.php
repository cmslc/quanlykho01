<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$currentAction = $_GET['action'] ?? 'home';

$sidebarMenu = [
    [
        'label' => 'Dashboard',
        'icon'  => 'ri-dashboard-2-line',
        'url'   => base_url('staffvn/home'),
        'active' => ['home'],
    ],
    [
        'label' => __('Hàng đến'),
        'icon'  => 'ri-ship-line',
        'active' => ['orders-list', 'orders-detail'],
        'children' => [
            ['label' => __('Hàng đang vận chuyển'), 'url' => base_url('staffvn/orders-list'), 'active' => ['orders-list', 'orders-detail']],
        ]
    ],
    [
        'label' => __('Quản lý mã hàng'),
        'icon'  => 'ri-file-list-3-line',
        'url'   => base_url('staffvn/orders-manage'),
        'active' => ['orders-manage'],
    ],
    [
        'label' => __('Nhập kho VN'),
        'icon'  => 'ri-inbox-archive-line',
        'active' => ['packages-list', 'orders-scan', 'bag-unpack', 'warehouse-zones', 'inventory-check'],
        'children' => [
            ['label' => __('Kiện hàng'), 'url' => base_url('staffvn/packages-list'), 'active' => ['packages-list']],
            ['label' => __('Quét mã hàng loạt'), 'url' => base_url('staffvn/orders-scan'), 'active' => ['orders-scan']],
            ['label' => __('Tách bao'), 'url' => base_url('staffvn/bag-unpack'), 'active' => ['bag-unpack']],
            ['label' => __('Vị trí kho'), 'url' => base_url('staffvn/warehouse-zones'), 'active' => ['warehouse-zones']],
            ['label' => __('Kiểm kê kho'), 'url' => base_url('staffvn/inventory-check'), 'active' => ['inventory-check']],
        ]
    ],
    [
        'label' => __('Giao hàng'),
        'icon'  => 'ri-truck-line',
        'active' => ['orders-delivery', 'delivery-batches', 'delivery-detail'],
        'children' => [
            ['label' => __('Giao đơn lẻ'), 'url' => base_url('staffvn/orders-delivery'), 'active' => ['orders-delivery']],
            ['label' => __('Chuyến giao hàng'), 'url' => base_url('staffvn/delivery-batches'), 'active' => ['delivery-batches', 'delivery-detail']],
        ]
    ],
    [
        'label' => __('Báo cáo'),
        'icon'  => 'ri-bar-chart-box-line',
        'url'   => base_url('staffvn/reports'),
        'active' => ['reports'],
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
