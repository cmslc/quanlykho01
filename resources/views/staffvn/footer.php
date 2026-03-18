        </div><!-- end container-fluid -->
    </div><!-- end page-content -->

    <footer class="footer">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <span class="text-muted fs-13">Copyright &copy; <?= date('Y') ?> ToryHub - <?= __('Quản lý kho vận') ?></span>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-muted fs-13">v1.0</span>
                </div>
            </div>
        </div>
    </footer>

</div><!-- end main-content -->
</div><!-- end layout-wrapper -->

<!-- Vendor Scripts -->
<script src="<?= base_url('public/material/assets/libs/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('public/material/assets/libs/simplebar/simplebar.min.js') ?>"></script>
<script src="<?= base_url('public/material/assets/libs/node-waves/waves.min.js') ?>"></script>
<script src="<?= base_url('public/material/assets/libs/feather-icons/feather.min.js') ?>"></script>
<script src="<?= base_url('public/material/assets/js/pages/plugins/lord-icon-2.1.0.js') ?>"></script>
<script src="<?= base_url('public/material/assets/js/plugins.js') ?>"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<?= $body['footer'] ?? '' ?>

<!-- App JS (must be last) -->
<script src="<?= base_url('public/material/assets/js/app.js') ?>"></script>
<script>
$(document).ready(function(){
    if($('.data-table').length){
        $('.data-table').DataTable({
            responsive: true,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json' }
        });
    }
});
</script>
</body>
</html>
