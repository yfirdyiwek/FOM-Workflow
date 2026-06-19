<?php
// ── Data fetching ───────────────────────────────────────────────────────────

// All committees (for edit modal)
$allCommittees = $pdo->query('SELECT id, name, short_code FROM committees WHERE is_active = 1 ORDER BY name')->fetchAll();

// All active users with their committee memberships (for edit modal lead dropdown)
$allMemberships = $pdo->query('SELECT u.id, u.display_name, m.committee_id
    FROM users u
    JOIN user_committee_memberships m ON m.user_id = u.id
    WHERE u.is_active = 1 AND u.role_level != "superadmin"
    ORDER BY u.display_name ASC')->fetchAll();

// Committee members for task modal (flat list — members + support of this committee, no superadmin)
$stmt = $pdo->prepare('SELECT DISTINCT u.id, u.display_name
    FROM users u
    JOIN user_committee_memberships m ON m.user_id = u.id
    WHERE m.committee_id = ? AND u.is_active = 1 AND u.role_level != "superadmin"
    ORDER BY u.display_name ASC');
$stmt->execute([(int) $assignment['assigned_committee_id']]);
$committeeMembers = $stmt->fetchAll();

// Supporting members (for display in collapsible meta)
$stmt = $pdo->prepare('SELECT asm.id AS support_id, u.id AS user_id, u.display_name
    FROM assignment_supporting_members asm
    JOIN users u ON u.id = asm.user_id
    WHERE asm.assignment_id = ? ORDER BY u.display_name ASC');
$stmt->execute([$id]);
$supportingMembers = $stmt->fetchAll();
$supportCount = count($supportingMembers);

// Tasks
$stmt = $pdo->prepare('SELECT st.*, u.display_name AS assigned_name, creator.display_name AS created_by_name
    FROM assignment_subtasks st
    LEFT JOIN users u       ON u.id = st.assigned_user_id
    LEFT JOIN users creator ON creator.id = st.created_by_user_id
    WHERE st.assignment_id = ?
    ORDER BY LOWER(st.title) ASC, st.created_at ASC');
$stmt->execute([$id]);
$subtasks = $stmt->fetchAll();

// Sort tasks by title using numeric-aware sorting.
// This keeps numbered task titles in human order: 1, 2, 10 — not 1, 10, 2.
// Numbered titles are placed before unnumbered titles so the task list can be controlled by prefixing titles with numbers.
usort($subtasks, static function ($a, $b) {
    $titleA = trim((string) ($a['title'] ?? ''));
    $titleB = trim((string) ($b['title'] ?? ''));

    $aHasNumber = preg_match('/^\s*(\d+)/', $titleA, $matchA);
    $bHasNumber = preg_match('/^\s*(\d+)/', $titleB, $matchB);

    if ($aHasNumber && $bHasNumber) {
        $numCmp = ((int) $matchA[1]) <=> ((int) $matchB[1]);
        if ($numCmp !== 0) {
            return $numCmp;
        }
    } elseif ($aHasNumber) {
        return -1;
    } elseif ($bHasNumber) {
        return 1;
    }

    $titleCmp = strnatcasecmp($titleA, $titleB);
    if ($titleCmp !== 0) {
        return $titleCmp;
    }

    return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
});

$taskCount           = count($subtasks);
$completedTaskCount  = 0;
$inProgressTaskCount = 0;
$waitingTaskCount    = 0;
foreach ($subtasks as $st) {
    if ($st['status'] === 'completed')       $completedTaskCount++;
    elseif ($st['status'] === 'in_progress') $inProgressTaskCount++;
    elseif ($st['status'] === 'waiting')     $waitingTaskCount++;
}

// History
$stmt = $pdo->prepare('SELECT al.*, u.display_name FROM activity_log al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE (target_type = "assignment" AND target_id = ?)
       OR (target_type = "assignment_subtask" AND target_id IN
           (SELECT id FROM assignment_subtasks WHERE assignment_id = ?))
    ORDER BY created_at DESC');
$stmt->execute([$id, $id]);
$history = $stmt->fetchAll();

$themeClass = match (strtoupper((string) $assignment['short_code'])) {
    'SC'   => 'assignment-theme-sc',
    'CC'   => 'assignment-theme-cc',
    'ARDC' => 'assignment-theme-ardc',
    'FC'   => 'assignment-theme-fc',
    default => 'assignment-theme-default',
};

// Documents
$stmt = $pdo->prepare('SELECT d.*, u.display_name AS uploader_name, st.title AS subtask_title
    FROM documents d
    LEFT JOIN users u ON u.id = d.uploaded_by_user_id
    LEFT JOIN assignment_subtasks st ON st.id = d.subtask_id
    WHERE d.assignment_id = ? AND d.is_active = 1
    ORDER BY d.uploaded_at DESC');
$stmt->execute([$id]);
$documents = $stmt->fetchAll();

$docCategories = ['Proposal','Research','Communications Draft','Financial Document',
                  'Meeting Notes','Reference','Final Deliverable','Other'];

// Index documents by subtask_id for quick lookup in task cards
// Key 0 = assignment-level (no subtask)
$docsBySubtask = [];
foreach ($documents as $doc) {
    $key = $doc['subtask_id'] ? (int) $doc['subtask_id'] : 0;
    $docsBySubtask[$key][] = $doc;
}

// JSON for task modal pre-fill
$subtasksJson = json_encode(array_map(fn($st) => [
    'id'          => $st['id'],
    'title'       => $st['title'],
    'description' => $st['description'] ?? '',
    'assigned'    => $st['assigned_user_id'],
    'priority'    => $st['priority'],
    'status'      => $st['status'],
    'due_date'    => $st['due_date'] ?? '',
], $subtasks));

// JSON for edit assignment modal — memberships keyed by committee_id
$membershipsJson = json_encode($allMemberships);
