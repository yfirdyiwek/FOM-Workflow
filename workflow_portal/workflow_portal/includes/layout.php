<?php
require_once __DIR__ . '/auth.php';

function nav_link(string $href, string $label, string $activeKey, string $currentKey, string $dotClass = ''): string
{
    $active = $activeKey === $currentKey ? ' active' : '';
    $dot = $dotClass ? '<span class="dot ' . e($dotClass) . '"></span>' : '<span class="dot"></span>';
    return '<a href="' . e(base_url($href)) . '" class="nav-link' . $active . '">' . $dot . e($label) . '</a>';
}

function render_header(string $title, string $subtitle, string $activeNav, ?array $primaryAction = null): void
{
    $user = current_user();
    $initials = '';

    if ($user) {
        $source = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        if ($source === '') {
            $source = $user['display_name'] ?: $user['username'];
        }

        preg_match_all('/\b\p{L}/u', $source, $matches);
        $initials = strtoupper(implode('', array_slice($matches[0], 0, 2))) ?: 'U';
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>';
    echo '<title>' . e($title) . ' — ' . e(APP_TAGLINE) . '</title>';
    echo '<link rel="stylesheet" href="' . e(base_url('assets/styles.css')) . '"/>';
    echo '</head><body>';
    echo '<div class="app">';
    echo '<aside class="sidebar">';
    echo '<div class="brand"><div class="brand-mark"><img data-brand-logo alt="" /></div><div class="brand-text"><strong data-site-title>' . e(APP_NAME) . '</strong><span data-site-tagline>' . e(APP_TAGLINE) . '</span></div></div>';

    echo '<div class="nav-group-label">General</div><nav class="nav">';
    echo nav_link('dashboard.php', 'Home', 'home', $activeNav);
    echo nav_link('documents.php', 'Documents', 'documents', $activeNav);
    echo nav_link('reports.php', 'Reports', 'reports', $activeNav);
    echo '</nav>';

    echo '<div class="nav-group-label">Committees</div><nav class="nav">';
    echo nav_link('sc-dashboard.php', 'Steering Committee', 'sc', $activeNav, 'sc');
    echo nav_link('committee-dashboard.php?code=CC', 'Communications', 'cc', $activeNav, 'cc');
    echo nav_link('committee-dashboard.php?code=ARDC', 'ARDC', 'ardc', $activeNav, 'ardc');
    echo nav_link('committee-dashboard.php?code=FC', 'Finance', 'fc', $activeNav, 'fc');
    echo '</nav>';

    echo '<div class="nav-group-label">Administration</div><nav class="nav">';
    echo nav_link('users.php', 'Users / Members', 'users', $activeNav);
    echo nav_link('activity-log.php', 'Activity Log', 'activity', $activeNav);
    echo '</nav>';

    $displayName = $user ? e(user_display_name()) : 'Guest';
    $userEmail   = $user ? e($user['email'] ?? '') : '';
    echo '<div class="sidebar-footer">';
    echo '<div class="sidebar-user"><strong>' . $displayName . '</strong>';
    if ($userEmail) echo '<span>' . $userEmail . '</span>';
    echo '</div>';
    echo '<a href="' . e(base_url('logout.php')) . '" class="btn-logout">⏻ Log Out</a>';
    echo '</div>';
    echo '</aside>';

    echo '<main class="main">';
    echo '<header class="header">';

    // Left: hamburger only
    echo '<div class="header-left">';
    echo '<button class="icon-btn header-menu-toggle" type="button" data-sidebar-toggle aria-expanded="true">';
    echo '<span class="hamburger" aria-hidden="true"><span></span><span></span><span></span></span>';
    echo '<span class="sr-only" data-sidebar-label>Close Menu</span>';
    echo '</button>';
    echo '</div>';

    // Center: title only (subtitle suppressed)
    echo '<div class="header-center"><h1>' . e($title) . '</h1></div>';

    // Right: search + theme + avatar all on one row
    echo '<div class="header-right">';
    echo '<label class="search" aria-label="Quick search"><span>🔎</span><input type="text" placeholder="Search coming later" disabled/></label>';
    echo '<div class="theme-switch" aria-label="Color theme">';
    echo '<button class="theme-toggle theme-toggle--icon" type="button" data-set-theme="light" aria-label="Light mode" title="Light mode">✦</button>';
    echo '<button class="theme-toggle theme-toggle--icon" type="button" data-set-theme="dark" aria-label="Dark mode" title="Dark mode">☾</button>';
    echo '</div>';
    echo '<div class="avatar" aria-label="User profile" title="' . e(user_display_name()) . '">' . e($initials) . '</div>';
    echo '</div>';

    echo '</header>';

    echo '<div class="content">';

    if ($error = flash('error')) {
        if (!(current_user() && $error === 'An admin user already exists. Please log in instead.')) {
            echo '<div class="alert-card returned"><strong>Error</strong><p>' . e($error) . '</p></div>';
        }
    }

    if ($success = flash('success')) {
        echo '<div class="toast-success" id="flash-toast" role="status" aria-live="polite">✓ ' . e($success) . '</div>';
    }
}

function render_footer(): void
{
    echo '</div></main></div>';
    echo '<script src="' . e(base_url('assets/config.js')) . '"></script>';
    echo '<script src="' . e(base_url('assets/app.js')) . '"></script>';
    echo '</body></html>';
}