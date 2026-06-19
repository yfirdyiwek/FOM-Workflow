  <!-- Tasks -->
  <!-- Bottom grid -->
  <div class="assignment-bottom-grid">

    <!-- Tasks -->
    <article class="card detail-card <?= e($themeClass) ?>">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
        <div>
          <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-secondary);">Assignment Tasks</div>
        </div>
        <div class="meta-line" style="align-items:center;flex-shrink:0;">
          <span style="font-size:.84rem;font-weight:700;"><?= e((string) $taskCount) ?> total</span>
          <span class="status-completed" style="font-size:.84rem;font-weight:700;"><?= e((string) $completedTaskCount) ?> done</span>
          <span class="status-progress"  style="font-size:.84rem;font-weight:700;"><?= e((string) $inProgressTaskCount) ?> active</span>
        </div>
      </div>

      <div class="panel-list">
        <?php foreach ($subtasks as $subtask): ?>
          <?php $taskDocs = $docsBySubtask[(int) $subtask['id']] ?? []; ?>
          <div class="panel-item" style="padding:0;overflow:hidden;">
            <details class="task-expandable">
              <!-- Summary row: always visible -->
              <summary style="list-style:none;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px;cursor:pointer;flex-wrap:wrap;">
                <div style="flex:1;min-width:0;">
                  <strong style="display:block;margin-bottom:3px;"><?= e($subtask['title']) ?></strong>
                  <span style="font-size:.86rem;color:var(--text-secondary);">
                    <?= e($subtask['assigned_name'] ?: 'Unassigned') ?>
                    <?php if ($subtask['due_date']): ?> · Due <?= e(format_date($subtask['due_date'])) ?><?php endif; ?>
                    <?php if ($taskDocs): ?> · <strong style="color:var(--text-primary);"><?= count($taskDocs) ?> file<?= count($taskDocs) !== 1 ? 's' : '' ?></strong><?php endif; ?>
                  </span>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;flex-shrink:0;">
                  <span class="priority <?= e($subtask['priority']) ?>"><?= e(priority_label($subtask['priority'])) ?></span>
                  <span class="<?= e(subtask_status_badge_class($subtask['status'])) ?>" style="font-weight:700;font-size:.84rem;"><?= e(task_status_label($subtask['status'])) ?></span>
                  <?php if (user_can_manage_subtasks($assignment)): ?>
                    <button class="mini-btn" type="button"
                            data-edit-task
                            data-task-mode="edit"
                            data-task-id="<?= e((string) $subtask['id']) ?>"
                            onclick="event.stopPropagation();">Edit</button>
                  <?php endif; ?>
                </div>
              </summary>

              <!-- Expanded body -->
              <div style="padding:0 14px 14px;border-top:1px solid var(--border-subtle);">

                <!-- Instructions -->
                <?php if ($subtask['description']): ?>
                  <div style="margin:14px 0 12px;padding:12px;background:var(--surface-muted);border-radius:10px;">
                    <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--text-secondary);margin-bottom:6px;">Instructions</div>
                    <p style="margin:0;font-size:.93rem;line-height:1.6;"><?= nl2br(e($subtask['description'])) ?></p>
                  </div>
                <?php endif; ?>

                <!-- Documents attached to this task -->
                <?php if ($taskDocs): ?>
                  <div style="margin-bottom:12px;">
                    <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--text-secondary);margin-bottom:8px;">Submitted Files</div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                      <?php foreach ($taskDocs as $doc): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:10px;">
                          <div style="min-width:0;flex:1;">
                            <strong style="font-size:.9rem;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                              <?= e($doc['title']) ?>
                              <?php if ($doc['is_final_version']): ?><span class="badge" style="background:#dcfce7;color:#166534;font-size:.7rem;">Final</span><?php endif; ?>
                            </strong>
                            <p style="margin:2px 0 0;font-size:.82rem;color:var(--text-secondary);">
                              <?= e($doc['uploader_name'] ?: 'Unknown') ?> · <?= e((new DateTime($doc['uploaded_at']))->format('M j, Y g:i A')) ?>
                              · <?= e(strtoupper($doc['file_extension'] ?? '')) ?> · <?= e(number_format(($doc['file_size'] ?? 0) / 1024, 0)) ?>KB
                            </p>
                          </div>
                          <div style="display:flex;gap:6px;flex-shrink:0;">
                            <a class="mini-btn" href="download.php?id=<?= e((string) $doc['id']) ?>">Download</a>
                            <?php if (user_is_adminish() || user_is_committee_admin_for((int) $assignment['assigned_committee_id'])): ?>
                              <form method="post" onsubmit="return confirm('Remove this file?');" style="display:inline;">
                                <input type="hidden" name="_csrf"  value="<?= e(csrf_token()) ?>"/>
                                <input type="hidden" name="action" value="delete_document"/>
                                <input type="hidden" name="doc_id" value="<?= e((string) $doc['id']) ?>"/>
                                <button class="mini-btn" type="submit" style="color:#b91c1c;border-color:#fecaca;">Remove</button>
                              </form>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <!-- Upload form for this task -->
                <?php if (user_can_update_subtask($assignment, $subtask)): ?>
                  <details class="assignment-collapsible" style="margin-bottom:12px;">
                    <summary>Upload a file for this task</summary>
                    <div class="collapsible-body">
                      <form method="post" enctype="multipart/form-data" class="form-demo" style="margin-top:8px;">
                        <input type="hidden" name="_csrf"          value="<?= e(csrf_token()) ?>"/>
                        <input type="hidden" name="action"         value="upload_document"/>
                        <input type="hidden" name="doc_subtask_id" value="<?= e((string) $subtask['id']) ?>"/>
                        <div class="layout-two-equal" style="gap:12px;">
                          <div class="field">
                            <label>Document title</label>
                            <input name="doc_title" placeholder="Leave blank to use filename"/>
                          </div>
                          <div class="field">
                            <label>Category</label>
                            <select name="doc_category">
                              <?php foreach ($docCategories as $cat): ?>
                                <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                        </div>
                        <div class="field">
                          <label>File <span style="font-weight:400;color:var(--text-secondary);">(max <?= (int) UPLOAD_MAX_MB ?>MB)</span></label>
                          <input name="doc_file" type="file" required style="padding:8px;"/>
                        </div>
                        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                          <label style="display:flex;align-items:center;gap:6px;font-size:.88rem;cursor:pointer;font-weight:400;">
                            <input type="checkbox" name="doc_is_final" value="1"/> Mark as final version
                          </label>
                          <button class="btn btn-primary" type="submit" style="min-height:36px;padding:7px 16px;font-size:.88rem;">Upload</button>
                        </div>
                      </form>
                    </div>
                  </details>
                <?php endif; ?>

                <!-- Status update -->
                <?php if (user_can_update_subtask($assignment, $subtask)): ?>
                  <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="_csrf"      value="<?= e(csrf_token()) ?>"/>
                    <input type="hidden" name="action"     value="update_subtask_status"/>
                    <input type="hidden" name="subtask_id" value="<?= e((string) $subtask['id']) ?>"/>
                    <label style="font-size:.86rem;font-weight:700;">Update status:</label>
                    <select name="subtask_status" style="font-size:.86rem;padding:6px 10px;border-radius:8px;border:1px solid var(--border-subtle);background:var(--surface-input);color:var(--text-primary);">
                      <?php foreach (['not_started'=>'Not Started','in_progress'=>'In Progress','waiting'=>'Waiting','completed'=>'Completed'] as $sv=>$sl): ?>
                        <option value="<?= e($sv) ?>" <?= $subtask['status']===$sv?'selected':'' ?>><?= e($sl) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="mini-btn" type="submit">Save</button>
                  </form>
                <?php endif; ?>

              </div>
            </details>
          </div>
        <?php endforeach; ?>
        <?php if (!$subtasks): ?>
          <div class="panel-item">
            <strong>No tasks yet</strong>
            <p>Use "+ New Task" to create the first task for this assignment.</p>
          </div>
        <?php endif; ?>
      </div>
      <?php if (user_can_manage_subtasks($assignment)): ?>
        <div style="margin-top:14px;display:flex;justify-content:flex-end;">
          <button class="btn btn-primary" type="button"
                  style="min-height:34px;padding:7px 16px;font-size:.88rem;"
                  data-modal-open="task-modal" data-task-mode="create">+ New Task</button>
        </div>
      <?php endif; ?>
  </article>

