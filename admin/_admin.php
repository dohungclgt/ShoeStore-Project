<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

function admin_boot(string $active, string $title): array
{
    $user = require_role(['Super Admin', 'Admin', 'Staff']);
    render_header($title, true);
    echo '<div class="admin-wrap">';
    admin_sidebar($active);
    echo '<main class="p-4">';
    return $user;
}

function admin_end(): void
{
    echo '</main></div>';
    render_footer();
}

function order_status_options(string $current): string
{
    $statuses=['pending_payment','waiting_confirm','waiting_pickup','packing','shipping','delivered','completed','cancelled','returned'];
    $html='';
    foreach($statuses as $s) $html.='<option value="'.e($s).'" '.($s===$current?'selected':'').'>'.e(order_status_label($s)).'</option>';
    return $html;
}
