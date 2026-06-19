<!-- ── View Assignment modal ────────────────────────────────────────────── -->
<div class="modal-backdrop" id="view-assignment-modal" aria-hidden="true">
  <div class="modal-card modal-card--wide <?= e($themeClass) ?>" role="dialog" aria-modal="true" aria-labelledby="view-assignment-title">
    <div class="modal-card__header <?= e($themeClass) ?>" style="border-bottom:1px solid rgba(31,41,55,.12);">
      <div>
        <h3 id="view-assignment-title">Assignment Details</h3>
        <p>Full details for this assignment — read only.</p>
      </div>
      <button class="icon-btn" type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <div class="modal-card__body <?= e($themeClass) ?>">
      <style>.modal-card[class*="assignment-theme"] .meta-box,.modal-card[class*="assignment-theme"] .panel-item{background:rgba(255,255,255,.78)}</style>
      <div class="meta-grid" style="margin-bottom:18px;">
        <div class="meta-box"><span class="k">Committee</span><span class="v"><?= e($assignment['committee_name']) ?> (<?= e($assignment['short_code']) ?>)</span></div>
        <div class="meta-box"><span class="k">Lead</span><span class="v"><?= e($assignment['lead_name'] ?: 'Unassigned') ?></span></div>
        <div class="meta-box"><span class="k">Status</span><span class="v"><?= e(status_label($assignment['status'])) ?></span></div>
        <div class="meta-box"><span class="k">Priority</span><span class="v"><span class="priority <?= e($assignment['priority']) ?>"><?= e(priority_label($assignment['priority'])) ?></span></span></div>
        <div class="meta-box"><span class="k">Date assigned</span><span class="v"><?= e(format_date($assignment['date_assigned'])) ?></span></div>
        <div class="meta-box"><span class="k">Due date</span><span class="v"><?= e(format_date($assignment['due_date'])) ?></span></div>
        <div class="meta-box"><span class="k">Support people</span><span class="v"><?= e((string) $supportCount) ?> added<?php if ($supportingMembers): ?> — <?= e(implode(', ', array_column($supportingMembers, 'display_name'))) ?><?php endif; ?></span></div>
        <div class="meta-box"><span class="k">Tasks</span><span class="v"><?= e((string) $taskCount) ?> total / <?= e((string) $completedTaskCount) ?> completed</span></div>
        <div class="meta-box"><span class="k">Created by</span><span class="v"><?= e($assignment['created_by_name'] ?: 'Unknown') ?></span></div>
        <div class="meta-box"><span class="k">Last updated by</span><span class="v"><?= e($assignment['updated_by_name'] ?: 'Unknown') ?></span></div>
      </div>
      <div class="panel-list">
        <div class="panel-item"><strong>Short description</strong><p><?= e($assignment['short_description'] ?: 'No short description.') ?></p></div>
        <div class="panel-item"><strong>Full description / notes</strong><p><?= nl2br(e($assignment['full_description'] ?: 'No extended notes.')) ?></p></div>
        <div class="panel-item"><strong>Notes</strong><p><?= e($assignment['origin_source'] ?: 'Not specified.') ?></p></div>
      </div>
    </div>
    <div class="modal-card__footer">
      <button class="btn btn-secondary" type="button" data-modal-close>Close</button>
    </div>
  </div>
</div>

