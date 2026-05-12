<?php
// ============================================================
//  STUDEX — Student Index
//  view/partials/flash_message.php
//  Dipanggil otomatis dari main.php setelah getFlash()
//  $flash = ['type' => 'success|error|warning|info', 'message' => '...']
// ============================================================

defined('STUDEX') or die('Direct access not permitted');

if (empty($flash)) return;

$type    = $flash['type']    ?? 'info';
$message = $flash['message'] ?? '';

// Map type ke class & icon
$map = [
    'success' => [
        'class' => 'alert-success',
        'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>',
    ],
    'error' => [
        'class' => 'alert-danger',
        'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>',
    ],
    'warning' => [
        'class' => 'alert-warning',
        'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>',
    ],
    'info' => [
        'class' => 'alert-info',
        'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>',
    ],
];

$config = $map[$type] ?? $map['info'];
?>

<div class="alert <?= $config['class'] ?> fade-in mb-5" role="alert" id="flashMessage">
    <?= $config['icon'] ?>
    <span><?= e($message) ?></span>
    <button class="alert-close" onclick="this.closest('.alert').remove()" aria-label="Tutup">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
    </button>
</div>

<script>
    // Auto-dismiss flash message setelah 5 detik
    (function() {
        var el = document.getElementById('flashMessage');
        if (!el) return;
        setTimeout(function() {
            el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            el.style.opacity    = '0';
            el.style.transform  = 'translateY(-8px)';
            setTimeout(function() { el && el.remove(); }, 400);
        }, 5000);
    })();
</script>