<?php
?>
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

<div id="loginPopup" class="login-popup" role="dialog" aria-modal="true" aria-hidden="true" style="display:none;">
  <div class="login-popup-inner" role="document">
    <div class="login-popup-body">
      <strong>Sign in required</strong>
      <div class="login-popup-text">You need to sign in to submit a report or a claim.</div>
    </div>
    <div class="login-popup-actions">
      <a id="loginPopupSignIn" class="btn btn-primary" href="SignUp_LogIn_Form.php">Sign In / Register</a>
      <button id="loginPopupClose" class="btn btn-outline" type="button">Close</button>
    </div>
  </div>
</div>

<style>
.login-popup {
  position: fixed;
  right: 20px;
  bottom: 20px;
  z-index: 2200;
  max-width: 340px;
  width: calc(100% - 40px);
  box-shadow: 0 14px 40px rgba(2,6,23,0.18);
  border-radius: 12px;
  background: #fff;
  border: 1px solid rgba(15,23,42,0.06);
  display: none;
  animation: popup-in .18s ease;
}
.login-popup[aria-hidden="false"] { display: block; }
.login-popup-inner { display:flex; align-items:center; gap:12px; padding:12px; }
.login-popup-body { flex:1; }
.login-popup-body strong { display:block; margin-bottom:6px; }
.login-popup-text { color:var(--muted); font-size:13px; }
.login-popup-actions { display:flex; gap:8px; align-items:center; }
@keyframes popup-in { from { transform: translateY(8px); opacity:0 } to { transform: translateY(0); opacity:1 } }

.login-popup .btn { padding:8px 12px; border-radius:8px; font-weight:700; }
.login-popup .btn-primary { background: var(--accent); color:#fff; }
.login-popup .btn-outline { background:#fff; border:1px solid rgba(15,23,42,0.06); color:var(--muted); }
</style>

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

  (function () {
    const popup = document.getElementById('loginPopup');
    const closeBtn = document.getElementById('loginPopupClose');

    window.showLoginPopup = function () {
      if (!popup) return;
      popup.setAttribute('aria-hidden', 'false');
      popup.style.display = 'block';
      if (window.__loginPopupTimer) clearTimeout(window.__loginPopupTimer);
      window.__loginPopupTimer = setTimeout(hideLoginPopup, 9000);
    };

    function hideLoginPopup() {
      if (!popup) return;
      popup.setAttribute('aria-hidden', 'true');
      popup.style.display = 'none';
      if (window.__loginPopupTimer) { clearTimeout(window.__loginPopupTimer); window.__loginPopupTimer = null; }
    }

    window.showLoginModal = window.showLoginPopup;
    window.hideLoginModal = hideLoginPopup;

    closeBtn?.addEventListener('click', hideLoginPopup);
  })();
</script>