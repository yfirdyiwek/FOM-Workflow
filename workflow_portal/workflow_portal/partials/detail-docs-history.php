  <!-- Documents -->
  <article class="card detail-card">
    <details id="documents-toggle">
      <summary style="list-style:none;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;cursor:pointer;padding:0;">
        <div>
          <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-secondary);">Assignment Documents</div>
        </div>
        <span class="btn btn-secondary documents-toggle-label" style="min-height:34px;padding:7px 14px;font-size:.88rem;pointer-events:none;flex-shrink:0;">Show Documents</span>
      </summary>

      <div style="margin-top:16px;">

        <!-- Upload form -->
        <details class="assignment-collapsible" style="margin-bottom:16px;">
          <summary>Upload a document</summary>
          <div class="collapsible-body">
            <form method="post" enctype="multipart/form-data" class="form-demo" style="margin-top:8px;">
              <input type="hidden" name="_csrf"   value="<?= e(csrf_token()) ?>"/>
              <input type="hidden" name="action"  value="upload_document"/>

              <div class="layout-two-equal" style="gap:16px;">
                <div class="field">
                  <label for="doc_title">Document title</label>
                  <input id="doc_title" name="doc_title" placeholder="Leave blank to use filename"/>
                </div>
                <div class="field">
                  <label for="doc_category">Category</label>
                  <select id="doc_category" name="doc_category">
                    <?php foreach ($docCategories as $cat): ?>
                      <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <?php if ($subtasks): ?>
              <div class="field">
                <label for="doc_subtask_id">Link to task <span style="font-weight:400;color:var(--text-secondary);">(optional)</span></label>
                <select id="doc_subtask_id" name="doc_subtask_id">
                  <option value="">— Assignment level —</option>
                  <?php foreach ($subtasks as $st): ?>
                    <option value="<?= e((string) $st['id']) ?>"><?= e($st['title']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endif; ?>

              <div class="field">
                <label for="doc_description">Notes <span style="font-weight:400;color:var(--text-secondary);">(optional)</span></label>
                <input id="doc_description" name="doc_description" placeholder="Brief description of this file"/>
              </div>

              <div class="layout-two-equal" style="gap:16px;align-items:flex-end;">
                <div class="field">
                  <label for="doc_file">File <span style="font-weight:400;color:var(--text-secondary);">(max <?= (int) UPLOAD_MAX_MB ?>MB)</span></label>
                  <input id="doc_file" name="doc_file" type="file" required style="padding:8px;"/>
                </div>
                <div class="field" style="display:flex;align-items:center;gap:8px;padding-bottom:4px;">
                  <input type="checkbox" id="doc_is_final" name="doc_is_final" value="1"/>
                  <label for="doc_is_final" style="cursor:pointer;font-weight:400;">Mark as final version</label>
                </div>
              </div>

              <div class="button-row">
                <button class="btn btn-primary" type="submit">Upload Document</button>
              </div>
            </form>
          </div>
        </details>

        <!-- Document list: assignment-level files only (task files shown on task cards) -->
        <?php $assignmentDocs = $docsBySubtask[0] ?? []; ?>
        <?php if ($assignmentDocs): ?>
          <div class="panel-list compact-panel-list">
            <?php foreach ($assignmentDocs as $doc): ?>
              <div class="panel-item" style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                <div style="min-width:0;flex:1;">
                  <strong style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <?= e($doc['title']) ?>
                    <?php if ($doc['is_final_version']): ?>
                      <span class="badge" style="background:#dcfce7;color:#166534;font-size:.72rem;">Final</span>
                    <?php endif; ?>
                    <?php if ($doc['category']): ?>
                      <span class="badge" style="font-size:.72rem;"><?= e($doc['category']) ?></span>
                    <?php endif; ?>
                  </strong>
                  <p style="margin:3px 0 0;font-size:.84rem;color:var(--text-secondary);">
                    <?= e($doc['uploader_name'] ?: 'Unknown') ?>
                    · <?= e((new DateTime($doc['uploaded_at']))->format('M j, Y g:i A')) ?>
                    · <?= e(strtoupper($doc['file_extension'] ?? '')) ?>
                    · <?= e(number_format(($doc['file_size'] ?? 0) / 1024, 0)) ?>KB
                    <?php if ($doc['subtask_title']): ?>
                      · Task: <?= e($doc['subtask_title']) ?>
                    <?php endif; ?>
                  </p>
                  <?php if ($doc['description']): ?>
                    <p style="margin:3px 0 0;font-size:.84rem;color:var(--text-secondary);font-style:italic;"><?= e($doc['description']) ?></p>
                  <?php endif; ?>
                </div>
                <div style="display:flex;gap:6px;flex-shrink:0;align-items:center;">
                  <a class="mini-btn" href="download.php?id=<?= e((string) $doc['id']) ?>">Download</a>
                  <?php if (user_is_adminish() || user_is_committee_admin_for((int) $assignment['assigned_committee_id'])): ?>
                    <form method="post" onsubmit="return confirm('Remove this document?');">
                      <input type="hidden" name="_csrf"   value="<?= e(csrf_token()) ?>"/>
                      <input type="hidden" name="action"  value="delete_document"/>
                      <input type="hidden" name="doc_id"  value="<?= e((string) $doc['id']) ?>"/>
                      <button class="mini-btn" type="submit" style="color:#b91c1c;border-color:#fecaca;">Remove</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="panel-item"><strong>No assignment-level documents yet</strong><p>Files uploaded directly to tasks appear on their task cards above. Use the form above for documents that apply to the whole assignment.</p></div>
        <?php endif; ?>

      </div>
    </details>
  </article>

  <style>
  #documents-toggle summary::-webkit-details-marker{display:none}
  </style>
  <script>
  (function(){
    const tog = document.getElementById('documents-toggle');
    if (!tog) return;
    const lbl = tog.querySelector('.documents-toggle-label');
    function sync(){ lbl.textContent = tog.open ? 'Hide Documents' : 'Show Documents'; }
    tog.addEventListener('toggle', sync);
    sync();
  })();
  </script>

  <!-- History -->
  <article class="card detail-card">
    <details id="history-toggle">
      <summary style="list-style:none;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;cursor:pointer;padding:0;">
        <div>
          <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-secondary);">Assignment History</div>
        </div>
        <span class="btn btn-secondary history-toggle-label" style="min-height:34px;padding:7px 14px;font-size:.88rem;pointer-events:none;flex-shrink:0;">Show History</span>
      </summary>
      <div class="panel-list compact-panel-list" style="margin-top:16px;">
        <?php foreach ($history as $entry): ?>
          <div class="panel-item">
            <strong><?= e($entry['action_summary']) ?></strong>
            <p><?= e($entry['display_name'] ?: 'System') ?> · <?= e((new DateTime($entry['created_at']))->format('M j, Y g:i A')) ?></p>
          </div>
        <?php endforeach; ?>
        <?php if (!$history): ?>
          <div class="panel-item">
            <strong>No history yet</strong>
            <p>Actions will appear here once tasks are created or updated.</p>
          </div>
        <?php endif; ?>
      </div>
    </details>
  </article>

  <style>
  #history-toggle summary::-webkit-details-marker{display:none}
  </style>
  <script>
  (function(){
    const toggle = document.getElementById('history-toggle');
    if (!toggle) return;
    const label = toggle.querySelector('.history-toggle-label');
    function syncLabel(){ label.textContent = toggle.open ? 'Hide History' : 'Show History'; }
    toggle.addEventListener('toggle', syncLabel);
    syncLabel();
  })();
  </script>

