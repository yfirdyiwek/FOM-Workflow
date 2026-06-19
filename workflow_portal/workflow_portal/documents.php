<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_once __DIR__ . '/includes/layout.php';

$pdo  = db();
$user = current_user();

// Filters
$filterCommittee = (int) ($_GET['committee'] ?? 0);
$filterCategory  = trim($_GET['category'] ?? '');

// Build WHERE — non-admins only see documents from their committees
$where  = 'WHERE d.is_active = 1';
$params = [];

if (!user_is_adminish()) {
    $committeeIds = user_committee_ids((int) $user['id']);
    if (!$committeeIds) $committeeIds = [0];
    $placeholders = implode(',', array_fill(0, count($committeeIds), '?'));
    $where  .= " AND a.assigned_committee_id IN ($placeholders)";
    $params  = $committeeIds;
}

if ($filterCommittee > 0) {
    $where   .= ' AND a.assigned_committee_id = ?';
    $params[] = $filterCommittee;
}
if ($filterCategory !== '') {
    $where   .= ' AND d.category = ?';
    $params[] = $filterCategory;
}

$stmt = $pdo->prepare("SELECT d.*,
        u.display_name AS uploader_name,
        a.title AS assignment_title, a.id AS assignment_id,
        c.name AS committee_name, c.short_code,
        st.title AS subtask_title
    FROM documents d
    LEFT JOIN assignments a  ON a.id  = d.assignment_id
    LEFT JOIN committees c   ON c.id  = a.assigned_committee_id
    LEFT JOIN users u        ON u.id  = d.uploaded_by_user_id
    LEFT JOIN assignment_subtasks st ON st.id = d.subtask_id
    $where
    ORDER BY d.uploaded_at DESC
    LIMIT 100");
$stmt->execute($params);
$documents = $stmt->fetchAll();

$committees  = $pdo->query('SELECT id, name, short_code FROM committees WHERE is_active = 1 ORDER BY name')->fetchAll();
$docCategories = ['Proposal','Research','Communications Draft','Financial Document',
                  'Meeting Notes','Reference','Final Deliverable','Other'];

render_header('Documents', '', 'documents', null);
?>

<article class="card section">
  <div style="display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;margin-bottom:20px;">
    <span></span>
    <h2 class="section-title" style="font-size:1.45rem;margin:0;text-align:center;white-space:nowrap;">Documents</h2>
    <span></span>
  </div>

  <!-- Filters -->
  <form method="get" class="filters" style="margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <select name="committee" onchange="this.form.submit()" style="padding:8px 12px;border-radius:10px;border:1px solid var(--border-subtle);background:var(--surface-input);color:var(--text-primary);">
      <option value="">All committees</option>
      <?php foreach ($committees as $c): ?>
        <option value="<?= e((string) $c['id']) ?>" <?= $filterCommittee === (int) $c['id'] ? 'selected' : '' ?>>
          <?= e($c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <select name="category" onchange="this.form.submit()" style="padding:8px 12px;border-radius:10px;border:1px solid var(--border-subtle);background:var(--surface-input);color:var(--text-primary);">
      <option value="">All categories</option>
      <?php foreach ($docCategories as $cat): ?>
        <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($filterCommittee || $filterCategory): ?>
      <a class="mini-btn" href="documents.php">Clear filters</a>
    <?php endif; ?>
  </form>

  <!-- Document list -->
  <?php if ($documents): ?>
    <div class="panel-list">
      <?php foreach ($documents as $doc): ?>
        <div class="panel-item" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;">
          <div style="min-width:0;flex:1;">
            <strong style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
              <?= e($doc['title']) ?>
              <?php if ($doc['is_final_version']): ?>
                <span class="badge" style="background:#dcfce7;color:#166534;font-size:.72rem;">Final</span>
              <?php endif; ?>
              <?php if ($doc['category']): ?>
                <span class="badge" style="font-size:.72rem;"><?= e($doc['category']) ?></span>
              <?php endif; ?>
            </strong>
            <p style="margin:0 0 3px;font-size:.88rem;color:var(--text-secondary);">
              <?php if ($doc['assignment_title']): ?>
                <a class="muted-link" href="assignment-detail.php?id=<?= e((string) $doc['assignment_id']) ?>"><?= e($doc['assignment_title']) ?></a>
                <?php if ($doc['short_code']): ?>
                  <span class="badge committee-badge <?= e(strtolower($doc['short_code'])) ?>" style="font-size:.72rem;"><?= e($doc['short_code']) ?></span>
                <?php endif; ?>
              <?php endif; ?>
              <?php if ($doc['subtask_title']): ?>
                · Task: <?= e($doc['subtask_title']) ?>
              <?php endif; ?>
            </p>
            <p style="margin:0;font-size:.84rem;color:var(--text-secondary);">
              Uploaded by <?= e($doc['uploader_name'] ?: 'Unknown') ?>
              · <?= e((new DateTime($doc['uploaded_at']))->format('M j, Y g:i A')) ?>
              · <?= e(strtoupper($doc['file_extension'] ?? '')) ?>
              · <?= e(number_format(($doc['file_size'] ?? 0) / 1024, 0)) ?>KB
            </p>
            <?php if ($doc['description']): ?>
              <p style="margin:3px 0 0;font-size:.84rem;color:var(--text-secondary);font-style:italic;"><?= e($doc['description']) ?></p>
            <?php endif; ?>
          </div>
          <a class="mini-btn" href="download.php?id=<?= e((string) $doc['id']) ?>" style="flex-shrink:0;">Download</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="panel-item">
      <strong>No documents found</strong>
      <p>Documents uploaded on Assignment Detail pages will appear here. <?php if ($filterCommittee || $filterCategory): ?>Try clearing the filters.<?php endif; ?></p>
    </div>
  <?php endif; ?>
</article>

<?php render_footer(); ?>