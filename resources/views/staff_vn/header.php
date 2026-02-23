<?php
if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$ToryHub = new DB();
$csrf = new Csrf();
?>
<!doctype html>
<html lang="vi" data-layout="vertical" data-bs-theme="light" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-sidebar-visibility="show" data-layout-width="fluid" data-layout-position="fixed" data-layout-style="default" data-preloader="disable">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= $page_title ?? __('Kho Việt Nam') ?> - Kho Việt Nam - ToryHub</title>
    <link rel="shortcut icon" href="<?= base_url('public/material/assets/images/favicon.ico') ?>">
    <!-- Layout config Js (MUST be first) -->
    <script src="<?= base_url('public/material/assets/js/layout.js') ?>"></script>
    <!-- Bootstrap 5 Css -->
    <link href="<?= base_url('public/material/assets/css/bootstrap.min.css') ?>" rel="stylesheet" type="text/css">
    <!-- Icons Css -->
    <link href="<?= base_url('public/material/assets/css/icons.min.css') ?>" rel="stylesheet" type="text/css">
    <!-- App Css -->
    <link href="<?= base_url('public/material/assets/css/app.min.css') ?>" rel="stylesheet" type="text/css">
    <!-- Custom Css -->
    <link href="<?= base_url('public/material/assets/css/custom.css') ?>" rel="stylesheet" type="text/css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables BS5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- SweetAlert2 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .bg-purple { background-color: #6f42c1 !important; }
        .bg-orange { background-color: #fd7e14 !important; }
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    </style>
    <?= $body['header'] ?? '' ?>
</head>
<body>
