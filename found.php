<?php
session_start();
require_once __DIR__ . '/db.php';

$form_message = '';
$form_message_type = '';
if (!empty($_SESSION['success'])) {
    $form_message = $_SESSION['success'];
    $form_message_type = 'success';
    unset($_SESSION['success']);
}
if (!empty($_SESSION['error'])) {
    $form_message = $_SESSION['error'];
    $form_message_type = 'error';
    unset($_SESSION['error']);
}

$logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['admin_logged_in']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Report Found Item — LTMS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="index.css">
</head>
<body>
  <div class="noise-layer" aria-hidden="true"></div>

  <?php require_once __DIR__ . '/header.php'; ?>

  <main>
    <section class="wrap form-page">
      <div class="form-card">
        <header class="form-card-header">
          <h2>Report Found Item</h2>
          <p class="muted">Help the owner — provide clear details and a photo if possible.</p>
        </header>

        <?php if (!$logged_in): ?>
        <?php endif; ?>

        <form id="foundForm" action="save_found.php" method="POST" enctype="multipart/form-data" class="report-form" novalidate>
          <?php if ($form_message): ?>
            <div id="serverMessage" class="form-inline-message <?= ($form_message_type === 'success') ? 'success' : 'error' ?>" role="status" style="margin-bottom:12px;">
              <?= htmlspecialchars($form_message) ?>
            </div>
          <?php endif; ?>

          <div class="form-row">
            <label for="title">Item Title</label>
            <input id="title" name="title" type="text" required placeholder="e.g. Black Wallet">
          </div>

          <div class="form-row">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" required placeholder="Describe distinguishing features, any contact details left, etc."></textarea>
          </div>

          <div class="form-grid">
            <div class="form-row">
              <label for="date_found">Date Found</label>
              <input id="date_found" name="date_found" type="date" required>
            </div>

            <div class="form-row">
              <label for="location_found">Location Found</label>
              <input id="location_found" name="location_found" type="text" required placeholder="e.g. Library - Main Entrance">
            </div>
          </div>

          <div class="form-row">
            <label for="photo">Upload Photo (optional)</label>
            <input id="photo" name="photo" type="file" accept="image/*">
          </div>

          <div class="form-actions">
            <button id="submitBtn" class="btn btn-primary" type="submit" <?= $logged_in ? '' : 'disabled aria-disabled="true"' ?>><i class='bx bx-upload'></i> Submit Report</button>
            <a class="btn btn-ghost" href="found_list.php">View Found Items</a>
          </div>

          <div id="formMessage" style="margin-top:12px;"></div>
        </form>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="wrap footer-inner">
      <div class="footer-col">
        <h4>LTMS</h4>
        <p class="muted">A simple, effective lost & found platform tailored for campuses and organizations.</p>
      </div>
      <div class="footer-col">
        <h4>Links</h4>
        <ul>
          <li><a href="lost.php">Report Lost</a></li>
          <li><a href="lost_list.php">Lost Items</a></li>
          <li><a href="SignUp_LogIn_Form.php">Sign Up / Login</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Contact</h4>
        <p class="muted">ltms@gmail.com</p>
      </div>
    </div>

    <div class="site-copyright">
      <div class="wrap">© <span id="year"></span> LTMS — All rights reserved.</div>
    </div>
  </footer>

  <script>
  (function () {
    const form = document.getElementById('foundForm');
    const submitBtn = document.getElementById('submitBtn');
    const msg = document.getElementById('formMessage');
    const serverMsg = document.getElementById('serverMessage');

    window.LOGGED_IN = <?= $logged_in ? 'true' : 'false' ?>;

    if (serverMsg) {
      serverMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function redirectToLoginWithReturn(focusId) {
      const returnTo = location.pathname + location.search;
      let url = 'SignUp_LogIn_Form.php?return_to=' + encodeURIComponent(returnTo);
      if (focusId) url += '&focus=' + encodeURIComponent(focusId);
      window.location.href = url;
    }

    document.addEventListener('focusin', function (e) {
      if (window.LOGGED_IN) return;
      const t = e.target;
      if (!t) return;
      if (t.matches('input, textarea, select, [contenteditable]')) {
        const id = t.id || '';
        redirectToLoginWithReturn(id);
      }
    });

    document.addEventListener('DOMContentLoaded', function () {
      const hash = decodeURIComponent(location.hash || '').replace(/^#/, '');
      if (hash) {
        const el = document.getElementById(hash);
        if (el) {
          el.focus();
          if (typeof el.select === 'function') el.select();
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
    });

    if (!form) return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      msg.textContent = '';

      if (!window.LOGGED_IN) {
        redirectToLoginWithReturn('title');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';

      const title = form.querySelector('input[name="title"]').value.trim();
      const description = form.querySelector('textarea[name="description"]').value.trim();
      const dateFound = form.querySelector('input[name="date_found"]').value;
      const locationFound = form.querySelector('input[name="location_found"]').value.trim();

      if (!title || !description || !dateFound || !locationFound) {
        msg.style.color = 'crimson';
        msg.textContent = 'Please fill all required fields.';
        submitBtn.disabled = false;
        submitBtn.innerHTML = "<i class='bx bx-upload'></i> Submit Report";
        return;
      }

      const fd = new FormData(form);

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: fd,
          headers: { 'Accept': 'application/json, text/html' }
        });

        if (response.redirected) {
          window.location.href = response.url;
          return;
        }

        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch (err) { data = null; }

        if (data) {
          if (data.success) {
            if (data.redirect) {
              window.location.href = data.redirect;
            } else {
              msg.style.color = 'green';
              msg.textContent = data.message || 'Report submitted successfully.';
              form.reset();
            }
          } else {
            if (response.status === 401) {
              redirectToLoginWithReturn('title');
              return;
            }
            msg.style.color = 'crimson';
            msg.textContent = data.message || 'Server error.';
          }
        } else {
          if (text.trim().length === 0) {
            msg.style.color = 'crimson';
            msg.textContent = 'No response from server. Check server logs or ensure save_found.php exists.';
          } else {
            msg.style.color = 'crimson';
            msg.innerHTML = '<strong>Server response:</strong><pre style="white-space:pre-wrap;max-height:160px;overflow:auto;border:1px solid #eee;padding:8px;margin-top:8px;">' + text.slice(0,400) + (text.length>400? "\\n...":'') + '</pre>';
          }
        }
      } catch (err) {
        console.error(err);
        msg.style.color = 'crimson';
        msg.textContent = 'Network or server error. Check console and network tab.';
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = "<i class='bx bx-upload'></i> Submit Report";
      }
    });

    const yearEl = document.getElementById('year');
    if (yearEl) yearEl.textContent = new Date().getFullYear();
  })();
  </script>
</body>
</html>