<!-- ── Edit Assignment modal ────────────────────────────────────────────── -->
<?php if (user_can_edit_assignment($assignment)): ?>
<div class="modal-backdrop" id="edit-assignment-modal" aria-hidden="true">
  <div class="modal-card modal-card--wide" role="dialog" aria-modal="true" aria-labelledby="edit-assignment-title">
    <div class="modal-card__header">
      <div>
        <h3 id="edit-assignment-title">Edit Assignment</h3>
        <p>Changes are saved immediately and logged in History. Changing committee resets the lead dropdown.</p>
      </div>
      <button class="icon-btn" type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <div class="modal-card__body">
      <form method="post" class="form-demo" id="edit-assignment-form">
        <input type="hidden" name="_csrf"  value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="action" value="edit_assignment"/>

        <div class="field">
          <label for="ea-title">Title</label>
          <input id="ea-title" name="title" value="<?= e($assignment['title']) ?>" required/>
        </div>

        <div class="layout-two-equal" style="gap:16px;">
          <div class="field">
            <label for="ea-committee">Committee</label>
            <select id="ea-committee" name="assigned_committee_id" required>
              <?php foreach ($allCommittees as $c): ?>
                <option value="<?= e((string) $c['id']) ?>" <?= (int)$assignment['assigned_committee_id']===(int)$c['id']?'selected':'' ?>>
                  <?= e($c['name']) ?> (<?= e($c['short_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="ea-lead">Lead person</label>
            <select id="ea-lead" name="lead_user_id">
              <option value="">— Unassigned —</option>
              <?php foreach ($allMemberships as $m): ?>
                <option value="<?= e((string) $m['id']) ?>"
                        data-committee="<?= e((string) $m['committee_id']) ?>"
                        <?= (int)$assignment['lead_user_id']===(int)$m['id']?'selected':'' ?>>
                  <?= e($m['display_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="layout-two-equal" style="gap:16px;">
          <div class="field">
            <label for="ea-status">Status</label>
            <select id="ea-status" name="status">
              <?php foreach ([
                'assigned'=>'Assigned','in_progress'=>'In Progress','waiting_blocked'=>'Waiting / Blocked',
                'ready_for_review'=>'Ready for Review','completed'=>'Completed','archived'=>'Archived',
                'approved'=>'Approved','pending_handoff'=>'Pending Handoff',
                'in_receiving_review'=>'In Receiving Review',
                'returned_for_revision'=>'Returned for Revision','resubmitted'=>'Resubmitted',
              ] as $sv=>$sl): ?>
                <option value="<?= e($sv) ?>" <?= $assignment['status']===$sv?'selected':'' ?>><?= e($sl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="ea-priority">Priority</label>
            <select id="ea-priority" name="priority">
              <?php foreach (['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'] as $pv=>$pl): ?>
                <option value="<?= e($pv) ?>" <?= $assignment['priority']===$pv?'selected':'' ?>><?= e($pl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="layout-two-equal" style="gap:16px;">
          <div class="field">
            <label for="ea-date-assigned">Date assigned</label>
            <input id="ea-date-assigned" name="date_assigned" type="date" value="<?= e($assignment['date_assigned'] ?? '') ?>"/>
          </div>
          <div class="field">
            <label for="ea-due-date">Due date</label>
            <input id="ea-due-date" name="due_date" type="date" value="<?= e($assignment['due_date'] ?? '') ?>"/>
          </div>
        </div>

        <div class="field">
          <label for="ea-short-desc">Short description</label>
          <input id="ea-short-desc" name="short_description" value="<?= e($assignment['short_description'] ?? '') ?>" placeholder="One-line summary"/>
        </div>
        <div class="field">
          <label for="ea-full-desc">Full description / notes</label>
          <textarea id="ea-full-desc" name="full_description"><?= e($assignment['full_description'] ?? '') ?></textarea>
        </div>
        <div class="field">
          <label for="ea-origin">Notes</label>
          <input id="ea-origin" name="origin_source" value="<?= e($assignment['origin_source'] ?? '') ?>" placeholder="Optional notes"/>
        </div>
      </form>
    </div>
    <div class="modal-card__footer" style="justify-content:space-between;">
      <?php if (user_role() === 'superadmin'): ?>
      <form method="post" onsubmit="return confirm('Permanently delete this assignment? All tasks, documents, and history will also be removed. This cannot be undone.');">
        <input type="hidden" name="_csrf"   value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="action"  value="delete_assignment"/>
        <button class="btn btn-danger" type="submit">Delete Assignment</button>
      </form>
      <?php else: ?>
      <span></span>
      <?php endif; ?>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-secondary" type="button" data-modal-close>Cancel</button>
        <button class="btn btn-primary"   type="submit" form="edit-assignment-form">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<script>
// Filter lead dropdown to only show members of the selected committee
(function(){
  const committeeSelect = document.getElementById('ea-committee');
  const leadSelect      = document.getElementById('ea-lead');
  if (!committeeSelect || !leadSelect) return;

  function filterLeads() {
    const chosenCommittee = committeeSelect.value;
    let hasSelected = false;
    Array.from(leadSelect.options).forEach(opt => {
      if (!opt.value) return; // keep "Unassigned"
      const match = opt.dataset.committee === chosenCommittee;
      opt.hidden   = !match;
      opt.disabled = !match;
      if (!match && opt.selected) { opt.selected = false; }
      if (match && !hasSelected && opt.selected) hasSelected = true;
    });
  }

  committeeSelect.addEventListener('change', filterLeads);
  filterLeads(); // run on load to reflect current state
})();
</script>
<?php endif; ?>

<!-- ── Task modal ───────────────────────────────────────────────────────── -->
<?php if (user_can_manage_subtasks($assignment)): ?>
<div class="modal-backdrop" id="task-modal" aria-hidden="true">
  <div class="modal-card modal-card--wide" role="dialog" aria-modal="true" aria-labelledby="task-modal-title">
    <div class="modal-card__header">
      <div>
        <h3 id="task-modal-title">New Task</h3>
        <p>All active members of <?= e($assignment['committee_name']) ?> are available. Assigning someone auto-adds them as a support person.</p>
      </div>
      <button class="icon-btn" type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <div class="modal-card__body">
      <form method="post" class="form-demo" id="task-form">
        <input type="hidden" name="_csrf"      value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="action"     value="create_subtask" id="task-form-action"/>
        <input type="hidden" name="subtask_id" value=""               id="task-form-subtask-id"/>

        <div class="field">
          <label for="tf-title">Task title</label>
          <input id="tf-title" name="subtask_title" placeholder="e.g. Draft archive category structure" required/>
        </div>

        <div class="layout-two-equal" style="gap:16px;">
          <div class="field">
            <label for="tf-assignee">Assign to</label>
            <select id="tf-assignee" name="subtask_assigned_user_id" required>
              <option value="">Choose committee member…</option>
              <?php foreach ($committeeMembers as $m): ?>
                <option value="<?= e((string) $m['id']) ?>"><?= e($m['display_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="tf-due">Due date</label>
            <input id="tf-due" name="subtask_due_date" type="date"/>
          </div>
        </div>

        <div class="layout-two-equal" style="gap:16px;">
          <div class="field">
            <label for="tf-priority">Priority</label>
            <select id="tf-priority" name="subtask_priority">
              <option value="medium">Medium</option>
              <option value="low">Low</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div class="field">
            <label for="tf-status">Status</label>
            <select id="tf-status" name="subtask_status">
              <option value="not_started">Not Started</option>
              <option value="in_progress">In Progress</option>
              <option value="waiting">Waiting</option>
              <option value="completed">Completed</option>
            </select>
          </div>
        </div>

        <div class="field">
          <label for="tf-desc">What should this person do?</label>
          <textarea id="tf-desc" name="subtask_description" placeholder="Describe the concrete work expected."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-card__footer" style="justify-content:space-between;">
      <form method="post" id="delete-task-form" style="display:none;">
        <input type="hidden" name="_csrf"      value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="action"     value="delete_subtask"/>
        <input type="hidden" name="subtask_id" value="" id="delete-task-id"/>
        <button class="btn btn-danger" type="submit" id="delete-task-btn"
                onclick="return confirm('Delete this task? This cannot be undone and any uploaded files will be removed.');">
          Delete Task
        </button>
      </form>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-secondary" type="button" data-modal-close>Cancel</button>
        <button class="btn btn-primary"   type="submit" form="task-form" id="task-form-submit">Save Task</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const subtasks = <?= $subtasksJson ?>;
  const modal    = document.getElementById('task-modal');
  const titleEl  = document.getElementById('task-modal-title');
  const actionEl = document.getElementById('task-form-action');
  const stId     = document.getElementById('task-form-subtask-id');
  const submitEl = document.getElementById('task-form-submit');
  const tfTitle  = document.getElementById('tf-title');
  const tfAssign = document.getElementById('tf-assignee');
  const tfDue    = document.getElementById('tf-due');
  const tfPri    = document.getElementById('tf-priority');
  const tfStatus = document.getElementById('tf-status');
  const tfDesc   = document.getElementById('tf-desc');

  function resetForm() {
    tfTitle.value = ''; tfAssign.value = ''; tfDue.value = '';
    tfPri.value = 'medium'; tfStatus.value = 'not_started';
    tfDesc.value = ''; stId.value = '';
  }

  // Wire both + New Task (data-modal-open) and Edit task (data-edit-task) buttons
  document.querySelectorAll('[data-modal-open="task-modal"], [data-edit-task]').forEach(btn => {
    btn.addEventListener('click', () => {
      const mode   = btn.dataset.taskMode || 'create';
      const taskId = parseInt(btn.dataset.taskId || '0', 10);

      if (mode === 'create') {
        resetForm();
        actionEl.value       = 'create_subtask';
        titleEl.textContent  = 'New Task';
        submitEl.textContent = 'Create Task';
        document.getElementById('delete-task-form').style.display = 'none';
      } else {
        const st = subtasks.find(s => s.id === taskId);
        if (!st) return;
        actionEl.value       = 'edit_subtask';
        stId.value           = st.id;
        tfTitle.value        = st.title;
        tfAssign.value       = st.assigned ?? '';
        tfDue.value          = st.due_date ?? '';
        tfPri.value          = st.priority;
        tfStatus.value       = st.status;
        tfDesc.value         = st.description ?? '';
        titleEl.textContent  = 'Edit Task';
        submitEl.textContent = 'Save Changes';
        // Show delete form and set its subtask_id
        document.getElementById('delete-task-form').style.display = 'block';
        document.getElementById('delete-task-id').value = st.id;
      }

      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      setTimeout(() => tfTitle.focus(), 80);
    });
  });
})();
</script>
<?php endif; ?>
