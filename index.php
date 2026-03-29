<?php
session_start();
require_once __DIR__ . '/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Lost & Found Item Tracking System — LTMS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <meta name="description" content="Quickly report, search, and recover lost belongings for your campus or organization." />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="index.css">
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

  <main>
    <section class="hero">
      <div class="wrap hero-grid">
        <div class="hero-content">
          <h1>Easily track, report, and recover lost items</h1>
          <p class="lead">Make lost-and-found painless — report items, search reports, and reunite belongings with their owners quickly. Built for campuses and organizations.</p>

          <div class="hero-ctas">
            <a class="btn btn-primary" href="lost_list.php">View Lost Items</a>
            <a class="btn btn-primary" href="found_list.php">View Found Items</a>
          </div>

          <ul class="trust-list" aria-hidden="true">
            <li><strong>Fast</strong> — submit reports in under a minute</li>
            <li><strong>Match</strong> — Search by keywords, date and location to surface likely matches between lost and found reports</li>
            <li><strong>Privacy & Verification</strong> — Claims are verified to ensure items are returned to the rightful owners only.</li>
          </ul>
        </div>

        <div class="hero-visual">
          <div class="card">
            <?php
              date_default_timezone_set('Asia/Manila');
              $q = $conn->query("SELECT * FROM lost_items ORDER BY created_at DESC LIMIT 1");
              if ($q && $q->num_rows > 0) {
                  $item = $q->fetch_assoc();
                  $title = htmlspecialchars($item['title']);
                  $loc = htmlspecialchars($item['location_lost']);
                  $date = date("M d, Y • h:i A", strtotime($item['created_at']));
                  $status = trim($item['status']);

                  $seconds = time() - strtotime($item['created_at']);
                  if ($seconds < 60) {
                      $ago = "Just now";
                  } elseif ($seconds < 3600) {
                      $ago = floor($seconds / 60) . " min ago";
                  } elseif ($seconds < 86400) {
                      $ago = floor($seconds / 3600) . " hours ago";
                  } else {
                      $ago = floor($seconds / 86400) . " days ago";
                  }

                  $photo_html = "<div class='home-thumb placeholder'><i class='bx bx-image'></i></div>";
                  if (!empty($item['photo'])) {
                      $photo_path = ltrim($item['photo'], '/');
                      if (file_exists(__DIR__ . '/' . $photo_path)) {
                          $photo_html = "<img src=\"" . htmlspecialchars($photo_path) . "\" class=\"home-thumb\" alt=\"" . htmlspecialchars($item['title']) . "\">";
                      }
                  }
            ?>
              <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <span class="pill">Latest Lost Item</span>
                <span class="time muted"><?= $ago ?></span>
              </div>

              <div class="card-body" style="display:flex;gap:12px;align-items:center;margin-top:12px;">
                <div class="item-icon">
                  <?= $photo_html ?>
                </div>

                <div>
                  <h3 style="margin:0 0 6px 0;"><?= $title ?></h3>
                  <p class="muted" style="margin:0 0 6px 0;">Lost at <?= $loc ?> — <?= $date ?></p>
                  <p class="muted" style="margin:0;"><strong>Status:</strong> <?= htmlspecialchars($status) ?></p>
                </div>
              </div>

              <div class="card-footer" style="display:flex;gap:8px;margin-top:12px;">
                <a class="btn btn-small" href="lost_list.php">View Lost Items</a>
                <a class="btn btn-small" href="found_list.php">View Found Items</a>
              </div>

            <?php
              } else {
                  echo "<p class='muted'>No lost items yet — be the first to report!</p>";
              }
            ?>
          </div>
        </div>

      </div>
    </section>

    <section class="features wrap" style="margin-top:24px;">
      <h2 class="section-title">Why use LTMS?</h2>
      <div class="feature-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:12px;">
        <article class="feature card" style="padding:16px;">
          <i class='bx bx-search-alt feature-icon' style="font-size:28px;color:var(--accent);"></i>
          <h3 style="margin-top:8px;">Advanced Search</h3>
          <p class="muted">Filter by location, date, category, and keywords to quickly locate matching reports.</p>
        </article>

        <article class="feature card" style="padding:16px;">
          <i class='bx bx-bell feature-icon' style="font-size:28px;color:var(--accent);"></i>
          <h3 style="margin-top:8px;">Notifications</h3>
          <p class="muted">Admin dashboard surfaces pending claims and likely matches so staff can review and contact claimants quickly.</p>
        </article>

        <article class="feature card" style="padding:16px;">
          <i class='bx bx-shield feature-icon' style="font-size:28px;color:var(--accent);"></i>
          <h3 style="margin-top:8px;">Privacy & Verification</h3>
          <p class="muted">Claims are verified to ensure items are returned to the rightful owners only.</p>
        </article>
      </div>
    </section>

    <section class="how-it-works wrap" style="margin-top:24px;">
      <h2 class="section-title">How it works</h2>
      <ol class="steps" style="margin-top:12px;">
        <li><strong>Report</strong> — Submit a lost or found report with details and an optional photo.</li>
        <li><strong>Match</strong> — Search by keywords, date and location to surface likely matches between lost and found reports.</li>
        <li><strong>Recover</strong> — Verified claimants pick up their items from a secure location.</li>
      </ol>
    </section>
  </main>

  <footer class="site-footer" style="margin-top:32px;">
    <div class="wrap footer-inner" style="display:flex;gap:24px;flex-wrap:wrap;">
      <div class="footer-col">
        <h4>LTMS</h4>
        <p class="muted">A simple, effective lost & found platform tailored for campuses and organizations.</p>
      </div>

      <div class="footer-col">
        <h4>Links</h4>
        <ul style="list-style:none;padding:0;margin:0;">
          <li><a href="lost_list.php">Lost Items</a></li>
          <li><a href="found_list.php">Found Items</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Contact</h4>
        <p class="muted">ltms@gmail.com</p>
      </div>
    </div>

    <div class="site-copyright" style="margin-top:12px;">
      <div class="wrap">© <?php echo date("Y"); ?> LTMS — All rights reserved.</div>
    </div>
  </footer>

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