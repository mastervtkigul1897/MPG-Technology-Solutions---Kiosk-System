<?php
/** @var string $title */
http_response_code(404);
?>
<p class="text-muted">Page not found.</p>
<a href="<?= e(url('/')) ?>" class="btn btn-primary">Home</a>
<?php require dirname(__DIR__).'/partials/footer_credit.php'; ?>
