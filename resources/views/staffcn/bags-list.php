<?php
require_once(__DIR__.'/../../../models/is_staffcn.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$page_title = __('Danh sách bao hàng');

$filterStatus = input_get('status') ?: '';
$filterSearch = trim(input_get('search') ?? '');

$where = "1=1";
$params = [];

if ($filterStatus && in_array($filterStatus, ['open', 'sealed', 'loading', 'shipping', 'arrived'])) {
    $where .= " AND b.status = ?";
    $params[] = $filterStatus;
}
if ($filterSearch) {
    $where .= " AND b.bag_code LIKE ?";
    $params[] = '%' . $filterSearch . '%';
}

// Pagination
$perPage = 10;
$page = max(1, intval(input_get('page') ?: 1));
$totalBags = $ToryHub->num_rows_safe("SELECT b.id FROM `bags` b WHERE $where", $params);
$totalPages = max(1, ceil($totalBags / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$bags = $ToryHub->get_list_safe("SELECT b.*, u.fullname as creator_name
    FROM `bags` b LEFT JOIN `users` u ON b.created_by = u.id
    WHERE $where ORDER BY b.create_date DESC LIMIT $perPage OFFSET $offset", $params);

$bagStatuses = ['open', 'sealed', 'loading', 'shipping', 'arrived'];
$bagStatusLabels = [
    'open' => ['label' => 'Đang mở', 'bg' => 'info-subtle', 'text' => 'info', 'icon' => 'ri-lock-unlock-line'],
    'sealed' => ['label' => 'Chờ vận chuyển', 'bg' => 'warning-subtle', 'text' => 'warning', 'icon' => 'ri-time-line'],
    'loading' => ['label' => 'Đang xếp xe', 'bg' => 'secondary-subtle', 'text' => 'secondary', 'icon' => 'ri-truck-line'],
    'shipping' => ['label' => 'Đang vận chuyển', 'bg' => 'primary-subtle', 'text' => 'primary', 'icon' => 'ri-ship-line'],
    'arrived' => ['label' => 'Đã đến kho VN', 'bg' => 'success-subtle', 'text' => 'success', 'icon' => 'ri-check-double-line'],
];

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= __('Danh sách bao hàng') ?></h4>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success" id="btn-export-bags"><i class="ri-file-excel-2-line me-1"></i><?= __('Xuất Excel') ?></button>
                        <a href="<?= base_url('staffcn/bags-packing') ?>" class="btn btn-primary">
                            <i class="ri-archive-drawer-line"></i> <?= __('Đóng bao mới') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url('staffcn/bags-list') ?>">
                            <input type="hidden" name="module" value="admin">
                            <input type="hidden" name="action" value="bags-list">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label"><?= __('Tìm kiếm') ?></label>
                                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="<?= __('Mã bao...') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><?= __('Trạng thái') ?></label>
                                    <select class="form-select" name="status">
                                        <option value=""><?= __('Tất cả') ?></option>
                                        <?php foreach ($bagStatuses as $s): ?>
                                        <option value="<?= $s ?>" <?= $filterStatus == $s ? 'selected' : '' ?>><?= __($bagStatusLabels[$s]['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-auto">
                                    <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i><?= __('Lọc') ?></button>
                                    <a href="<?= base_url('staffcn/bags-list') ?>" class="btn btn-secondary"><?= __('Reset') ?></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bags Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= __('Danh sách bao hàng') ?> (<?= $totalBags ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Mã hàng (Mã bao)') ?></th>
                                        <th><?= __('Trạng thái') ?></th>
                                        <th><?= __('Số kiện') ?></th>
                                        <th><?= __('Tổng cân (kg)') ?></th>
                                        <th><?= __('Số khối (m³)') ?></th>
                                        <th><?= __('Người tạo') ?></th>
                                        <th><?= __('Ngày tạo') ?></th>
                                        <th><?= __('Ảnh') ?></th>
                                        <th><?= __('Hành động') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($bags)): ?>
                                    <tr><td colspan="9" class="text-center text-muted py-4"><?= __('Chưa có bao hàng nào') ?></td></tr>
                                    <?php endif; ?>
                                    <?php $rowNum = $offset; foreach ($bags as $bag):
                                        $sl = $bagStatusLabels[$bag['status']] ?? $bagStatusLabels['open'];
                                        $rowNum++;
                                    ?>
                                    <tr class="bag-row" data-bag-id="<?= $bag['id'] ?>" style="cursor:pointer;">
                                        <td class="align-middle">
                                            <a href="<?= base_url('staffcn/bags-packing&id=' . $bag['id']) ?>" onclick="event.stopPropagation();"><strong><?= htmlspecialchars($bag['bag_code']) ?></strong></a>
                                            <i class="ri-arrow-right-s-line fs-14 toggle-icon text-muted ms-1"></i>
                                            <?php if ($bag['total_packages'] > 0): ?>
                                            <div class="mt-1"><span class="text-muted"><i class="ri-archive-line"></i> <?= $bag['total_packages'] ?> <?= __('kiện') ?></span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle"><span class="badge bg-<?= $sl['bg'] ?> text-<?= $sl['text'] ?> fs-12 px-2 py-1"><i class="<?= $sl['icon'] ?> me-1"></i><?= __($sl['label']) ?></span></td>
                                        <td class="align-middle"><span class="fw-bold"><?= $bag['total_packages'] ?></span></td>
                                        <td class="align-middle"><?= number_format($bag['total_weight'], 2) ?></td>
                                        <td class="align-middle"><?= floatval($bag['weight_volume']) ?></td>
                                        <td class="align-middle"><?= htmlspecialchars($bag['creator_name'] ?? '') ?></td>
                                        <td class="align-middle"><?= date('d/m/Y H:i', strtotime($bag['create_date'])) ?></td>
                                        <td class="align-middle text-center">
                                            <?php if (!empty($bag['images'])):
                                                $bagImgArr = array_filter(array_map('trim', explode(',', $bag['images'])));
                                                $bagImgUrls = array_map('get_upload_url', $bagImgArr);
                                                $imgCount = count($bagImgArr);
                                            ?>
                                            <a href="#" class="btn-view-images position-relative d-inline-block" data-images="<?= htmlspecialchars(json_encode(array_values($bagImgUrls))) ?>">
                                                <img src="<?= $bagImgUrls[0] ?>" class="rounded" style="width:40px;height:40px;object-fit:cover;">
                                                <?php if ($imgCount > 1): ?>
                                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:10px;"><?= $imgCount ?></span>
                                                <?php endif; ?>
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle">
                                            <div class="d-flex gap-1">
                                                <a href="<?= base_url('staffcn/bags-packing&id=' . $bag['id']) ?>" class="btn btn-sm btn-primary" onclick="event.stopPropagation();"><i class="ri-pencil-line me-1"></i><?= __('Sửa') ?></a>
                                                <?php if ($bag['status'] === 'open'): ?>
                                                <button type="button" class="btn btn-sm btn-dark btn-seal-bag" data-id="<?= $bag['id'] ?>" data-code="<?= htmlspecialchars($bag['bag_code']) ?>" data-count="<?= $bag['total_packages'] ?>"><i class="ri-lock-line me-1"></i><?= __('Đóng bao') ?></button>
                                                <button type="button" class="btn btn-sm btn-danger btn-delete-bag" data-id="<?= $bag['id'] ?>" data-code="<?= htmlspecialchars($bag['bag_code']) ?>"><i class="ri-delete-bin-line me-1"></i><?= __('Xóa') ?></button>
                                                <?php elseif ($bag['status'] === 'sealed'): ?>
                                                <button type="button" class="btn btn-sm btn-warning btn-unseal-bag" data-id="<?= $bag['id'] ?>" data-code="<?= htmlspecialchars($bag['bag_code']) ?>"><i class="ri-lock-unlock-line me-1"></i><?= __('Mở bao') ?></button>
                                                <button type="button" class="btn btn-sm btn-danger btn-delete-bag" data-id="<?= $bag['id'] ?>" data-code="<?= htmlspecialchars($bag['bag_code']) ?>"><i class="ri-delete-bin-line me-1"></i><?= __('Xóa') ?></button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="bag-detail-row d-none" id="bag-detail-<?= $bag['id'] ?>">
                                        <td colspan="9" class="p-0">
                                            <div class="px-4 py-2 bg-light">
                                                <div class="bag-packages-content">
                                                    <div class="text-center py-2 text-muted"><i class="ri-loader-4-line ri-spin fs-20"></i> <?= __('Đang tải...') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <div class="text-muted">
                                <?= __('Hiển thị') ?> <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalBags) ?> / <?= $totalBags ?>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    $queryParams = $_GET;
                                    unset($queryParams['page']);
                                    $baseUrl = base_url('staffcn/bags-list') . ($queryParams ? '&' . http_build_query($queryParams) : '');
                                    ?>
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . '&page=' . ($page - 1) ?>">&laquo;</a>
                                    </li>
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    if ($startPage > 1): ?>
                                        <li class="page-item"><a class="page-link" href="<?= $baseUrl . '&page=1' ?>">1</a></li>
                                        <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                    <?php endif; ?>
                                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . '&page=' . $p ?>"><?= $p ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <?php if ($endPage < $totalPages): ?>
                                        <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                        <li class="page-item"><a class="page-link" href="<?= $baseUrl . '&page=' . $totalPages ?>"><?= $totalPages ?></a></li>
                                    <?php endif; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $baseUrl . '&page=' . ($page + 1) ?>">&raquo;</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<div class="modal fade" id="imageGalleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0 py-2">
                <span class="text-white-50 fs-12" id="gallery-counter"></span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="imageCarousel" class="carousel slide" data-bs-touch="true">
                    <div class="carousel-inner" id="carousel-items"></div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#imageCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#imageCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function(){
    var csrfToken = '<?= (new Csrf())->get_token_value() ?>';
    var ajaxUrl = '<?= base_url('ajaxs/staffcn/bags.php') ?>';

    // Image gallery
    var galleryCarousel = null;
    var galleryTotal = 0;
    function updateGalleryCounter() {
        var idx = $('#imageCarousel .carousel-item.active').index();
        $('#gallery-counter').text((idx + 1) + ' / ' + galleryTotal);
        if (galleryTotal <= 1) {
            $('#imageCarousel .carousel-control-prev, #imageCarousel .carousel-control-next').addClass('d-none');
        } else {
            $('#imageCarousel .carousel-control-prev, #imageCarousel .carousel-control-next').removeClass('d-none');
        }
    }
    $('#imageCarousel').on('slid.bs.carousel', updateGalleryCounter);
    $(document).on('click', '.btn-view-images', function(e){
        e.preventDefault();
        var images = $(this).data('images');
        if (!images || !images.length) return;
        galleryTotal = images.length;
        var html = '';
        images.forEach(function(url, i){
            html += '<div class="carousel-item' + (i === 0 ? ' active' : '') + '">'
                + '<div class="d-flex align-items-center justify-content-center" style="min-height:300px;">'
                + '<img src="' + url + '" class="d-block" style="max-width:100%;max-height:75vh;object-fit:contain;">'
                + '</div></div>';
        });
        $('#carousel-items').html(html);
        if (galleryCarousel) galleryCarousel.dispose();
        galleryCarousel = new bootstrap.Carousel($('#imageCarousel')[0], { interval: false, touch: true, keyboard: true });
        updateGalleryCounter();
        new bootstrap.Modal($('#imageGalleryModal')[0]).show();
    });

    // Seal bag
    $(document).on('click', '.btn-seal-bag', function(){
        var id = $(this).data('id');
        var code = $(this).data('code');
        var count = $(this).data('count');
        if (count < 1) {
            Swal.fire({icon: 'warning', title: '<?= __('Bao hàng chưa có kiện nào') ?>'});
            return;
        }
        Swal.fire({
            title: '<?= __('Đóng bao?') ?>',
            html: '<?= __('Đóng bao') ?> <strong>' + code + '</strong> (<?= __('gồm') ?> ' + count + ' <?= __('kiện') ?>)?<br><small class="text-muted"><?= __('Sau khi đóng sẽ không thể thêm/gỡ kiện') ?></small>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Đóng bao') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if(result.isConfirmed){
                $.post(ajaxUrl, { request_name: 'seal', bag_id: id, csrf_token: csrfToken }, function(res){
                    if(res.status == 'success'){
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });

    // Unseal bag
    $(document).on('click', '.btn-unseal-bag', function(){
        var id = $(this).data('id');
        var code = $(this).data('code');
        Swal.fire({
            title: '<?= __('Mở lại bao?') ?>',
            html: '<?= __('Mở lại bao') ?> <strong>' + code + '</strong>?<br><small class="text-muted"><?= __('Bao sẽ được mở lại để thêm/gỡ kiện') ?></small>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Mở bao') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if(result.isConfirmed){
                $.post(ajaxUrl, { request_name: 'unseal', bag_id: id, csrf_token: csrfToken }, function(res){
                    if(res.status == 'success'){
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });

    // ===== Expand bag rows =====
    var loadedBags = {};
    $(document).on('click', '.bag-row', function(e){
        if ($(e.target).closest('a, button, .btn').length) return;
        var bagId = $(this).data('bag-id');
        var $detail = $('#bag-detail-' + bagId);
        var $icon = $(this).find('.toggle-icon');
        if ($detail.hasClass('d-none')) {
            $detail.removeClass('d-none');
            $icon.removeClass('ri-arrow-right-s-line').addClass('ri-arrow-down-s-line');
            if (!loadedBags[bagId]) loadBagPackages(bagId);
        } else {
            $detail.addClass('d-none');
            $icon.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-right-s-line');
        }
    });

    function loadBagPackages(bagId) {
        var $c = $('#bag-detail-' + bagId).find('.bag-packages-content');
        $.post(ajaxUrl, {
            request_name: 'load_bag_detail', bag_id: bagId, csrf_token: csrfToken
        }, function(res){
            if (res.csrf_token) csrfToken = res.csrf_token;
            if (res.status === 'success' && res.packages && res.packages.length > 0) {
                var h = '<table class="table table-sm table-borderless mb-0"><thead><tr>';
                h += '<th>#</th><th><?= __("Mã kiện") ?></th><th><?= __("Mã vận đơn") ?></th>';
                h += '<th><?= __("Sản phẩm") ?></th><th><?= __("Khách hàng") ?></th>';
                h += '<th><?= __("Cân nặng") ?></th><th><?= __("Số khối") ?></th><th><?= __("Trạng thái") ?></th>';
                h += '</tr></thead><tbody>';
                res.packages.forEach(function(p, i){
                    h += '<tr>';
                    h += '<td>' + (i + 1) + '</td>';
                    h += '<td><strong>' + esc(p.package_code) + '</strong></td>';
                    h += '<td><code class="fs-11">' + esc(p.tracking_cn || '-') + '</code></td>';
                    h += '<td><small>' + esc(p.product_name || '-') + '</small></td>';
                    h += '<td><small>' + esc(p.customer_name || '-') + '</small></td>';
                    h += '<td>' + (p.weight_charged > 0 ? p.weight_charged + ' kg' : '-') + '</td>';
                    h += '<td>' + (p.cbm > 0 ? p.cbm + ' m³' : '-') + '</td>';
                    h += '<td>' + (p.status_html || p.status || '-') + '</td>';
                    h += '</tr>';
                });
                h += '</tbody></table>';
                $c.html(h);
                loadedBags[bagId] = true;
            } else {
                $c.html('<div class="text-center py-2 text-muted"><?= __("Bao trống") ?></div>');
                loadedBags[bagId] = true;
            }
        }, 'json').fail(function(){
            $c.html('<div class="text-center py-2 text-danger"><?= __("Lỗi tải dữ liệu") ?></div>');
        });
    }

    function esc(s){ if(!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // Delete bag
    $(document).on('click', '.btn-delete-bag', function(){
        var id = $(this).data('id');
        var code = $(this).data('code');
        Swal.fire({
            title: '<?= __('Xóa bao hàng?') ?>',
            html: '<?= __('Xóa bao') ?> <strong>' + code + '</strong>?<br><small class="text-muted"><?= __('Các kiện sẽ quay lại trạng thái kho Trung Quốc') ?></small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: '<?= __('Xóa') ?>',
            cancelButtonText: '<?= __('Hủy') ?>'
        }).then(function(result){
            if(result.isConfirmed){
                $.post(ajaxUrl, { request_name: 'delete', bag_id: id, csrf_token: csrfToken }, function(res){
                    if(res.status == 'success'){
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });

    // Export Excel (server-side with images)
    $('#btn-export-bags').on('click', function(){
        var params = new URLSearchParams(window.location.search);
        window.location.href = '<?= base_url('ajaxs/staffcn/bags-export.php') ?>?' + params.toString();
    });
});
</script>
