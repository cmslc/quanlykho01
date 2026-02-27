<?php
require_once(__DIR__.'/../../../models/is_admin.php');
require_once(__DIR__.'/../../../libs/csrf.php');

$flagMap = [
    'vi' => "\xF0\x9F\x87\xBB\xF0\x9F\x87\xB3",
    'zh' => "\xF0\x9F\x87\xA8\xF0\x9F\x87\xB3",
    'en' => "\xF0\x9F\x87\xBA\xF0\x9F\x87\xB8",
    'ja' => "\xF0\x9F\x87\xAF\xF0\x9F\x87\xB5",
    'ko' => "\xF0\x9F\x87\xB0\xF0\x9F\x87\xB7",
    'th' => "\xF0\x9F\x87\xB9\xF0\x9F\x87\xAD",
    'fr' => "\xF0\x9F\x87\xAB\xF0\x9F\x87\xB7",
    'de' => "\xF0\x9F\x87\xA9\xF0\x9F\x87\xAA",
];

// ====== SUB-PAGE: Translation Editor for a specific language ======
$editLangId = intval(input_get('lang_id') ?? 0);

if ($editLangId) {
    $editLang = $ToryHub->get_row_safe("SELECT * FROM `languages` WHERE `id` = ?", [$editLangId]);
    if (!$editLang) {
        header('Location: ' . base_url('admin/translation-manage'));
        exit;
    }

    $page_title = __('Dịch') . ': ' . htmlspecialchars($editLang['name']);

    // Scan keys from source
    $baseDir = realpath(__DIR__ . '/../../../');
    $scanPatterns = [
        $baseDir . '/resources/views/admin/*.php',
        $baseDir . '/resources/views/customer/*.php',
        $baseDir . '/resources/views/staffcn/*.php',
        $baseDir . '/resources/views/staffvn/*.php',
        $baseDir . '/resources/views/common/*.php',
    ];
    $allKeys = [];
    foreach ($scanPatterns as $pattern) {
        $files = glob($pattern);
        if (!$files) continue;
        foreach ($files as $file) {
            $content = file_get_contents($file);
            preg_match_all("/__\\(['\"](.+?)['\"]\\)/", $content, $matches);
            foreach ($matches[1] as $key) {
                $allKeys[$key] = true;
            }
        }
    }
    $allKeys = array_keys($allKeys);
    sort($allKeys);

    // Get translations for this language
    $translations = $ToryHub->get_list_safe("SELECT * FROM `translate` WHERE `lang_id` = ?", [$editLangId]);
    $transMap = [];
    foreach ($translations as $t) {
        $transMap[$t['name']] = $t['value'];
    }

    $totalKeys = count($allKeys);
    $translated = 0;
    foreach ($allKeys as $key) {
        if (!empty($transMap[$key])) $translated++;
    }
    $missing = $totalKeys - $translated;

    // Filters
    $filterSearch = trim(input_get('search') ?? '');
    $filterStatus = input_get('status') ?: '';
    $perPage = 10;
    $currentPage = max(1, intval(input_get('page') ?: 1));

    $filteredKeys = $allKeys;
    if ($filterSearch) {
        $search = mb_strtolower($filterSearch);
        $filteredKeys = array_values(array_filter($filteredKeys, function($key) use ($search, $transMap) {
            if (mb_strpos(mb_strtolower($key), $search) !== false) return true;
            $val = $transMap[$key] ?? '';
            if ($val && mb_strpos(mb_strtolower($val), $search) !== false) return true;
            return false;
        }));
    }
    if ($filterStatus === 'translated') {
        $filteredKeys = array_values(array_filter($filteredKeys, function($key) use ($transMap) {
            return !empty($transMap[$key]);
        }));
    } elseif ($filterStatus === 'missing') {
        $filteredKeys = array_values(array_filter($filteredKeys, function($key) use ($transMap) {
            return empty($transMap[$key]);
        }));
    }

    $totalFiltered = count($filteredKeys);
    $totalPages = max(1, ceil($totalFiltered / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $pagedKeys = array_slice($filteredKeys, ($currentPage - 1) * $perPage, $perPage);

    $baseUrl = base_url('admin/translation-manage') . '&lang_id=' . $editLangId;
    $currentFilters = ['search' => $filterSearch, 'status' => $filterStatus];
    function buildTrUrl($params, $base) {
        $q = http_build_query(array_filter($params, function($v){ return $v !== '' && $v !== null && $v !== 0; }));
        return $base . ($q ? '&' . $q : '');
    }

    require_once(__DIR__.'/header.php');
    require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= $page_title ?></h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="<?= base_url('admin/translation-manage') ?>"><?= __('Quản lý dịch') ?></a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($editLang['name']) ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row mb-3">
            <div class="col">
                <div class="card card-body py-3 mb-0">
                    <div class="text-muted fs-12 mb-1"><?= __('Tổng key') ?></div>
                    <h5 class="mb-0"><?= $totalKeys ?></h5>
                </div>
            </div>
            <div class="col">
                <div class="card card-body py-3 mb-0">
                    <div class="text-muted fs-12 mb-1"><?= __('Đã dịch') ?></div>
                    <h5 class="mb-0 <?= $translated == $totalKeys ? 'text-success' : 'text-primary' ?>">
                        <?= $translated ?> / <?= $totalKeys ?>
                    </h5>
                    <small class="text-muted"><?= $totalKeys > 0 ? round($translated / $totalKeys * 100) : 0 ?>%</small>
                </div>
            </div>
            <div class="col">
                <div class="card card-body py-3 mb-0">
                    <div class="text-muted fs-12 mb-1"><?= __('Chưa dịch') ?></div>
                    <h5 class="mb-0 text-danger"><?= $missing ?></h5>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="row mb-3">
            <div class="col-12">
                <form method="get" action="<?= base_url('admin/translation-manage') ?>" class="card card-body py-3">
                    <input type="hidden" name="module" value="admin">
                    <input type="hidden" name="action" value="translation-manage">
                    <input type="hidden" name="lang_id" value="<?= $editLangId ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label mb-1 fs-12"><?= __('Tìm kiếm') ?></label>
                            <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="<?= __('Key hoặc bản dịch...') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1 fs-12"><?= __('Trạng thái') ?></label>
                            <select name="status" class="form-select">
                                <option value=""><?= __('Tất cả') ?></option>
                                <option value="translated" <?= $filterStatus === 'translated' ? 'selected' : '' ?>><?= __('Đã dịch') ?></option>
                                <option value="missing" <?= $filterStatus === 'missing' ? 'selected' : '' ?>><?= __('Chưa dịch') ?></option>
                            </select>
                        </div>
                        <div class="col-md-auto d-flex gap-1">
                            <button type="submit" class="btn btn-primary"><i class="ri-filter-3-line me-1"></i><?= __('Lọc') ?></button>
                            <a href="<?= $baseUrl ?>" class="btn btn-outline-secondary"><i class="ri-refresh-line"></i></a>
                        </div>
                        <div class="col-md-auto ms-auto">
                            <button type="button" class="btn btn-info" id="btn-scan-keys"><i class="ri-radar-line me-1"></i><?= __('Quét key') ?></button>
                            <button type="button" class="btn btn-success" id="btn-save-all"><i class="ri-save-line me-1"></i><?= __('Lưu tất cả') ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Translation Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h5 class="card-title mb-0 flex-grow-1">
                            <?= ($flagMap[$editLang['lang']] ?? '') ?> <?= htmlspecialchars($editLang['name']) ?>
                            <span class="text-muted fs-12">(<?= $totalFiltered ?> key)</span>
                        </h5>
                        <a href="<?= base_url('admin/translation-manage') ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="ri-arrow-left-line me-1"></i><?= __('Quay lại') ?>
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="tbl-translations">
                                <thead>
                                    <tr>
                                        <th style="width:40px;">#</th>
                                        <th style="min-width:300px;"><?= __('Key') ?> (<?= __('Tiếng Việt') ?>)</th>
                                        <th style="min-width:300px;"><?= htmlspecialchars($editLang['name']) ?></th>
                                        <th style="width:60px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pagedKeys)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4"><?= __('Không có key nào') ?></td></tr>
                                    <?php endif; ?>
                                    <?php
                                    $rowIdx = ($currentPage - 1) * $perPage;
                                    foreach ($pagedKeys as $key):
                                        $rowIdx++;
                                        $val = $transMap[$key] ?? '';
                                    ?>
                                    <tr class="tr-key " data-key="<?= htmlspecialchars($key) ?>">
                                        <td class="text-muted"><?= $rowIdx ?></td>
                                        <td><code class="text-dark"><?= htmlspecialchars($key) ?></code></td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm trans-input"
                                                data-lang-id="<?= $editLangId ?>"
                                                data-key="<?= htmlspecialchars($key) ?>"
                                                value="<?= htmlspecialchars($val) ?>"
                                                placeholder="<?= htmlspecialchars($editLang['name']) ?>...">
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-save-row" title="<?= __('Lưu') ?>"><i class="ri-save-line"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <div class="text-muted fs-12">
                                <?= (($currentPage - 1) * $perPage + 1) ?>-<?= min($currentPage * $perPage, $totalFiltered) ?> / <?= $totalFiltered ?>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= buildTrUrl(array_merge($currentFilters, ['page' => $currentPage - 1]), $baseUrl) ?>">&laquo;</a>
                                    </li>
                                    <?php
                                    $startP = max(1, $currentPage - 2);
                                    $endP = min($totalPages, $currentPage + 2);
                                    if ($startP > 1): ?><li class="page-item"><a class="page-link" href="<?= buildTrUrl(array_merge($currentFilters, ['page' => 1]), $baseUrl) ?>">1</a></li><?php if ($startP > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; endif;
                                    for ($p = $startP; $p <= $endP; $p++): ?>
                                    <li class="page-item <?= $p == $currentPage ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= buildTrUrl(array_merge($currentFilters, ['page' => $p]), $baseUrl) ?>"><?= $p ?></a>
                                    </li>
                                    <?php endfor;
                                    if ($endP < $totalPages): ?><?php if ($endP < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?><li class="page-item"><a class="page-link" href="<?= buildTrUrl(array_merge($currentFilters, ['page' => $totalPages]), $baseUrl) ?>"><?= $totalPages ?></a></li><?php endif; ?>
                                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= buildTrUrl(array_merge($currentFilters, ['page' => $currentPage + 1]), $baseUrl) ?>">&raquo;</a>
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

<script>
$(function(){
    var csrfToken = '<?= (new Csrf())->get_token_value() ?>';
    var ajaxUrl = '<?= base_url("ajaxs/admin/translations.php") ?>';
    var langId = <?= $editLangId ?>;

    // Mark changed inputs
    $(document).on('input', '.trans-input', function(){
        $(this).addClass('border-warning');
        $(this).closest('tr').find('.btn-save-row').removeClass('btn-outline-primary').addClass('btn-warning');
    });

    // Save single row
    $(document).on('click', '.btn-save-row', function(){
        var $tr = $(this).closest('tr');
        var $btn = $(this);
        var $input = $tr.find('.trans-input');

        $btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i>');
        $.post(ajaxUrl, {
            request_name: 'save_translation',
            lang_id: langId,
            name: $input.data('key'),
            value: $input.val().trim(),
            csrf_token: csrfToken
        }, function(res){
            if (res.status === 'success') {
                $input.removeClass('border-warning');
                $btn.removeClass('btn-warning').addClass('btn-outline-success');
                $btn.html('<i class="ri-check-line"></i>');
                setTimeout(function(){
                    $btn.removeClass('btn-outline-success').addClass('btn-outline-primary').html('<i class="ri-save-line"></i>').prop('disabled', false);
                }, 1500);
            } else {
                Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                $btn.prop('disabled', false).html('<i class="ri-save-line"></i>');
            }
        }, 'json').fail(function(){
            Swal.fire({icon: 'error', title: 'Error', text: '<?= __("Lỗi kết nối") ?>'});
            $btn.prop('disabled', false).html('<i class="ri-save-line"></i>');
        });
    });

    // Save all changed
    $('#btn-save-all').on('click', function(){
        var translations = [];
        $('.trans-input.border-warning').each(function(){
            translations.push({
                lang_id: langId,
                name: $(this).data('key'),
                value: $(this).val().trim()
            });
        });

        if (translations.length === 0) {
            Swal.fire({icon: 'info', title: '<?= __("Không có thay đổi nào") ?>'});
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin me-1"></i><?= __("Đang lưu...") ?>');

        $.post(ajaxUrl, {
            request_name: 'save_bulk',
            translations: JSON.stringify(translations),
            csrf_token: csrfToken
        }, function(res){
            if (res.status === 'success') {
                Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
            } else {
                Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                $btn.prop('disabled', false).html('<i class="ri-save-line me-1"></i><?= __("Lưu tất cả") ?>');
            }
        }, 'json').fail(function(){
            Swal.fire({icon: 'error', title: 'Error', text: '<?= __("Lỗi kết nối") ?>'});
            $btn.prop('disabled', false).html('<i class="ri-save-line me-1"></i><?= __("Lưu tất cả") ?>');
        });
    });

    // Scan keys
    $('#btn-scan-keys').on('click', function(){
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin me-1"></i><?= __("Đang quét...") ?>');

        $.post(ajaxUrl, {
            request_name: 'scan_keys',
            csrf_token: csrfToken
        }, function(res){
            if (res.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: res.msg,
                    html: '<?= __("Tổng") ?>: <strong>' + res.total_scanned + '</strong> key<br><?= __("Mới") ?>: <strong>' + res.new_keys + '</strong> key',
                    timer: 3000
                }).then(function(){ location.reload(); });
            } else {
                Swal.fire({icon: 'error', title: 'Error', text: res.msg});
            }
            $btn.prop('disabled', false).html('<i class="ri-radar-line me-1"></i><?= __("Quét key") ?>');
        }, 'json').fail(function(){
            Swal.fire({icon: 'error', title: 'Error', text: '<?= __("Lỗi kết nối") ?>'});
            $btn.prop('disabled', false).html('<i class="ri-radar-line me-1"></i><?= __("Quét key") ?>');
        });
    });

    // Ctrl+S
    $(document).on('keydown', function(e){
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            $('#btn-save-all').click();
        }
    });
});
</script>

<?php
    // End translation editor sub-page
    exit;
}

// ====== MAIN PAGE: Language List ======
$page_title = __('Quản lý dịch');

$languages = $ToryHub->get_list_safe("SELECT * FROM `languages` ORDER BY `lang_default` DESC, `id` ASC", []);

// Count translation stats per language
$baseDir = realpath(__DIR__ . '/../../../');
$scanPatterns = [
    $baseDir . '/resources/views/admin/*.php',
    $baseDir . '/resources/views/customer/*.php',
    $baseDir . '/resources/views/staffcn/*.php',
    $baseDir . '/resources/views/staffvn/*.php',
    $baseDir . '/resources/views/common/*.php',
];
$allKeys = [];
foreach ($scanPatterns as $pattern) {
    $files = glob($pattern);
    if (!$files) continue;
    foreach ($files as $file) {
        $content = file_get_contents($file);
        preg_match_all("/__\\(['\"](.+?)['\"]\\)/", $content, $matches);
        foreach ($matches[1] as $key) {
            $allKeys[$key] = true;
        }
    }
}
$totalKeys = count($allKeys);

$langStats = [];
foreach ($languages as $l) {
    $count = $ToryHub->get_row_safe("SELECT COUNT(*) as cnt FROM `translate` WHERE `lang_id` = ?", [$l['id']]);
    $langStats[$l['id']] = intval($count['cnt'] ?? 0);
}

require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
?>
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= $page_title ?></h4>
                </div>
            </div>
        </div>

        <!-- Language List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h5 class="card-title mb-0 flex-grow-1"><?= __('Danh sách ngôn ngữ') ?></h5>
                        <button type="button" class="btn btn-primary" id="btn-add-lang">
                            <i class="ri-add-line me-1"></i><?= __('Thêm ngôn ngữ') ?>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">#</th>
                                        <th><?= __('Ngôn ngữ') ?></th>
                                        <th style="width:80px;"><?= __('Mã') ?></th>
                                        <th style="width:100px;"><?= __('Icon') ?></th>
                                        <th style="width:120px;"><?= __('Bản dịch') ?></th>
                                        <th style="width:100px;"><?= __('Mặc định') ?></th>
                                        <th style="width:100px;"><?= __('Trạng thái') ?></th>
                                        <th style="width:280px;"><?= __('Hành động') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($languages as $idx => $lang): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td class="fw-medium"><?= htmlspecialchars($lang['name']) ?></td>
                                        <td><code><?= htmlspecialchars($lang['lang']) ?></code></td>
                                        <td><span style="font-size:24px;"><?= $flagMap[$lang['lang']] ?? '' ?></span></td>
                                        <td>
                                            <?php
                                            $cnt = $langStats[$lang['id']] ?? 0;
                                            $pct = $totalKeys > 0 ? round($cnt / $totalKeys * 100) : 0;
                                            if ($lang['lang_default']):
                                            ?>
                                                <span class="text-muted fs-12"><?= __('Gốc') ?></span>
                                            <?php else: ?>
                                                <span class="<?= $pct == 100 ? 'text-success' : ($pct > 0 ? 'text-primary' : 'text-danger') ?>"><?= $cnt ?>/<?= $totalKeys ?></span>
                                                <small class="text-muted">(<?= $pct ?>%)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($lang['lang_default']): ?>
                                                <span class="badge bg-success"><?= __('Mặc định') ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($lang['status']): ?>
                                                <span class="badge bg-success"><?= __('Hiển thị') ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><?= __('Ẩn') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <?php if (!$lang['lang_default']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info btn-set-default" data-id="<?= $lang['id'] ?>" data-name="<?= htmlspecialchars($lang['name']) ?>">
                                                    <i class="ri-checkbox-circle-line me-1"></i><?= __('Mặc định') ?>
                                                </button>
                                                <?php endif; ?>
                                                <?php if (!$lang['lang_default']): ?>
                                                <a href="<?= base_url('admin/translation-manage') ?>&lang_id=<?= $lang['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="ri-translate-2 me-1"></i><?= __('Dịch') ?>
                                                </a>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning btn-edit-lang" data-id="<?= $lang['id'] ?>" data-name="<?= htmlspecialchars($lang['name']) ?>" data-lang="<?= htmlspecialchars($lang['lang']) ?>" data-status="<?= $lang['status'] ?>">
                                                    <i class="ri-pencil-line me-1"></i><?= __('Sửa') ?>
                                                </button>
                                                <?php if (!$lang['lang_default']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-lang" data-id="<?= $lang['id'] ?>" data-name="<?= htmlspecialchars($lang['name']) ?>">
                                                    <i class="ri-delete-bin-line me-1"></i><?= __('Xóa') ?>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($languages)): ?>
                                    <tr><td colspan="8" class="text-center text-muted py-4"><?= __('Chưa có ngôn ngữ nào') ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Language Modal -->
        <div class="modal fade" id="modalLang" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalLangTitle"><?= __('Thêm ngôn ngữ') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="lang-edit-id" value="">
                        <div class="mb-3">
                            <label class="form-label"><?= __('Tên ngôn ngữ') ?></label>
                            <input type="text" class="form-control" id="lang-name" placeholder="VD: Tiếng Nhật">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('Mã ngôn ngữ') ?></label>
                            <input type="text" class="form-control" id="lang-code" placeholder="VD: ja" maxlength="5">
                            <small class="text-muted"><?= __('Mã ISO 639-1 (2 ký tự): vi, zh, en, ja, ko...') ?></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('Trạng thái') ?></label>
                            <select class="form-select" id="lang-status">
                                <option value="1"><?= __('Hiển thị') ?></option>
                                <option value="0"><?= __('Ẩn') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __('Hủy') ?></button>
                        <button type="button" class="btn btn-primary" id="btn-save-lang"><?= __('Lưu') ?></button>
                    </div>
                </div>
            </div>
        </div>

<?php require_once(__DIR__.'/footer.php'); ?>

<script>
$(function(){
    var csrfToken = '<?= (new Csrf())->get_token_value() ?>';
    var ajaxUrl = '<?= base_url("ajaxs/admin/translations.php") ?>';
    var modal = new bootstrap.Modal(document.getElementById('modalLang'));

    // Add language
    $('#btn-add-lang').on('click', function(){
        $('#lang-edit-id').val('');
        $('#lang-name').val('');
        $('#lang-code').val('').prop('disabled', false);
        $('#lang-status').val('1');
        $('#modalLangTitle').text('<?= __("Thêm ngôn ngữ") ?>');
        modal.show();
    });

    // Edit language
    $(document).on('click', '.btn-edit-lang', function(){
        $('#lang-edit-id').val($(this).data('id'));
        $('#lang-name').val($(this).data('name'));
        $('#lang-code').val($(this).data('lang')).prop('disabled', true);
        $('#lang-status').val($(this).data('status'));
        $('#modalLangTitle').text('<?= __("Sửa ngôn ngữ") ?>');
        modal.show();
    });

    // Save language
    $('#btn-save-lang').on('click', function(){
        var editId = $('#lang-edit-id').val();
        var name = $('#lang-name').val().trim();
        var code = $('#lang-code').val().trim();
        var status = $('#lang-status').val();

        if (!name || !code) {
            Swal.fire({icon: 'warning', title: '<?= __("Vui lòng nhập đầy đủ thông tin") ?>'});
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.post(ajaxUrl, {
            request_name: editId ? 'edit_language' : 'add_language',
            id: editId,
            name: name,
            lang: code,
            status: status,
            csrf_token: csrfToken
        }, function(res){
            if (res.status === 'success') {
                modal.hide();
                Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
            } else {
                Swal.fire({icon: 'error', title: 'Error', text: res.msg});
            }
            $btn.prop('disabled', false);
        }, 'json').fail(function(){
            Swal.fire({icon: 'error', title: 'Error', text: '<?= __("Lỗi kết nối") ?>'});
            $btn.prop('disabled', false);
        });
    });

    // Set default
    $(document).on('click', '.btn-set-default', function(){
        var id = $(this).data('id');
        var name = $(this).data('name');
        Swal.fire({
            title: '<?= __("Đặt mặc định") ?>?',
            text: '<?= __("Đặt") ?> "' + name + '" <?= __("làm ngôn ngữ mặc định") ?>?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __("Đồng ý") ?>',
            cancelButtonText: '<?= __("Hủy") ?>'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, {
                    request_name: 'set_default',
                    id: id,
                    csrf_token: csrfToken
                }, function(res){
                    if (res.status === 'success') {
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });

    // Delete language
    $(document).on('click', '.btn-delete-lang', function(){
        var id = $(this).data('id');
        var name = $(this).data('name');
        Swal.fire({
            title: '<?= __("Xóa ngôn ngữ") ?>?',
            html: '<?= __("Xóa") ?> "<strong>' + name + '</strong>" <?= __("và tất cả bản dịch liên quan") ?>?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: '<?= __("Xóa") ?>',
            cancelButtonText: '<?= __("Hủy") ?>'
        }).then(function(result){
            if (result.isConfirmed) {
                $.post(ajaxUrl, {
                    request_name: 'delete_language',
                    id: id,
                    csrf_token: csrfToken
                }, function(res){
                    if (res.status === 'success') {
                        Swal.fire({icon: 'success', title: res.msg, timer: 1500, showConfirmButton: false}).then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon: 'error', title: 'Error', text: res.msg});
                    }
                }, 'json');
            }
        });
    });
});
</script>
