<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
include 'common.php';

$menu->title = _t('重置密码');

include 'header.php';
?>
<style>
    .typecho-table-wrap {
        padding: 50px 30px;
    }
    label:after {
        content: " *";
        color: #ed1c24;
    }
</style>
<div class="body container">
    <div class="typecho-logo">
        <h1>重置密码</h1>
    </div>

    <div class="row typecho-page-main">
        <div class="col-mb-12 col-tb-6 col-tb-offset-3 typecho-content-panel">
            <div class="typecho-table-wrap">
                <?php @$this->resetForm()->render(); ?>
            </div>
        </div>
    </div>
</div>
<?php
include __ADMIN_DIR__ . '/common-js.php';
?>
    </body>
</html>
