<?php
$successMessage = session_flash('success') ?: session_flash('status');
$errors = session_flash('errors');
if (! is_array($errors)) {
    $errors = $errors ? [$errors] : [];
}
?>
<?php if ($successMessage || $errors !== []): ?>
<script>
(() => {
    const successMessage = <?= json_embed($successMessage) ?>;
    const errorMessages = <?= json_embed(array_values($errors)) ?>;
    const run = () => {
        if (typeof Swal === 'undefined') return;
        if (successMessage) {
            Swal.fire({ icon: 'success', title: 'Success', text: successMessage, confirmButtonColor: '#198754' });
        }
        if (errorMessages.length) {
            Swal.fire({
                icon: 'error',
                title: 'Please fix the following',
                html: `<ul style="text-align:left; margin:0; padding-left:1.2rem;">${errorMessages.map((e) => `<li>${e}</li>`).join('')}</ul>`,
                confirmButtonColor: '#dc3545',
            });
        }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run, { once: true });
    else run();
})();
</script>
<?php endif; ?>
