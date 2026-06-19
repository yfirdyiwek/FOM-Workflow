<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

function subtask_status_badge_class(string $status): string
{
    return match ($status) {
        'completed'   => 'status-completed',
        'in_progress' => 'status-progress',
        'waiting'     => 'status-blocked',
        default       => 'status-assigned',
    };
}

function task_status_label(string $status): string
{
    return match ($status) {
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'waiting'     => 'Waiting',
        'completed'   => 'Completed',
        default       => ucwords(str_replace('_', ' ', $status)),
    };
}

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) { http_response_code(404); exit('Assignment not found.'); }

$pdo  = db();

$stmt = $pdo->prepare('SELECT a.*, c.name AS committee_name, c.short_code,
        u.display_name AS lead_name,
        creator.display_name AS created_by_name,
        updater.display_name AS updated_by_name
    FROM assignments a
    JOIN committees c ON c.id = a.assigned_committee_id
    LEFT JOIN users u       ON u.id = a.lead_user_id
    LEFT JOIN users creator ON creator.id = a.created_by_user_id
    LEFT JOIN users updater ON updater.id = a.updated_by_user_id
    WHERE a.id = ? LIMIT 1');
$stmt->execute([$id]);
$assignment = $stmt->fetch();

if (!$assignment || !user_can_view_assignment($assignment)) {
    http_response_code(404);
    exit('Assignment not found or not visible to your account.');
}

require __DIR__ . '/partials/detail-post-handlers.php';
require __DIR__ . '/partials/detail-data.php';

render_header('Assignment Detail', '', 'assignments', null);
?>

<style>
.assignment-detail-layout{display:flex;flex-direction:column;gap:24px}
.assignment-bottom-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px;align-items:start}
.assignment-theme-sc{background:#deebf5;border-color:#b7d0e5}
.assignment-theme-cc{background:#daf4f4;border-color:#a9dddd}
.assignment-theme-ardc{background:#dff1e7;border-color:#b8d7c5}
.assignment-theme-fc{background:#f4e0e5;border-color:#dfb8c2}
.assignment-theme-default{background:#f3f4f6;border-color:#d9dde3}
.assignment-detail-layout .detail-card[class*="assignment-theme"] .panel-item,
.assignment-detail-layout .detail-card[class*="assignment-theme"] .meta-box,
.assignment-detail-layout .detail-card[class*="assignment-theme"] .field input,
.assignment-detail-layout .detail-card[class*="assignment-theme"] .field select,
.assignment-detail-layout .detail-card[class*="assignment-theme"] .field textarea{background:rgba(255,255,255,.78)}
.assignment-collapsible{border:1px solid rgba(31,41,55,.08);border-radius:14px;background:rgba(255,255,255,.5);overflow:hidden}
.assignment-collapsible summary{list-style:none;cursor:pointer;padding:14px 16px;font-weight:700;display:flex;align-items:center;justify-content:space-between}
.assignment-collapsible summary::-webkit-details-marker{display:none}
.assignment-collapsible summary::after{content:'▾';font-size:.95rem;color:#475569}
.assignment-collapsible[open] summary::after{content:'▴'}
.assignment-collapsible .collapsible-body{padding:0 16px 16px}
.task-item{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.task-expandable summary::-webkit-details-marker{display:none}
.task-expandable summary:hover{background:color-mix(in srgb,var(--surface-soft) 50%,transparent)}
.task-item-left{flex:1;min-width:0}
.task-item-left strong{display:block;margin-bottom:3px}
.task-item-left p{margin:0;font-size:.88rem;color:var(--text-secondary);line-height:1.4}
.task-item-right{display:flex;gap:8px;flex-wrap:wrap;align-items:center;flex-shrink:0}
@media(max-width:1220px){.assignment-bottom-grid{grid-template-columns:1fr}.task-item{flex-direction:column}.task-item-right{justify-content:flex-start}}
.header-center{text-align:left}
.app.sidebar-closed .header-center{padding-left:48px}
</style>

<div class="assignment-detail-layout">

  <!-- Top card -->
  <article class="card detail-card <?= e($themeClass) ?>">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
      <div style="flex:1;min-width:0;">
        <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-secondary);margin-bottom:4px;">Assignment Title</div>
        <h2 style="margin:0 0 10px;"><?= e($assignment['title']) ?></h2>
        <div class="meta-line" style="flex-wrap:wrap;gap:10px;align-items:center;">
          <span class="badge committee-badge <?= e(committee_badge_class($assignment['short_code'])) ?>"><?= e($assignment['committee_name']) ?></span>
          <span class="<?= e(status_badge_class($assignment['status'])) ?>" style="font-weight:700;font-size:.84rem;"><?= e(status_label($assignment['status'])) ?></span>
          <span style="font-size:.88rem;color:var(--text-secondary);">Lead: <strong style="color:var(--text-primary);"><?= e($assignment['lead_name'] ?: 'Unassigned') ?></strong></span>
          <span style="font-size:.88rem;color:var(--text-secondary);">Due: <strong style="color:var(--text-primary);"><?= e(format_date($assignment['due_date'])) ?></strong></span>
          <span style="font-size:.88rem;color:var(--text-secondary);">Priority: <strong class="priority <?= e($assignment['priority']) ?>"><?= e(priority_label($assignment['priority'])) ?></strong></span>
        </div>
      </div>
      <div style="display:flex;gap:8px;flex-shrink:0;align-items:flex-start;">
        <?php if (user_can_edit_assignment($assignment)): ?>
          <button class="mini-btn" type="button" data-modal-open="edit-assignment-modal">Edit</button>
        <?php endif; ?>
        <button class="mini-btn" type="button" data-modal-open="view-assignment-modal">View</button>
      </div>
    </div>
  </article>

<?php require __DIR__ . '/partials/detail-tasks.php'; ?>
<?php require __DIR__ . '/partials/detail-docs-history.php'; ?>
<?php require __DIR__ . '/partials/detail-modals.php'; ?>

<?php render_footer(); ?>
