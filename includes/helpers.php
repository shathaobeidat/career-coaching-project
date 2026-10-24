<?php

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit();
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid() {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

function flash($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_pull() {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function initials($name) {
    $parts = preg_split('/\s+/', trim((string) $name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out !== '' ? $out : '?';
}

function status_badge($status) {
    return '<span class="status ' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}

function money($amount) {
    return '$' . number_format((float) $amount, 2);
}

function stars($rating) {
    $rating = max(0, min(5, (int) round($rating)));
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

/** Renders a list of validation errors (or nothing). */
function error_list(array $errors) {
    if (!$errors) {
        return '';
    }
    $html = '<div class="alert alert-error"><ul>';
    foreach ($errors as $err) {
        $html .= '<li>' . e($err) . '</li>';
    }
    return $html . '</ul></div>';
}

function paginate(array $items, $perPage = 10) {
    $total = count($items);
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
    return [
        'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
        'page'  => $page,
        'pages' => $pages,
        'total' => $total,
    ];
}

function pagination_nav($page, $pages) {
    if ($pages <= 1) {
        return '';
    }
    $link = function ($p, $label, $class = '') {
        $query = $_GET;
        $query['page'] = $p;
        return '<a' . ($class ? ' class="' . $class . '"' : '') . ' href="?' . e(http_build_query($query)) . '">' . $label . '</a>';
    };

    $html = '<nav class="pagination" aria-label="Pages">';
    $html .= $page > 1 ? $link($page - 1, '← Previous') : '<span class="gap">← Previous</span>';

    $shown = array_unique(array_merge([1, $pages], range(max(1, $page - 2), min($pages, $page + 2))));
    sort($shown);
    $last = 0;
    foreach ($shown as $p) {
        if ($p - $last > 1) {
            $html .= '<span class="gap">…</span>';
        }
        $html .= $p === $page ? '<span class="current" aria-current="page">' . $p . '</span>' : $link($p, (string) $p);
        $last = $p;
    }

    $html .= $page < $pages ? $link($page + 1, 'Next →') : '<span class="gap">Next →</span>';
    return $html . '</nav>';
}


function require_csrf($back) {
    if (!csrf_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect($back);
    }
}


function flash_result(array $res, $successMessage) {
    $res['ok'] ? flash('success', $successMessage) : flash('error', implode(' ', $res['errors']));
}

function pick($value, array $allowed, $default) {
    return in_array($value, $allowed, true) ? $value : $default;
}


function require_profile() {
    global $coachId;
    if (!$coachId) {
        flash('info', 'Please complete your coach profile first.');
        redirect('coach-profile.php');
    }
}


function booking_actions(array $b, $next = 'bookings') {
    $buttons = [];
    if ($b['status'] === 'pending') {
        $buttons[] = ['confirm', 'Confirm', 'btn btn-sm btn-xs', null];
        $buttons[] = ['cancel', 'Decline', 'btn btn-sm btn-xs btn-danger', 'Decline this booking request?'];
    } elseif ($b['status'] === 'confirmed') {
        if (strtotime($b['start_at']) <= time()) {
            $buttons[] = ['complete', 'Mark completed', 'btn btn-sm btn-xs', null];
        }
        $buttons[] = ['cancel', 'Cancel', 'btn btn-sm btn-xs btn-danger', 'Cancel this confirmed session?'];
    }
    if (!$buttons) {
        return '<span class="muted">—</span>';
    }
    $html = '<div class="actions">';
    foreach ($buttons as [$action, $label, $class, $confirm]) {
        $html .= '<form method="post" action="bookings.php"'
              . ($confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\');"' : '') . '>'
              . csrf_field()
              . '<input type="hidden" name="booking_id" value="' . (int) $b['id'] . '">'
              . '<input type="hidden" name="action" value="' . e($action) . '">'
              . '<input type="hidden" name="next" value="' . e($next) . '">'
              . '<input type="hidden" name="status" value="' . e($_GET['status'] ?? 'all') . '">'
              . '<button type="submit" class="' . e($class) . '">' . e($label) . '</button></form>';
    }
    return $html . '</div>';
}
