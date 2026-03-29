<?php
session_start();
require_once __DIR__ . '/db.php';

function gv($k) {
    return isset($_GET[$k]) ? trim((string)$_GET[$k]) : '';
}

function normalize_status_display($s) {
    $s = strtolower(trim((string)$s));
    if ($s === 'returned') return 'Returned';
    if ($s === 'pending') return 'Pending';
    return 'Unclaimed';
}

$type = strtolower(gv('type'));
$q = gv('q');                   
$location = gv('location');
$date_from = gv('date_from');
$date_to = gv('date_to');

$tables = [];
if ($type === 'lost') $tables = ['lost_items'];
elseif ($type === 'found') $tables = ['found_items'];
else $tables = ['lost_items', 'found_items'];

$results = [];

foreach ($tables as $tbl) {
    if ($tbl === 'lost_items') {
        $cols = "id, title, description, photo, date_lost AS date, location_lost AS location, status";
        $item_type_value = 'lost';
    } else {
        $cols = "id, title, description, photo, date_found AS date, location_found AS location, status";
        $item_type_value = 'found';
    }

    $sql = "SELECT {$cols} FROM {$tbl} WHERE 1=1";
    $params = [];
    $types = '';

    if ($q !== '') {
        $sql .= " AND (title LIKE ? OR description LIKE ?)";
        $like = '%' . $q . '%';
        $params[] = $like; $params[] = $like;
        $types .= 'ss';
    }

    if ($location !== '') {
        $sql .= " AND location_" . ($tbl === 'lost_items' ? "lost" : "found") . " LIKE ?";
        $params[] = '%' . $location . '%';
        $types .= 's';
    }

    if ($date_from !== '') {
        $sql .= " AND " . ($tbl === 'lost_items' ? "date_lost" : "date_found") . " >= ?";
        $params[] = $date_from;
        $types .= 's';
    }
    if ($date_to !== '') {
        $sql .= " AND " . ($tbl === 'lost_items' ? "date_lost" : "date_found") . " <= ?";
        $params[] = $date_to;
        $types .= 's';
    }

    $sql .= " ORDER BY " . ($tbl === 'lost_items' ? "date_lost" : "date_found") . " DESC LIMIT 500";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("search.php prepare failed for {$tbl}: " . $conn->error);
        continue;
    }

    if (!empty($params)) {
        $bind_names = [];
        $bind_types = $types;
        $bind_names[] = & $bind_types;
        for ($i = 0; $i < count($params); $i++) {
            $bind_names[] = & $params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }

    if (!$stmt->execute()) {
        error_log("search.php execute failed for {$tbl}: " . $stmt->error);
        $stmt->close();
        continue;
    }

    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['item_type'] = $item_type_value;
            $results[] = $row;
        }
    }
    $stmt->close();
}

