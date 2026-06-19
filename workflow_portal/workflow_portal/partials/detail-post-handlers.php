<?php
// ── POST handlers ──────────────────────────────────────────────────────────
if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ── Delete assignment ─────────────────────────────────────────────────────
    if ($action === 'delete_assignment') {
        if (user_role() !== 'superadmin') {
            http_response_code(403); exit('Only SuperAdmin can delete assignments.');
        }
        // Deactivate all documents linked to this assignment or its subtasks
        $pdo->prepare('UPDATE documents SET is_active = 0 WHERE assignment_id = ?')->execute([$id]);
        $pdo->prepare('UPDATE documents SET is_active = 0 WHERE subtask_id IN
            (SELECT id FROM assignment_subtasks WHERE assignment_id = ?)')->execute([$id]);
        // Log before deletion (after deletion the record is gone)
        $title = $assignment['title'];
        activity_log((int) current_user()['id'], 'assignment_deleted', 'assignment', $id,
            'Deleted assignment "' . $title . '".');
        // Delete the assignment — cascades to subtasks, supporting members, updates, handoffs
        $pdo->prepare('DELETE FROM assignments WHERE id = ?')->execute([$id]);
        flash('success', 'Assignment "' . $title . '" was permanently deleted.');
        redirect('dashboard.php');
    }

    // ── Edit assignment ────────────────────────────────────────────────────
    if ($action === 'edit_assignment') {
        if (!user_can_edit_assignment($assignment)) {
            http_response_code(403); exit('No permission.');
        }

        $allowedStatuses   = ['approved','assigned','in_progress','waiting_blocked',
                              'ready_for_review','completed','archived',
                              'pending_handoff','in_receiving_review','returned_for_revision','resubmitted'];
        $allowedPriorities = ['low','medium','high','urgent'];

        $newStatus   = $_POST['status']   ?? $assignment['status'];
        $newPriority = $_POST['priority'] ?? $assignment['priority'];
        $newDueDate  = trim($_POST['due_date'] ?? '');
        if (!in_array($newStatus,   $allowedStatuses,   true)) $newStatus   = $assignment['status'];
        if (!in_array($newPriority, $allowedPriorities, true)) $newPriority = $assignment['priority'];

        $newCommitteeId  = (int) ($_POST['assigned_committee_id'] ?? 0);
        $newLeadId       = (int) ($_POST['lead_user_id'] ?? 0);
        $newTitle        = trim($_POST['title'] ?? '');
        $newDateAssigned = trim($_POST['date_assigned'] ?? '');
        $newShortDesc    = trim($_POST['short_description'] ?? '');
        $newFullDesc     = trim($_POST['full_description'] ?? '');
        $newOrigin       = trim($_POST['origin_source'] ?? '');

        if ($newTitle === '')     { flash('error','Title is required.');     redirect('assignment-detail.php?id='.$id); }
        if ($newCommitteeId < 1) { flash('error','Committee is required.'); redirect('assignment-detail.php?id='.$id); }

        if ($newLeadId > 0) {
            $stmt2 = $pdo->prepare('SELECT u.id FROM users u
                JOIN user_committee_memberships m ON m.user_id = u.id
                WHERE u.id = ? AND m.committee_id = ? AND u.is_active = 1 LIMIT 1');
            $stmt2->execute([$newLeadId, $newCommitteeId]);
            if (!$stmt2->fetch()) { flash('error','Lead must be a member of the selected committee.'); redirect('assignment-detail.php?id='.$id); }
        }

        $pdo->prepare('UPDATE assignments SET
                title=?, assigned_committee_id=?, lead_user_id=?,
                status=?, priority=?, due_date=?, date_assigned=?,
                short_description=?, full_description=?, origin_source=?,
                updated_by_user_id=?, updated_at=NOW() WHERE id=?')
            ->execute([
                $newTitle, $newCommitteeId, $newLeadId > 0 ? $newLeadId : null,
                $newStatus, $newPriority,
                $newDueDate !== '' ? $newDueDate : null,
                $newDateAssigned !== '' ? $newDateAssigned : null,
                $newShortDesc !== '' ? $newShortDesc : null,
                $newFullDesc  !== '' ? $newFullDesc  : null,
                $newOrigin    !== '' ? $newOrigin    : null,
                (int) current_user()['id'], $id,
            ]);

        activity_log((int) current_user()['id'], 'assignment_updated', 'assignment', $id,
            'Edited assignment "'.$assignment['title'].'".');
        flash('success', 'Assignment updated.');
        redirect('assignment-detail.php?id='.$id);
    }

    // ── Save task (create or edit) ─────────────────────────────────────────
    if (in_array($action, ['create_subtask', 'edit_subtask'], true)) {
        if (!user_can_manage_subtasks($assignment)) {
            http_response_code(403); exit('No permission.');
        }

        $subtaskId   = (int) ($_POST['subtask_id'] ?? 0);
        $title       = trim($_POST['subtask_title'] ?? '');
        $assignedUid = (int) ($_POST['subtask_assigned_user_id'] ?? 0);
        $status      = $_POST['subtask_status']   ?? 'not_started';
        $priority    = $_POST['subtask_priority'] ?? 'medium';
        $dueDate     = trim($_POST['subtask_due_date'] ?? '');
        $description = trim($_POST['subtask_description'] ?? '');

        $allowedStatuses   = ['not_started','in_progress','waiting','completed'];
        $allowedPriorities = ['low','medium','high','urgent'];

        if ($title === '')     { flash('error','Task title is required.');   redirect('assignment-detail.php?id='.$id); }
        if ($assignedUid < 1)  { flash('error','Please choose an assignee.'); redirect('assignment-detail.php?id='.$id); }
        if (!in_array($status,   $allowedStatuses,   true)) $status   = 'not_started';
        if (!in_array($priority, $allowedPriorities, true)) $priority = 'medium';

        // Validate assignee is active member of this committee
        $stmt2 = $pdo->prepare('SELECT u.id, u.display_name FROM users u
            JOIN user_committee_memberships m ON m.user_id = u.id
            WHERE u.id = ? AND m.committee_id = ? AND u.is_active = 1 LIMIT 1');
        $stmt2->execute([$assignedUid, (int) $assignment['assigned_committee_id']]);
        $assignee = $stmt2->fetch();

        if (!$assignee) { flash('error','That person is not a member of this committee.'); redirect('assignment-detail.php?id='.$id); }

        $completionDate = $status === 'completed' ? date('Y-m-d') : null;
        $dueDateVal     = $dueDate !== '' ? $dueDate : null;

        // Auto-add assignee as supporting member
        $pdo->prepare('INSERT IGNORE INTO assignment_supporting_members
                (assignment_id, user_id, added_by_user_id, added_at) VALUES (?, ?, ?, NOW())')
            ->execute([$id, $assignedUid, (int) current_user()['id']]);

        if ($action === 'create_subtask') {
            $pdo->prepare('INSERT INTO assignment_subtasks
                    (assignment_id, title, description, assigned_user_id, created_by_user_id,
                     priority, status, due_date, completion_date, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())')
                ->execute([
                    $id, $title,
                    $description !== '' ? $description : null,
                    $assignedUid, (int) current_user()['id'],
                    $priority, $status, $dueDateVal, $completionDate,
                ]);
            $newId = (int) $pdo->lastInsertId();
            activity_log((int) current_user()['id'], 'task_created', 'assignment_subtask', $newId,
                'Created task "'.$title.'" under "'.$assignment['title'].'".');
            flash('success', 'Task created and assigned to '.$assignee['display_name'].'.');
        } else {
            $stmt2 = $pdo->prepare('SELECT id FROM assignment_subtasks WHERE id = ? AND assignment_id = ? LIMIT 1');
            $stmt2->execute([$subtaskId, $id]);
            if (!$stmt2->fetch()) { flash('error','Task not found.'); redirect('assignment-detail.php?id='.$id); }

            $pdo->prepare('UPDATE assignment_subtasks SET
                    title=?, description=?, assigned_user_id=?,
                    priority=?, status=?, due_date=?, completion_date=?, updated_at=NOW()
                    WHERE id=? AND assignment_id=?')
                ->execute([
                    $title, $description !== '' ? $description : null,
                    $assignedUid, $priority, $status, $dueDateVal, $completionDate,
                    $subtaskId, $id,
                ]);
            activity_log((int) current_user()['id'], 'task_updated', 'assignment_subtask', $subtaskId,
                'Updated task "'.$title.'" under "'.$assignment['title'].'".');
            flash('success', 'Task updated.');
        }
        redirect('assignment-detail.php?id='.$id);
    }

    // ── Delete task ────────────────────────────────────────────────────────
    if ($action === 'delete_subtask') {
        if (!user_can_manage_subtasks($assignment)) {
            http_response_code(403); exit('No permission.');
        }
        $subtaskId = (int) ($_POST['subtask_id'] ?? 0);
        if ($subtaskId > 0) {
            $stmt2 = $pdo->prepare('SELECT title FROM assignment_subtasks WHERE id = ? AND assignment_id = ? LIMIT 1');
            $stmt2->execute([$subtaskId, $id]);
            $deletedTask = $stmt2->fetch();
            if ($deletedTask) {
                // Deactivate any documents linked to this task
                $pdo->prepare('UPDATE documents SET is_active = 0 WHERE subtask_id = ?')->execute([$subtaskId]);
                // Delete the task
                $pdo->prepare('DELETE FROM assignment_subtasks WHERE id = ? AND assignment_id = ?')->execute([$subtaskId, $id]);
                activity_log((int) current_user()['id'], 'task_deleted', 'assignment', $id,
                    'Deleted task "' . $deletedTask['title'] . '" from assignment "' . $assignment['title'] . '".');
                flash('success', 'Task "' . $deletedTask['title'] . '" was deleted.');
            }
        }
        redirect('assignment-detail.php?id=' . $id);
    }

    // ── Upload document ───────────────────────────────────────────────────────
    if ($action === 'upload_document') {
        if (!user_can_view_assignment($assignment)) {
            http_response_code(403); exit('No permission.');
        }
        $subtaskId = (int) ($_POST['doc_subtask_id'] ?? 0);
        $err = handle_document_upload($id, $subtaskId > 0 ? $subtaskId : null, (int) current_user()['id']);
        if ($err) {
            flash('error', $err);
        } else {
            $docTitle = trim($_POST['doc_title'] ?? '') ?: 'document';
            activity_log((int) current_user()['id'], 'document_uploaded', 'assignment', $id,
                'Uploaded "' . $docTitle . '" to assignment "' . $assignment['title'] . '".');
            flash('success', 'Document uploaded successfully.');
        }
        redirect('assignment-detail.php?id=' . $id);
    }

    // ── Delete document ────────────────────────────────────────────────────
    if ($action === 'delete_document') {
        if (!user_is_adminish() && !user_is_committee_admin_for((int) $assignment['assigned_committee_id'])) {
            http_response_code(403); exit('No permission.');
        }
        $docId = (int) ($_POST['doc_id'] ?? 0);
        if ($docId > 0) {
            $stmt2 = $pdo->prepare('SELECT stored_file_name, title FROM documents WHERE id = ? AND assignment_id = ? LIMIT 1');
            $stmt2->execute([$docId, $id]);
            $doc = $stmt2->fetch();
            if ($doc) {
                $pdo->prepare('UPDATE documents SET is_active = 0 WHERE id = ?')->execute([$docId]);
                activity_log((int) current_user()['id'], 'document_deleted', 'assignment', $id,
                    'Removed document "' . $doc['title'] . '" from assignment "' . $assignment['title'] . '".');
                flash('success', 'Document removed.');
            }
        }
        redirect('assignment-detail.php?id=' . $id);
    }

    // ── Quick status update ────────────────────────────────────────────────
    if ($action === 'update_subtask_status') {
        $subtaskId = (int) ($_POST['subtask_id'] ?? 0);
        $newStatus = $_POST['subtask_status'] ?? '';
        $allowed   = ['not_started','in_progress','waiting','completed'];
        if (!in_array($newStatus, $allowed, true)) { flash('error','Invalid status.'); redirect('assignment-detail.php?id='.$id); }

        $stmt2 = $pdo->prepare('SELECT st.*, u.display_name AS assigned_name, creator.display_name AS created_by_name
            FROM assignment_subtasks st
            LEFT JOIN users u ON u.id = st.assigned_user_id
            LEFT JOIN users creator ON creator.id = st.created_by_user_id
            WHERE st.id = ? AND st.assignment_id = ? LIMIT 1');
        $stmt2->execute([$subtaskId, $id]);
        $subtask = $stmt2->fetch();

        if (!$subtask) { flash('error','Task not found.'); redirect('assignment-detail.php?id='.$id); }
        if (!user_can_update_subtask($assignment, $subtask)) { http_response_code(403); exit('No permission.'); }

        $completionDate = $newStatus === 'completed' ? date('Y-m-d') : null;
        $pdo->prepare('UPDATE assignment_subtasks SET status=?, completion_date=?, updated_at=NOW() WHERE id=? AND assignment_id=?')
            ->execute([$newStatus, $completionDate, $subtaskId, $id]);
        activity_log((int) current_user()['id'], 'task_status_updated', 'assignment_subtask', $subtaskId,
            'Changed task "'.$subtask['title'].'" to '.task_status_label($newStatus).' under "'.$assignment['title'].'".');
        flash('success', 'Status updated to '.task_status_label($newStatus).'.');
        redirect('assignment-detail.php?id='.$id);
    }
}
