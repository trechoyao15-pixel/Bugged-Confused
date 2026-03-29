<?php
session_start();
require_once __DIR__ . '/db.php';

// Guard: only allow logged-in admins
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: signup_login_form.php');
    exit;
}

// CSRF token
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['admin_csrf'];

// ... your lost_items, found_items, claims queries here ...



// ── Fetch data ────────────────────────────────────────────────────────────────
$lost_items    = [];
$found_items   = [];
$all_claims    = [];
$audit_logs    = [];

$res = $conn->query("SELECT * FROM lost_items  ORDER BY created_at DESC LIMIT 200");
if ($res) while ($r = $res->fetch_assoc()) $lost_items[] = $r;

$res = $conn->query("SELECT * FROM found_items ORDER BY created_at DESC LIMIT 200");
if ($res) while ($r = $res->fetch_assoc()) $found_items[] = $r;

// All claims (any status) with item details
$res = $conn->query("
    SELECT c.*,
        CASE WHEN c.item_type='lost' THEN l.title
             WHEN c.item_type='found' THEN f.title
             ELSE '' END                        AS item_title,
        COALESCE(l.photo, f.photo, '')          AS item_photo,
        COALESCE(l.location_lost, f.location_found, '') AS item_location
    FROM claims c
    LEFT JOIN lost_items  l ON (c.item_type='lost'  AND c.item_id = l.id)
    LEFT JOIN found_items f ON (c.item_type='found' AND c.item_id = f.id)
    ORDER BY
        FIELD(LOWER(TRIM(c.status)), 'pending', 'approved', 'rejected'),
        c.created_at ASC
    LIMIT 300
");
if ($res) while ($r = $res->fetch_assoc()) $all_claims[] = $r;

$res = $conn->query("
    SELECT a.*, adm.username AS admin_username
    FROM audit_logs a
    LEFT JOIN admins adm ON a.admin_id = adm.id
    ORDER BY a.created_at DESC
    LIMIT 60
");
if ($res) while ($r = $res->fetch_assoc()) $audit_logs[] = $r;

$statuses = ['Unclaimed', 'Pending', 'Returned'];

// Counts for header badges
$pending_count  = count(array_filter($all_claims, fn($c) => strtolower(trim($c['status'])) === 'pending'));

function resolve_photo(string $p): string {
    $p = trim($p);
    if ($p === '') return '';
    if (preg_match('#^https?://#i', $p)) return $p;
    return ltrim($p, '/');
}

function status_badge(string $s): string {
    $s = strtolower(trim($s));
    $map = [
        'pending'  => ['#f59e0b', '#fffbeb', 'Pending'],
        'approved' => ['#16a34a', '#f0fdf4', 'Approved'],
        'rejected' => ['#dc2626', '#fef2f2', 'Rejected'],
    ];
    [$color, $bg, $label] = $map[$s] ?? ['#6b7280', '#f3f4f6', ucfirst($s)];
    return "<span style=\"display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;color:{$color};background:{$bg};\">{$label}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard — LTMS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    /* ── Reset & base ─────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; }
    body {
      font-family: 'Poppins', system-ui, sans-serif;
      background: #f1f5f9;
      color: #0f172a;
      -webkit-font-smoothing: antialiased;
    }

    /* ── Layout ───────────────────────────────────────────── */
    .layout { display: flex; min-height: 100vh; }

    /* ── Sidebar ──────────────────────────────────────────── */
    .sidebar {
      width: 240px;
      flex-shrink: 0;
      background: #1e3a8a;
      display: flex;
      flex-direction: column;
      padding: 28px 0;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
    }
    .sidebar-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 0 20px 28px;
      color: #fff;
      font-weight: 800;
      font-size: 18px;
      text-decoration: none;
    }
    .sidebar-brand svg { border-radius: 8px; }

    .sidebar-section {
      padding: 0 12px;
      margin-bottom: 6px;
    }
    .sidebar-section-label {
      color: rgba(255,255,255,0.4);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      padding: 0 8px 8px;
    }
    .sidebar-link {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      border-radius: 10px;
      color: rgba(255,255,255,0.7);
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: background .15s, color .15s;
    }
    .sidebar-link:hover, .sidebar-link.active {
      background: rgba(255,255,255,0.12);
      color: #fff;
    }
    .sidebar-link i { font-size: 18px; }

    .badge-pill {
      margin-left: auto;
      background: #ef4444;
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 999px;
    }

    .sidebar-bottom {
      margin-top: auto;
      padding: 0 12px;
    }

    /* ── Main area ────────────────────────────────────────── */
    .main {
      flex: 1;
      display: flex;
      flex-direction: column;
      min-width: 0;
    }

    /* ── Top bar ──────────────────────────────────────────── */
    .topbar {
      background: #fff;
      border-bottom: 1px solid #e2e8f0;
      padding: 14px 28px;
      display: flex;
      align-items: center;
      gap: 16px;
      position: sticky;
      top: 0;
      z-index: 30;
    }
    .topbar h1 {
      font-size: 18px;
      font-weight: 800;
      color: #07103a;
      flex: 1;
    }
    .topbar-admin {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #6b7280;
      font-size: 14px;
      font-weight: 600;
    }
    .avatar {
      width: 34px; height: 34px;
      background: linear-gradient(135deg,#4B6EF6,#7B9CFF);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      color: #fff;
      font-size: 14px;
      font-weight: 800;
    }
    .btn-logout {
      display: flex; align-items: center; gap: 6px;
      padding: 8px 14px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      color: #6b7280;
      text-decoration: none;
      cursor: pointer;
      transition: background .15s;
    }
    .btn-logout:hover { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }

    /* ── Content ──────────────────────────────────────────── */
    .content { padding: 28px; display: flex; flex-direction: column; gap: 24px; }

    /* ── Stat cards ───────────────────────────────────────── */
    .stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; }
    .stat-card {
      background: #fff;
      border-radius: 14px;
      padding: 20px;
      border: 1px solid #e2e8f0;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .stat-label { font-size: 13px; font-weight: 600; color: #6b7280; }
    .stat-value { font-size: 32px; font-weight: 800; color: #07103a; line-height: 1; }
    .stat-icon { font-size: 22px; }

    /* ── Section card ─────────────────────────────────────── */
    .section-card {
      background: #fff;
      border-radius: 14px;
      border: 1px solid #e2e8f0;
      overflow: hidden;
    }
    .section-head {
      padding: 18px 22px;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .section-head h2 {
      font-size: 15px;
      font-weight: 800;
      color: #07103a;
      flex: 1;
    }
    .section-head .count-badge {
      background: #f1f5f9;
      color: #475569;
      font-size: 12px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 999px;
    }
    .section-body { padding: 20px 22px; }

    /* ── Alerts ───────────────────────────────────────────── */
    .alert {
      border-radius: 10px;
      padding: 14px 18px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 14px;
      font-weight: 600;
    }
    .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    .alert-error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    /* ── Claim cards ──────────────────────────────────────── */
    .claims-grid { display: flex; flex-direction: column; gap: 14px; }

    .claim-card {
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 16px;
      display: flex;
      gap: 14px;
      align-items: flex-start;
      background: #fafafa;
      transition: box-shadow .15s;
    }
    .claim-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
    .claim-card.status-approved { border-left: 4px solid #16a34a; }
    .claim-card.status-rejected { border-left: 4px solid #dc2626; }
    .claim-card.status-pending  { border-left: 4px solid #f59e0b; }

    .claim-thumb {
      width: 80px; height: 64px;
      object-fit: cover;
      border-radius: 8px;
      flex-shrink: 0;
      background: #e2e8f0;
    }
    .claim-thumb-placeholder {
      width: 80px; height: 64px;
      background: linear-gradient(135deg,#e2e8f0,#f1f5f9);
      border-radius: 8px;
      flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      color: #94a3b8;
      font-size: 22px;
    }
    .claim-info { flex: 1; min-width: 0; }
    .claim-info .claimant { font-weight: 700; font-size: 15px; color: #07103a; }
    .claim-info .contact  { font-size: 13px; color: #6b7280; margin-top: 2px; }
    .claim-info .item-name { margin-top: 8px; font-size: 14px; }
    .claim-info .item-name strong { color: #4B6EF6; }
    .claim-info .message { margin-top: 6px; font-size: 13px; color: #374151; line-height: 1.5; }
    .claim-info .meta { margin-top: 8px; font-size: 12px; color: #94a3b8; }

    .claim-actions-col {
      display: flex;
      flex-direction: column;
      gap: 8px;
      flex-shrink: 0;
    }

    .btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 9px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      font-family: inherit;
      cursor: pointer;
      border: none;
      text-decoration: none;
      white-space: nowrap;
      transition: opacity .15s, box-shadow .15s;
    }
    .btn:hover { opacity: .88; }
    .btn-approve {
      background: linear-gradient(90deg,#16a34a,#22c55e);
      color: #fff;
      box-shadow: 0 4px 12px rgba(22,163,74,.2);
    }
    .btn-reject {
      background: #fff;
      color: #b91c1c;
      border: 1px solid #fca5a5;
    }
    .btn-update {
      background: linear-gradient(90deg,#4B6EF6,#7B9CFF);
      color: #fff;
      box-shadow: 0 4px 12px rgba(75,110,246,.2);
    }
    .btn-delete {
      background: #fff;
      color: #b91c1c;
      border: 1px solid #fca5a5;
    }
    .btn-sm { padding: 7px 12px; font-size: 12px; }

    /* ── Table ────────────────────────────────────────────── */
    .tbl-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table { width: 100%; border-collapse: collapse; min-width: 780px; }
    thead th {
      background: #f8fafc;
      padding: 12px 14px;
      text-align: left;
      font-size: 12px;
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: .04em;
      border-bottom: 1px solid #e2e8f0;
      white-space: nowrap;
    }
    tbody td {
      padding: 13px 14px;
      border-bottom: 1px solid #f1f5f9;
      font-size: 14px;
      vertical-align: middle;
      color: #334155;
    }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover { background: #f8fafc; }

    .thumb { width: 72px; height: 56px; object-fit: cover; border-radius: 8px; display: block; }
    .thumb-placeholder {
      width: 72px; height: 56px;
      background: #e2e8f0;
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      color: #94a3b8; font-size: 20px;
    }

    .status-select {
      padding: 7px 10px;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
      font-size: 13px;
      font-family: inherit;
      font-weight: 600;
      color: #334155;
      background: #fff;
      cursor: pointer;
    }

    .actions-cell { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

    /* ── Tabs ─────────────────────────────────────────────── */
    .tabs { display: flex; gap: 4px; }
    .tab-btn {
      padding: 8px 16px;
      border-radius: 8px;
      border: none;
      font-size: 13px;
      font-weight: 700;
      font-family: inherit;
      cursor: pointer;
      background: transparent;
      color: #64748b;
      transition: background .15s, color .15s;
    }
    .tab-btn.active, .tab-btn:hover {
      background: #eff6ff;
      color: #4B6EF6;
    }

    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    /* ── Empty state ──────────────────────────────────────── */
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #94a3b8;
    }
    .empty-state i { font-size: 42px; display: block; margin-bottom: 10px; }

    /* ── Responsive ───────────────────────────────────────── */
    @media (max-width: 900px) {
      .sidebar { display: none; }
      .stats-row { grid-template-columns: repeat(2,1fr); }
    }
    @media (max-width: 560px) {
      .stats-row { grid-template-columns: 1fr 1fr; }
      .content { padding: 16px; }
      .claim-card { flex-wrap: wrap; }
    }
  </style>
</head>
<body>
<div class="layout">

  <!-- ── Sidebar ──────────────────────────────────────────── -->
  <aside class="sidebar">
    <a class="sidebar-brand" href="index.php">
      <svg width="32" height="32" viewBox="0 0 24 24">
        <path fill="rgba(255,255,255,.9)" d="M12 2C8 2 4 4 4 8v8c0 4 4 6 8 6s8-2 8-6V8c0-4-4-6-8-6zm0 2c2.8 0 5 1.2 5 4v8c0 2.8-2.2 4-5 4s-5-1.2-5-4V8c0-2.8 2.2-4 5-4z"/>
        <circle cx="12" cy="12" r="2.5" fill="#fff"/>
      </svg>
      LTMS Admin
    </a>

    <div class="sidebar-section">
      <div class="sidebar-section-label">Main</div>
      <a class="sidebar-link active" href="admin_dashboard.php">
        <i class='bx bxs-dashboard'></i> Dashboard
      </a>
      <a class="sidebar-link" href="#claims-section" onclick="scrollTo('claims-section')">
        <i class='bx bx-task'></i> Claims
        <?php if ($pending_count > 0): ?>
          <span class="badge-pill"><?= $pending_count ?></span>
        <?php endif; ?>
      </a>
      <a class="sidebar-link" href="#items-section">
        <i class='bx bx-list-ul'></i> Items
      </a>
      <a class="sidebar-link" href="#audit-section">
        <i class='bx bx-history'></i> Audit Log
      </a>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-section-label">Public Site</div>
      <a class="sidebar-link" href="lost_list.php" target="_blank">
        <i class='bx bx-search-alt'></i> Lost Items
      </a>
      <a class="sidebar-link" href="found_list.php" target="_blank">
        <i class='bx bx-check-circle'></i> Found Items
      </a>
    </div>

    <div class="sidebar-bottom">
      <a class="sidebar-link" href="admin_logout.php">
        <i class='bx bx-log-out'></i> Logout
      </a>
    </div>
  </aside>

  <!-- ── Main ─────────────────────────────────────────────── -->
  <div class="main">

    <!-- Top bar -->
    <div class="topbar">
      <h1>Admin Dashboard</h1>
      <div class="topbar-admin">
        <div class="avatar">A</div>
        <span><?= htmlspecialchars($_SESSION['admin_email'] ?? 'Admin') ?></span>
      </div>
      <a class="btn-logout" href="admin_logout.php">
        <i class='bx bx-log-out'></i> Logout
      </a>
    </div>

    <div class="content">

      <!-- Alerts -->
      <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success">
          <i class='bx bx-check-circle'></i>
          <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
      <?php endif; ?>

      <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-error">
          <i class='bx bx-error-circle'></i>
          <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
      <?php endif; ?>

      <!-- Stat cards -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon" style="color:#f59e0b;">⏳</div>
          <div class="stat-value"><?= $pending_count ?></div>
          <div class="stat-label">Pending Claims</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="color:#4B6EF6;">📋</div>
          <div class="stat-value"><?= count($lost_items) ?></div>
          <div class="stat-label">Lost Reports</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="color:#16a34a;">📦</div>
          <div class="stat-value"><?= count($found_items) ?></div>
          <div class="stat-label">Found Reports</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="color:#8b5cf6;">📁</div>
          <div class="stat-value"><?= count($all_claims) ?></div>
          <div class="stat-label">Total Claims</div>
        </div>
      </div>

      <!-- ── CLAIMS SECTION ────────────────────────────────── -->
      <div class="section-card" id="claims-section">
        <div class="section-head">
          <i class='bx bx-task' style="color:#4B6EF6;font-size:20px;"></i>
          <h2>Claims Management</h2>
          <?php if ($pending_count > 0): ?>
            <span class="count-badge" style="background:#fef3c7;color:#92400e;">
              <?= $pending_count ?> pending
            </span>
          <?php endif; ?>

          <!-- Filter tabs -->
          <div class="tabs" style="margin-left:auto;">
            <button class="tab-btn active" onclick="filterClaims('all',this)">All</button>
            <button class="tab-btn" onclick="filterClaims('pending',this)">Pending</button>
            <button class="tab-btn" onclick="filterClaims('approved',this)">Approved</button>
            <button class="tab-btn" onclick="filterClaims('rejected',this)">Rejected</button>
          </div>
        </div>

        <div class="section-body">
          <?php if (empty($all_claims)): ?>
            <div class="empty-state">
              <i class='bx bx-inbox'></i>
              No claims submitted yet.
            </div>
          <?php else: ?>
            <div class="claims-grid" id="claims-grid">
              <?php foreach ($all_claims as $c):
                $status_norm = strtolower(trim($c['status']));
                $item_photo  = resolve_photo($c['item_photo'] ?? '');
              ?>
                <div class="claim-card status-<?= $status_norm ?>" data-status="<?= $status_norm ?>">

                  <!-- Photo -->
                  <?php if ($item_photo): ?>
                    <img class="claim-thumb" src="<?= htmlspecialchars($item_photo) ?>"
                         alt="<?= htmlspecialchars($c['item_title'] ?? 'Item') ?>">
                  <?php else: ?>
                    <div class="claim-thumb-placeholder"><i class='bx bx-image'></i></div>
                  <?php endif; ?>

                  <!-- Info -->
                  <div class="claim-info">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                      <span class="claimant"><?= htmlspecialchars($c['claimant_name']) ?></span>
                      <?= status_badge($c['status']) ?>
                    </div>
                    <div class="contact">
                      <i class='bx bx-phone' style="font-size:13px;"></i>
                      <?= htmlspecialchars($c['claimant_contact']) ?>
                    </div>

                    <div class="item-name">
                      Claiming <strong><?= htmlspecialchars($c['item_title'] ?: '(unknown item)') ?></strong>
                      <span style="color:#94a3b8;font-size:12px;">
                        (<?= htmlspecialchars(ucfirst($c['item_type'])) ?> #<?= (int)$c['item_id'] ?>)
                      </span>
                    </div>

                    <?php if (!empty($c['item_location'])): ?>
                      <div style="font-size:12px;color:#94a3b8;margin-top:2px;">
                        <i class='bx bx-map-pin'></i>
                        <?= htmlspecialchars($c['item_location']) ?>
                      </div>
                    <?php endif; ?>

                    <?php if (!empty($c['message'])): ?>
                      <div class="message">
                        <i class='bx bx-message-detail' style="font-size:13px;"></i>
                        <?= nl2br(htmlspecialchars($c['message'])) ?>
                      </div>
                    <?php endif; ?>

                    <div class="meta">
                      Submitted: <?= htmlspecialchars($c['created_at']) ?>
                      &nbsp;·&nbsp; Claim #<?= (int)$c['id'] ?>
                    </div>
                  </div>

                  <!-- Actions (only show for pending) -->
                  <?php if ($status_norm === 'pending'): ?>
                    <div class="claim-actions-col">
                      <form method="POST" action="admin_action.php" style="margin:0;">
                        <input type="hidden" name="csrf"      value="<?= $csrf ?>">
                        <input type="hidden" name="action"    value="approve_claim">
                        <input type="hidden" name="claim_id"  value="<?= (int)$c['id'] ?>">
                        <input type="hidden" name="item_type" value="<?= htmlspecialchars($c['item_type']) ?>">
                        <input type="hidden" name="item_id"   value="<?= (int)$c['item_id'] ?>">
                        <button class="btn btn-approve" type="submit">
                          <i class='bx bx-check'></i> Approve
                        </button>
                      </form>

                      <form method="POST" action="admin_action.php" style="margin:0;">
                        <input type="hidden" name="csrf"      value="<?= $csrf ?>">
                        <input type="hidden" name="action"    value="reject_claim">
                        <input type="hidden" name="claim_id"  value="<?= (int)$c['id'] ?>">
                        <input type="hidden" name="item_type" value="<?= htmlspecialchars($c['item_type']) ?>">
                        <input type="hidden" name="item_id"   value="<?= (int)$c['item_id'] ?>">
                        <button class="btn btn-reject" type="submit">
                          <i class='bx bx-x'></i> Reject
                        </button>
                      </form>
                    </div>
                  <?php elseif ($status_norm === 'approved'): ?>
                    <div class="claim-actions-col">
                      <!-- Allow reversing an approved claim back to pending -->
                      <form method="POST" action="admin_action.php" style="margin:0;">
                        <input type="hidden" name="csrf"      value="<?= $csrf ?>">
                        <input type="hidden" name="action"    value="reopen_claim">
                        <input type="hidden" name="claim_id"  value="<?= (int)$c['id'] ?>">
                        <input type="hidden" name="item_type" value="<?= htmlspecialchars($c['item_type']) ?>">
                        <input type="hidden" name="item_id"   value="<?= (int)$c['item_id'] ?>">
                        <button class="btn btn-sm btn-reject" type="submit"
                                onclick="return confirm('Revert this approval back to Pending?')">
                          <i class='bx bx-undo'></i> Revert
                        </button>
                      </form>
                    </div>
                  <?php elseif ($status_norm === 'rejected'): ?>
                    <div class="claim-actions-col">
                      <!-- Allow re-opening a rejected claim -->
                      <form method="POST" action="admin_action.php" style="margin:0;">
                        <input type="hidden" name="csrf"      value="<?= $csrf ?>">
                        <input type="hidden" name="action"    value="reopen_claim">
                        <input type="hidden" name="claim_id"  value="<?= (int)$c['id'] ?>">
                        <input type="hidden" name="item_type" value="<?= htmlspecialchars($c['item_type']) ?>">
                        <input type="hidden" name="item_id"   value="<?= (int)$c['item_id'] ?>">
                        <button class="btn btn-sm btn-update" type="submit"
                                onclick="return confirm('Re-open this claim for review?')">
                          <i class='bx bx-refresh'></i> Re-open
                        </button>
                      </form>
                    </div>
                  <?php endif; ?>

                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── ITEMS SECTION ─────────────────────────────────── -->
      <div class="section-card" id="items-section">
        <div class="section-head">
          <i class='bx bx-list-ul' style="color:#4B6EF6;font-size:20px;"></i>
          <h2>Items</h2>
          <div class="tabs" style="margin-left:auto;">
            <button class="tab-btn active" id="tab-lost-btn"  onclick="switchItemTab('lost',this)">
              Lost (<?= count($lost_items) ?>)
            </button>
            <button class="tab-btn" id="tab-found-btn" onclick="switchItemTab('found',this)">
              Found (<?= count($found_items) ?>)
            </button>
          </div>
        </div>

        <!-- Lost items tab -->
        <div class="tab-panel active" id="tab-lost">
          <?php if (empty($lost_items)): ?>
            <div class="section-body">
              <div class="empty-state"><i class='bx bx-inbox'></i> No lost items.</div>
            </div>
          <?php else: ?>
            <div class="tbl-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Photo</th><th>Title</th><th>Description</th>
                    <th>Date Lost</th><th>Location</th><th>Status</th><th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($lost_items as $it): ?>
                    <tr>
                      <td>
                        <?php $p = resolve_photo($it['photo'] ?? ''); ?>
                        <?php if ($p): ?>
                          <img class="thumb" src="<?= htmlspecialchars($p) ?>" alt="">
                        <?php else: ?>
                          <div class="thumb-placeholder"><i class='bx bx-image'></i></div>
                        <?php endif; ?>
                      </td>
                      <td style="font-weight:600;"><?= htmlspecialchars($it['title']) ?></td>
                      <td style="max-width:240px;white-space:normal;"><?= htmlspecialchars($it['description']) ?></td>
                      <td><?= htmlspecialchars($it['date_lost']) ?></td>
                      <td><?= htmlspecialchars($it['location_lost']) ?></td>
                      <td>
                        <form class="status-form" method="POST" action="admin_action.php" style="margin:0;">
                          <input type="hidden" name="csrf"   value="<?= $csrf ?>">
                          <input type="hidden" name="type"   value="lost">
                          <input type="hidden" name="id"     value="<?= (int)$it['id'] ?>">
                          <input type="hidden" name="action" value="update_status">
                          <select name="new_status" class="status-select">
                            <?php foreach ($statuses as $s): ?>
                              <option value="<?= $s ?>" <?= strcasecmp($it['status'] ?? '', $s) === 0 ? 'selected' : '' ?>>
                                <?= $s ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </form>
                      </td>
                      <td>
                        <div class="actions-cell">
                          <button type="button" class="btn btn-sm btn-update trigger-status-update"
                                  data-form-index="lost-<?= (int)$it['id'] ?>">
                            <i class='bx bx-check'></i> Update
                          </button>
                          <form method="POST" action="admin_action.php" style="margin:0;"
                                onsubmit="return confirm('Delete this lost item?')">
                            <input type="hidden" name="csrf"   value="<?= $csrf ?>">
                            <input type="hidden" name="type"   value="lost">
                            <input type="hidden" name="id"     value="<?= (int)$it['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-sm btn-delete">
                              <i class='bx bx-trash'></i> Delete
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Found items tab -->
        <div class="tab-panel" id="tab-found">
          <?php if (empty($found_items)): ?>
            <div class="section-body">
              <div class="empty-state"><i class='bx bx-inbox'></i> No found items.</div>
            </div>
          <?php else: ?>
            <div class="tbl-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Photo</th><th>Title</th><th>Description</th>
                    <th>Date Found</th><th>Location</th><th>Status</th><th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($found_items as $it): ?>
                    <tr>
                      <td>
                        <?php $p = resolve_photo($it['photo'] ?? ''); ?>
                        <?php if ($p): ?>
                          <img class="thumb" src="<?= htmlspecialchars($p) ?>" alt="">
                        <?php else: ?>
                          <div class="thumb-placeholder"><i class='bx bx-image'></i></div>
                        <?php endif; ?>
                      </td>
                      <td style="font-weight:600;"><?= htmlspecialchars($it['title']) ?></td>
                      <td style="max-width:240px;white-space:normal;"><?= htmlspecialchars($it['description']) ?></td>
                      <td><?= htmlspecialchars($it['date_found']) ?></td>
                      <td><?= htmlspecialchars($it['location_found']) ?></td>
                      <td>
                        <form class="status-form" method="POST" action="admin_action.php" style="margin:0;">
                          <input type="hidden" name="csrf"   value="<?= $csrf ?>">
                          <input type="hidden" name="type"   value="found">
                          <input type="hidden" name="id"     value="<?= (int)$it['id'] ?>">
                          <input type="hidden" name="action" value="update_status">
                          <select name="new_status" class="status-select">
                            <?php foreach ($statuses as $s): ?>
                              <option value="<?= $s ?>" <?= strcasecmp($it['status'] ?? '', $s) === 0 ? 'selected' : '' ?>>
                                <?= $s ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </form>
                      </td>
                      <td>
                        <div class="actions-cell">
                          <button type="button" class="btn btn-sm btn-update trigger-status-update">
                            <i class='bx bx-check'></i> Update
                          </button>
                          <form method="POST" action="admin_action.php" style="margin:0;"
                                onsubmit="return confirm('Delete this found item?')">
                            <input type="hidden" name="csrf"   value="<?= $csrf ?>">
                            <input type="hidden" name="type"   value="found">
                            <input type="hidden" name="id"     value="<?= (int)$it['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-sm btn-delete">
                              <i class='bx bx-trash'></i> Delete
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── AUDIT LOG ──────────────────────────────────────── -->
      <div class="section-card" id="audit-section">
        <div class="section-head">
          <i class='bx bx-history' style="color:#4B6EF6;font-size:20px;"></i>
          <h2>Recent Audit Log</h2>
          <span class="count-badge"><?= count($audit_logs) ?> entries</span>
        </div>
        <div class="tbl-wrap">
          <?php if (empty($audit_logs)): ?>
            <div class="section-body">
              <div class="empty-state"><i class='bx bx-history'></i> No audit entries yet.</div>
            </div>
          <?php else: ?>
            <table>
              <thead>
                <tr><th>Time</th><th>Admin</th><th>Action</th><th>Target</th><th>Details</th></tr>
              </thead>
              <tbody>
                <?php foreach ($audit_logs as $a): ?>
                  <tr>
                    <td style="white-space:nowrap;font-size:12px;color:#94a3b8;">
                      <?= htmlspecialchars($a['created_at']) ?>
                    </td>
                    <td><?= htmlspecialchars($a['admin_username'] ?? 'Admin') ?></td>
                    <td>
                      <code style="background:#f1f5f9;padding:2px 7px;border-radius:5px;font-size:12px;">
                        <?= htmlspecialchars($a['action']) ?>
                      </code>
                    </td>
                    <td style="font-size:13px;">
                      <?= htmlspecialchars(($a['target_type'] ?: '') . ($a['target_id'] ? " #" . $a['target_id'] : '')) ?>
                    </td>
                    <td style="font-size:13px;max-width:300px;white-space:normal;">
                      <?= nl2br(htmlspecialchars($a['details'] ?? '')) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /layout -->

<script>
// ── Claim filter ─────────────────────────────────────────────────────────────
function filterClaims(status, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#claims-grid .claim-card').forEach(card => {
    const show = status === 'all' || card.dataset.status === status;
    card.style.display = show ? '' : 'none';
  });
}

// ── Items tab switch ──────────────────────────────────────────────────────────
function switchItemTab(tab, btn) {
  document.querySelectorAll('#items-section .tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('tab-lost').classList.toggle('active', tab === 'lost');
  document.getElementById('tab-found').classList.toggle('active', tab === 'found');
}

// ── Update status button wires up to the select form in the same row ─────────
document.addEventListener('click', e => {
  const btn = e.target.closest('.trigger-status-update');
  if (!btn) return;
  const tr = btn.closest('tr');
  if (!tr) return;
  const form = tr.querySelector('.status-form');
  if (form) form.submit();
});

// ── Sidebar scroll links ──────────────────────────────────────────────────────
document.querySelectorAll('.sidebar-link[href^="#"]').forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    const id = link.getAttribute('href').slice(1);
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
});
</script>
</body>
</html>