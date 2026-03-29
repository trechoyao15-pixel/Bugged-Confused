<?php
session_start();
require_once "db.php";

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: SignUp_LogIn_Form.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['admin_csrf'];

$lost_items = [];
$found_items = [];
$pending_claims = [];
$audit_logs = [];

$res = $conn->query("SELECT * FROM lost_items ORDER BY created_at DESC LIMIT 200");
if ($res) while ($r = $res->fetch_assoc()) $lost_items[] = $r;

$res = $conn->query("SELECT * FROM found_items ORDER BY created_at DESC LIMIT 200");
if ($res) while ($r = $res->fetch_assoc()) $found_items[] = $r;

$res = $conn->query("
  SELECT c.*,
    CASE WHEN c.item_type='lost' THEN l.title WHEN c.item_type='found' THEN f.title ELSE '' END as item_title,
    COALESCE(l.photo, f.photo, '') AS item_photo,
    COALESCE(l.id, f.id) AS item_id_lookup
  FROM claims c
  LEFT JOIN lost_items l ON (c.item_type='lost' AND c.item_id = l.id)
  LEFT JOIN found_items f ON (c.item_type='found' AND c.item_id = f.id)
  WHERE LOWER(TRIM(c.status)) = 'pending'
  ORDER BY c.created_at ASC
  LIMIT 200
");
if ($res) while ($r = $res->fetch_assoc()) $pending_claims[] = $r;

$res = $conn->query("SELECT a.*, u.username AS admin_username FROM audit_logs a LEFT JOIN admins u ON a.admin_id = u.id ORDER BY a.created_at DESC LIMIT 50");
if ($res) while ($r = $res->fetch_assoc()) $audit_logs[] = $r;

$statuses = ['Unclaimed', 'Pending', 'Returned'];

function resolve_photo_url(string $photo): string {
    $photo = trim($photo);
    if ($photo === '') return '';
    if (preg_match('#^https?://#i', $photo)) return $photo;
    if (strpos($photo, 'uploads/') === 0) return $photo;
    return ltrim($photo, '/');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard — LTMS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="index.css">
  <link rel="stylesheet" href="dashboard.css">
  <style>
    .claim-thumb { width:84px; height:64px; object-fit:cover; border-radius:8px; margin-right:12px; flex:0 0 auto; }
    .claim-item { display:flex; gap:12px; align-items:flex-start; padding:12px; border-radius:10px; background:#fff; border:1px solid rgba(15,23,42,0.03); }
    .claim-meta { flex:1; }
    .claim-actions { display:flex; flex-direction:column; gap:8px; }
    .thumb-placeholder { width:84px; height:64px; border-radius:8px; background:linear-gradient(90deg,#f2f4f7,#fff); display:inline-block; }
  </style>
</head>
<body>
  <div class="noise-layer" aria-hidden="true"></div>

  <?php require_once __DIR__ . '/header.php'; ?>

  <div class="wrap admin-wrap">
    <h1 class="section-title">Admin Dashboard</h1>

    <?php if (!empty($_SESSION['success'])): ?>
      <div class="dash-success card" role="status">
        <strong>Success</strong>
        <div><?= htmlspecialchars($_SESSION['success']) ?></div>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
      <div class="dash-error card" role="status">
        <strong>Error</strong>
        <div><?= htmlspecialchars($_SESSION['error']) ?></div>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;">
      <div style="color:var(--muted);">Pending claims: <?= count($pending_claims) ?></div>
    </div>

    <div class="card">
      <h2 class="section-title">Pending Claims (<?= count($pending_claims) ?>)</h2>
      <?php if (empty($pending_claims)): ?>
        <p class="muted">No pending claims.</p>
      <?php else: ?>
        <div class="claim-list" aria-live="polite">
          <?php foreach ($pending_claims as $c): ?>
            <div class="claim-item" role="article" aria-label="Claim by <?= htmlspecialchars($c['claimant_name']) ?>">
              <?php
                $item_photo = resolve_photo_url($c['item_photo'] ?? '');
              ?>
              <?php if ($item_photo !== ''): ?>
                <img class="claim-thumb" src="<?= htmlspecialchars($item_photo) ?>" alt="Photo of <?= htmlspecialchars($c['item_title'] ?: 'item') ?>">
              <?php else: ?>
                <div class="claim-thumb thumb-placeholder" aria-hidden="true"></div>
              <?php endif; ?>

              <div class="claim-meta">
                <strong><?= htmlspecialchars($c['claimant_name']) ?></strong>
                <div class="muted" style="margin-top:4px;"><?= htmlspecialchars($c['claimant_contact']) ?></div>

                <div style="margin-top:8px;">
                  <div><strong>Item:</strong> <?= htmlspecialchars($c['item_title'] ?: '(unknown)') ?></div>
                  <div style="margin-top:6px;"><strong>Message:</strong> <?= nl2br(htmlspecialchars($c['message'])) ?></div>
                </div>

                <div class="tiny muted" style="margin-top:8px;">Submitted: <?= htmlspecialchars($c['created_at']) ?></div>
              </div>

              <div class="claim-actions">
                <form method="post" action="action.php" style="margin:0;">
                  <input type="hidden" name="csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="action" value="approve_claim">
                  <input type="hidden" name="claim_id" value="<?= (int)$c['id'] ?>">
                  <input type="hidden" name="item_type" value="<?= htmlspecialchars($c['item_type']) ?>">
                  <input type="hidden" name="item_id" value="<?= (int)$c['item_id'] ?>">
                  <button class="btn btn-primary" type="submit">Approve</button>
                </form>

                <form method="post" action="action.php" style="margin:0;">
                  <input type="hidden" name="csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="action" value="reject_claim">
                  <input type="hidden" name="claim_id" value="<?= (int)$c['id'] ?>">
                  <input type="hidden" name="item_type" value="<?= htmlspecialchars($c['item_type']) ?>">
                  <input type="hidden" name="item_id" value="<?= (int)$c['item_id'] ?>">
                  <button class="btn btn-outline" type="submit">Reject</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 class="section-title">Lost Items</h2>

      <?php if (empty($lost_items)): ?>
        <p class="muted">No lost items.</p>
      <?php else: ?>
        <div class="table-wrapper">
          <table class="items-table" aria-describedby="lost-items">
            <thead>
              <tr>
                <th>Photo</th>
                <th>Title</th>
                <th>Description</th>
                <th>Date Lost</th>
                <th>Location</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lost_items as $it): ?>
                <tr>
                  <td>
                    <?php $photo = resolve_photo_url($it['photo'] ?? ''); ?>
                    <?php if ($photo): ?>
                      <img class="thumb" src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($it['title'] ?? '') ?>">
                    <?php else: ?>
                      <div class="thumb thumb-placeholder" aria-hidden="true"></div>
                    <?php endif; ?>
                  </td>

                  <td><?= htmlspecialchars($it['title']) ?></td>
                  <td><?= htmlspecialchars($it['description']) ?></td>
                  <td><?= htmlspecialchars($it['date_lost']) ?></td>
                  <td><?= htmlspecialchars($it['location_lost']) ?></td>

                  <td>
                    <form class="status-form" method="post" action="action.php" style="margin:0;">
                      <input type="hidden" name="csrf" value="<?= $csrf ?>">
                      <input type="hidden" name="type" value="lost">
                      <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                      <select name="new_status" aria-label="Change status">
                        <?php
                          foreach ($statuses as $s) {
                              $sel = (strcasecmp($it['status'] ?? '', $s) === 0) ? 'selected' : '';
                              echo '<option value="' . htmlspecialchars($s) . '" ' . $sel . '>' . htmlspecialchars($s) . '</option>';
                          }
                        ?>
                      </select>
                      <input type="hidden" name="action" value="update_status">
                      <button type="submit" class="visually-hidden" style="display:none;">Update</button>
                    </form>
                  </td>

                  <td class="actions-cell">
                    <button type="button" class="btn-update" aria-label="Update item">Update</button>

                    <form method="post" action="action.php" onsubmit="return confirm('Delete this report?');" style="display:inline-block;margin:0;">
                      <input type="hidden" name="csrf" value="<?= $csrf ?>">
                      <input type="hidden" name="type" value="lost">
                      <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <button type="submit" class="btn-delete">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 class="section-title">Found Items</h2>

      <?php if (empty($found_items)): ?>
        <p class="muted">No found items.</p>
      <?php else: ?>
        <div class="table-wrapper">
          <table class="items-table" aria-describedby="found-items">
            <thead>
              <tr>
                <th>Photo</th>
                <th>Title</th>
                <th>Description</th>
                <th>Date Found</th>
                <th>Location</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($found_items as $it): ?>
                <tr>
                  <td>
                    <?php $photo = resolve_photo_url($it['photo'] ?? ''); ?>
                    <?php if ($photo): ?>
                      <img class="thumb" src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($it['title'] ?? '') ?>">
                    <?php else: ?>
                      <div class="thumb thumb-placeholder" aria-hidden="true"></div>
                    <?php endif; ?>
                  </td>

                  <td><?= htmlspecialchars($it['title']) ?></td>
                  <td><?= htmlspecialchars($it['description']) ?></td>
                  <td><?= htmlspecialchars($it['date_found']) ?></td>
                  <td><?= htmlspecialchars($it['location_found']) ?></td>

                  <td>
                    <form class="status-form" method="post" action="action.php" style="margin:0;">
                      <input type="hidden" name="csrf" value="<?= $csrf ?>">
                      <input type="hidden" name="type" value="found">
                      <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                      <select name="new_status" aria-label="Change status">
                        <?php
                              foreach ($statuses as $s) {
                                  $sel = (strcasecmp($it['status'] ?? '', $s) === 0) ? 'selected' : '';
                                  echo '<option value="' . htmlspecialchars($s) . '" ' . $sel . '>' . htmlspecialchars($s) . '</option>';
                              }
                        ?>
                      </select>
                      <input type="hidden" name="action" value="update_status">
                      <button type="submit" class="visually-hidden" style="display:none;">Update</button>
                    </form>
                  </td>

                  <td class="actions-cell">
                    <button type="button" class="btn-update" aria-label="Update item">Update</button>

                    <form method="post" action="action.php" onsubmit="return confirm('Delete this report?');" style="display:inline-block;margin:0;">
                      <input type="hidden" name="csrf" value="<?= $csrf ?>">
                      <input type="hidden" name="type" value="found">
                      <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <button type="submit" class="btn-delete">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 class="section-title">Recent Audit Logs</h2>

      <?php if (empty($audit_logs)): ?>
        <p class="muted">No audit entries yet.</p>
      <?php else: ?>
        <div class="table-wrapper">
          <table class="items-table audit-table">
            <thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>Target</th><th>Details</th></tr></thead>
            <tbody>
              <?php foreach ($audit_logs as $a): ?>
                <tr>
                  <td><?= htmlspecialchars($a['created_at']) ?></td>
                  <td><?= htmlspecialchars($a['admin_username'] ?? $a['admin_id']) ?></td>
                  <td><?= htmlspecialchars($a['action']) ?></td>
                  <td><?= htmlspecialchars(($a['target_type'] ?: '') . ($a['target_id'] ? " #{$a['target_id']}" : '')) ?></td>
                  <td><?= nl2br(htmlspecialchars($a['details'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <script>
    (function () {
      const toggle = document.querySelector('.nav-toggle');
      const navList = document.getElementById('nav-list');
      toggle?.addEventListener('click', () => {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!expanded));
        navList.classList.toggle('open');
      });
    })();

    document.addEventListener('click', function (e) {
      const btn = e.target.closest('.btn-update');
      if (!btn) return;
      const tr = btn.closest('tr');
      if (!tr) return;
      const form = tr.querySelector('.status-form');
      if (!form) return;
      form.submit();
    });
  </script>
</body>
</html>