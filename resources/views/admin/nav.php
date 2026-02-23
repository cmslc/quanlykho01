<?php if (!defined('IN_SITE')) { die('The Request Not Found'); } ?>
<header id="page-topbar">
    <div class="layout-width">
        <div class="navbar-header">
            <div class="d-flex">
                <div class="navbar-brand-box horizontal-logo">
                    <a href="<?= base_url('admin/home') ?>" class="logo logo-dark">
                        <span class="logo-lg"><b>CMS01</b></span>
                    </a>
                    <a href="<?= base_url('admin/home') ?>" class="logo logo-light">
                        <span class="logo-lg"><b>CMS01</b></span>
                    </a>
                </div>

                <button type="button" class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger" id="topnav-hamburger-icon">
                    <span class="hamburger-icon"><span></span><span></span><span></span></span>
                </button>

                <div class="d-none d-sm-flex align-items-center gap-2 ms-2">
                    <span class="badge bg-soft-primary text-primary fs-12"><?= __('Tỷ giá') ?>: <?= format_vnd(get_exchange_rate()) ?>/¥</span>
                </div>
            </div>

            <div class="d-flex align-items-center">
                <!-- Language -->
                <div class="dropdown ms-1 topbar-head-dropdown header-item">
                    <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle" data-bs-toggle="dropdown">
                        <span class="fs-14 text-uppercase fw-bold"><?= getLanguage() ?></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a href="<?= base_url('ajaxs/admin/change-lang.php?lang=vi') ?>" class="dropdown-item">Tiếng Việt</a>
                        <a href="<?= base_url('ajaxs/admin/change-lang.php?lang=zh') ?>" class="dropdown-item">中文</a>
                        <a href="<?= base_url('ajaxs/admin/change-lang.php?lang=en') ?>" class="dropdown-item">English</a>
                    </div>
                </div>

                <!-- Fullscreen -->
                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle" data-toggle="fullscreen">
                        <i class="ri-fullscreen-line fs-22"></i>
                    </button>
                </div>

                <!-- Dark/Light -->
                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle light-dark-mode">
                        <i class="ri-moon-line fs-22"></i>
                    </button>
                </div>

                <!-- User -->
                <div class="dropdown ms-sm-3 header-item topbar-user">
                    <button type="button" class="btn" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <span class="text-start ms-xl-2">
                                <span class="d-none d-xl-inline-block ms-1 fw-medium user-name-text"><?= htmlspecialchars($getUser['fullname'] ?? $getUser['username']) ?></span>
                                <span class="d-none d-xl-block ms-1 fs-12 text-muted user-name-sub-text"><?= $getUser['role'] ?></span>
                            </span>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header"><?= __('Xin chào') ?>, <?= htmlspecialchars($getUser['fullname'] ?? $getUser['username']) ?>!</h6>
                        <a class="dropdown-item" href="<?= base_url('admin/settings') ?>">
                            <i class="ri-settings-3-line text-muted fs-16 align-middle me-1"></i>
                            <span><?= __('Cài đặt') ?></span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="<?= base_url('admin/logout') ?>">
                            <i class="ri-logout-box-r-line text-muted fs-16 align-middle me-1"></i>
                            <span><?= __('Đăng xuất') ?></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
