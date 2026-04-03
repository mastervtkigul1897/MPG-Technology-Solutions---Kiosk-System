<?php
/** @var array{current_page:int,last_page:int,total:int,per_page:int} $pagination */
$baseUrl = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$query = $_GET;
unset($query['page']);
$q = http_build_query($query);
$prefix = $q !== '' ? '?'.$q.'&' : '?';
?>
<?php if (($pagination['last_page'] ?? 1) > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm mb-0">
        <?php if ($pagination['current_page'] > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= e($baseUrl.$prefix.'page='.($pagination['current_page'] - 1)) ?>">Prev</a></li>
        <?php endif; ?>
        <li class="page-item disabled"><span class="page-link">Page <?= (int) $pagination['current_page'] ?> / <?= (int) $pagination['last_page'] ?></span></li>
        <?php if ($pagination['current_page'] < $pagination['last_page']): ?>
            <li class="page-item"><a class="page-link" href="<?= e($baseUrl.$prefix.'page='.($pagination['current_page'] + 1)) ?>">Next</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>