usort($results, function($a, $b) {
    $da = strtotime($a['date'] ?? '0');
    $db = strtotime($b['date'] ?? '0');
    return $db <=> $da;
});

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Advanced Search — LTMS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="index.css">
  <link rel="stylesheet" href="dashboard.css">
  <style>
    .search-form { display:flex; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:18px; }
    .search-form input[type="text"], .search-form input[type="date"], .search-form select { padding:8px 10px; border-radius:8px; border:1px solid #ddd; }
    .search-form .btn { padding:8px 12px; border-radius:8px; }
    .search-meta { margin-bottom:12px; color:#333; }
    .results-count { margin-bottom:12px; font-weight:700; }
  </style>
</head>
<body>
  <div class="noise-layer" aria-hidden="true"></div>

  <header class="site-header" role="banner">
    <div class="wrap header-inner">
      <a class="brand" href="index.php" aria-label="Lost and Found Home">
        <svg class="brand-mark" width="36" height="36" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path fill="#4B6EF6" d="M12 2C8 2 4 4 4 8v8c0 4 4 6 8 6s8-2 8-6V8c0-4-4-6-8-6zm0 2c2.8 0 5 1.2 5 4v8c0 2.8-2.2 4-5 4s-5-1.2-5-4V8c0-2.8 2.2-4 5-4z"></path>
          <circle cx="12" cy="12" r="2.5" fill="#fff"></circle>
        </svg>
        <span class="brand-text">LTMS</span>
      </a>

      <nav class="main-nav" role="navigation" aria-label="Main navigation">
        <button class="nav-toggle" aria-controls="nav-list" aria-expanded="false" aria-label="Toggle navigation">
          <i class='bx bx-menu'></i>
        </button>

        <ul id="nav-list" class="nav-list">
          <li><a href="index.php">Home</a></li>
          <li><a href="lost.php">Report Lost</a></li>
          <li><a href="found.php">Report Found</a></li>
          <li><a href="lost_list.php">Lost Items</a></li>
          <li><a href="found_list.php">Found Items</a></li>
        </ul>

        <div class="nav-actions">
          <a class="btn btn-outline" href="search.php" aria-label="Search"><i class='bx bx-search'></i> Search</a>
          <?php if (!empty($_SESSION['user_id'])): ?>
            <span>Hello, <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a class="btn btn-outline" href="logout.php">Logout</a>
          <?php elseif (!empty($_SESSION['admin_logged_in'])): ?>
            <span>Admin: <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
            <a class="btn btn-outline" href="dashboard.php">Dashboard</a>
            <a class="btn btn-outline" href="logout.php">Logout</a>
          <?php else: ?>
            <a class="btn btn-outline" href="SignUp_LogIn_Form.php">Sign Up / Login</a>
          <?php endif; ?>
        </div>
      </nav>
    </div>
  </header>

  <div class="wrap admin-wrap">

    <h1 class="section-title">Advanced Search</h1>

    <form class="search-form" method="get" action="search.php" role="search" aria-label="Advanced search form">
      <select name="type" aria-label="Type">
        <option value="" <?= $type === '' ? 'selected' : '' ?>>All types</option>
        <option value="lost" <?= $type === 'lost' ? 'selected' : '' ?>>Lost</option>
        <option value="found" <?= $type === 'found' ? 'selected' : '' ?>>Found</option>
      </select>

      <input type="text" name="q" placeholder="Keyword (title, description)" value="<?= htmlspecialchars($q) ?>" aria-label="Keyword">
      <input type="text" name="location" placeholder="Location" value="<?= htmlspecialchars($location) ?>" aria-label="Location">

      <label style="display:flex;gap:6px;align-items:center;">
        <span class="muted" style="font-weight:600;">From</span>
        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
      </label>
      <label style="display:flex;gap:6px;align-items:center;">
        <span class="muted" style="font-weight:600;">To</span>
        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
      </label>

      <button class="btn btn-primary" type="submit">Search</button>
      <a class="btn btn-outline" href="search.php">Reset</a>
    </form>

    <div class="card">
      <div class="search-meta">
        <div class="results-count"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?></div>
        <div>Filters: <?= ($type ? ucfirst(htmlspecialchars($type)) : 'All') ?>,
            <?= $q ? 'q="' . htmlspecialchars($q) . '",' : '' ?>
            <?= $location ? ' location="' . htmlspecialchars($location) . '",' : '' ?>
            <?= $date_from ? ' from=' . htmlspecialchars($date_from) . ',' : '' ?>
            <?= $date_to ? ' to=' . htmlspecialchars($date_to) . ',' : '' ?>
        </div>
      </div>

      <div class="table-wrapper">
        <table class="items-table" aria-describedby="search-results">
          <thead>
            <tr>
              <th>Photo</th>
              <th>Title</th>
              <th>Description</th>
              <th>Date</th>
              <th>Location</th>
              <th>Type</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($results)): ?>
              <tr><td colspan="7" class="muted">No results found</td></tr>
            <?php else: ?>
              <?php foreach ($results as $r): ?>
                <tr>
                  <td>
                    <?php
                      $photo = trim((string)($r['photo'] ?? ''));
                      $url = '';
                      if ($photo !== '') {
                        $url = preg_match('#^https?://#i', $photo) ? $photo : ltrim($photo, '/');
                      }
                      if ($url && file_exists(__DIR__ . '/' . $url)) {
                          echo '<img class="thumb" src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($r['title'] ?? '') . '">';
                      } else {
                          echo '<div class="thumb thumb-placeholder" aria-hidden="true"></div>';
                      }
                    ?>
                  </td>
                  <td><?= htmlspecialchars($r['title'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['description'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['date'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['location'] ?? '') ?></td>
                  <td><?= htmlspecialchars(ucfirst($r['item_type'] ?? '')) ?></td>
                  <td><?= htmlspecialchars(normalize_status_display($r['status'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

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
  </script>
</body>
</html>