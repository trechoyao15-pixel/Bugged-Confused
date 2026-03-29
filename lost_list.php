<?php
session_start();
require_once __DIR__ . '/db.php';

function normalize_status($s) {
    $s = strtolower(trim((string)$s));
    if ($s === 'returned') return 'Returned';
    if ($s === 'pending') return 'Pending';
    if (in_array($s, ['unclaimed', 'available', 'claimed', ''], true)) return 'Unclaimed';
    return 'Unclaimed';
}

$logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['admin_logged_in']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Lost Items — LTMS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="index.css">
</head>
<body>
<?php require_once __DIR__ . '/header.php'; ?>

<main>
  <section class="wrap list-page">
    <header class="list-header">
      <h2>Lost Items</h2>
      <p class="muted">Recent lost reports — click Claim to submit a claim for an item.</p>
    </header>

    <div class="table-card">
      <?php
      $query = $conn->query("SELECT * FROM lost_items ORDER BY created_at DESC");

      if (!$query || $query->num_rows === 0) {
          echo "<p class='muted'>No lost items yet.</p>";
      } else {
          echo "<table class='items-table'>";
          echo "<thead><tr><th>Photo</th><th>Title</th><th>Description</th><th>Date Lost</th><th>Location</th><th>Status</th><th>Claim</th></tr></thead>";
          echo "<tbody>";

          while ($row = $query->fetch_assoc()) {
              $id = (int)$row['id'];
              $status_norm = normalize_status($row['status'] ?? '');
              $display_status = htmlspecialchars($status_norm);

              $photoPath = isset($row['photo']) ? ltrim($row['photo'], '/') : '';
              $thumb = (!empty($photoPath) && file_exists(__DIR__ . '/' . $photoPath))
                  ? "<img src=\"" . htmlspecialchars($photoPath) . "\" alt=\"" . htmlspecialchars($row['title']) . "\" class=\"thumb\">"
                  : "<div class='thumb thumb-placeholder'><i class='bx bx-image'></i></div>";

              $title = htmlspecialchars($row['title']);
              $desc = htmlspecialchars($row['description']);
              $date = htmlspecialchars($row['date_lost']);
              $loc = htmlspecialchars($row['location_lost']);

              echo "<tr>
                      <td class='col-photo'>{$thumb}</td>
                      <td>{$title}</td>
                      <td>{$desc}</td>
                      <td>{$date}</td>
                      <td>{$loc}</td>
                      <td>{$display_status}</td>
                      <td>";

              if ($status_norm === 'Unclaimed') {
                  echo "<button class='btn btn-primary toggle-claim' data-id='{$id}'>Claim</button>
                        <div class='claim-area' id='claim-area-{$id}' style='display:none;margin-top:8px;'>
                          <form class='claim-form' data-id='{$id}' method='post' action='claim_submit.php'>
                            <input type='hidden' name='item_type' value='lost'>
                            <input type='hidden' name='item_id' value='{$id}'>
                            <div style='display:flex;gap:8px;margin-bottom:6px;'>
                              <input name='claimant_name' placeholder='Your name' required style='flex:1;padding:8px;border-radius:6px;border:1px solid #ddd;'>
                              <input name='claimant_contact' placeholder='Contact (email/phone)' required style='flex:1;padding:8px;border-radius:6px;border:1px solid #ddd;'>
                            </div>
                            <div style='margin-bottom:6px;'>
                              <textarea name='message' placeholder='Message (optional)' rows='2' style='width:100%;padding:8px;border-radius:6px;border:1px solid #ddd;'></textarea>
                            </div>
                            <div style='display:flex;gap:8px;align-items:center;'>
                              <button class='btn btn-primary submit-claim' type='submit'>Submit Claim</button>
                              <button type='button' class='btn btn-outline cancel-claim'>Cancel</button>
                              <div class='claim-result' style='margin-left:8px;color:#666;'></div>
                            </div>
                          </form>
                        </div>";
              } else {
                  echo "<span class='muted'>{$display_status}</span>";
              }

              echo "</td></tr>";
          }

          echo "</tbody></table>";
      }
      ?>
    </div>
  </section>
</main>

<script>
  window.LOGGED_IN = <?= $logged_in ? 'true' : 'false' ?>;

  document.addEventListener('click', (e) => {
      const t = e.target;
      if (t.matches('.toggle-claim')) {
          if (!window.LOGGED_IN) {
              if (typeof showLoginModal === 'function') {
                showLoginModal();
                return;
              } else {
                window.location.href = 'SignUp_LogIn_Form.php';
                return;
              }
          }

          const id = t.dataset.id;
          const area = document.getElementById('claim-area-' + id);
          area.style.display = area.style.display === 'none' ? 'block' : 'none';
      } else if (t.matches('.cancel-claim')) {
          const area = t.closest('.claim-area');
          if (area) area.style.display = 'none';
      }
  });

  document.addEventListener('submit', async (e) => {
      if (!e.target.matches('.claim-form')) return;
      e.preventDefault();
      if (!window.LOGGED_IN) {
          if (typeof showLoginModal === 'function') { showLoginModal(); return; }
          alert('Please sign in to submit a claim.');
          return;
      }

      const form = e.target;
      const id = form.dataset.id;
      const resultBox = form.querySelector('.claim-result');
      const submitBtn = form.querySelector('.submit-claim');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';
      resultBox.textContent = '';

      const fd = new FormData(form);

      try {
          const res = await fetch(form.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' }});
          if (res.status === 401) {
              if (typeof showLoginModal === 'function') { showLoginModal(); return; }
              resultBox.style.color = 'crimson';
              resultBox.textContent = 'Authentication required.';
              return;
          }
          const data = await res.json();

          if (data.success) {
              resultBox.style.color = 'green';
              resultBox.textContent = data.message;
              form.reset();
              setTimeout(() => document.getElementById('claim-area-' + id).style.display = 'none', 900);
              const btn = document.querySelector(`.toggle-claim[data-id='${id}']`);
              if (btn) btn.parentElement.innerHTML = "<span class='muted'>Pending</span>";
          } else {
              resultBox.style.color = 'crimson';
              resultBox.textContent = data.message;
          }
      } catch (err) {
          console.error(err);
          resultBox.style.color = 'crimson';
          resultBox.textContent = 'Network error.';
      } finally {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Submit Claim';
      }
  });
</script>
</body>
</html>