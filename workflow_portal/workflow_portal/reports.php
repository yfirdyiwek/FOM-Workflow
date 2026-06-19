<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_once __DIR__ . '/includes/layout.php';
render_header('Reports', 'Basic reports can be layered on once assignments are real.', 'reports', null);
?>
<article class="card placeholder-card">
  <h2 style="margin-top:0">Coming next</h2>
  <p>Because this starter already stores committee, lead, status, and due date, reports such as overdue assignments and workload by lead can be added without restructuring the app.</p>
</article>
<?php render_footer(); ?>
