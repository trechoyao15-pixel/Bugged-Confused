<?php
session_start();
require_once __DIR__ . '/db.php';

// ── Helpers ──────────────────────────────────────────────────────────────────
function is_ajax(): bool {
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
}

function send_json(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function redirect(string $msg, bool $is_error = false): void {
    $_SESSION[$is_error ? 'error' : 'success'] = $msg;
    header('Location: admin_dashboard.php');
    exit;
}

function audit(mysqli $conn, int $admin_id, string $action,
               ?string $target_type = null, ?int $target_id = null,
               ?string $details = null): void {
    $stmt = $conn->prepare(
        "INSERT INTO audit_logs (admin_id, action, target_type, target_id, details)
         VALUES (?, ?, ?, ?, ?)"
    );
    if (!$stmt) return;
    $stmt->bind_param('issis', $admin_id, $action, $target_type, $target_id, $details);
    $stmt->execute();
    $stmt->close();
}

// ── Auth guard ───────────────────────────────────────────────────────────────
if (empty($_SESSION['admin_logged_in'])) {
    if (is_ajax()) send_json(['success' => false, 'message' => 'Forbidden'], 403);
    redirect('You must be logged in as admin.', true);
}

$admin_id = (int)($_SESSION['admin_id'] ?? 1);

// ── CSRF check ───────────────────────────────────────────────────────────────
$csrf = $_POST['csrf'] ?? '';
if (empty($csrf) || !hash_equals($_SESSION['admin_csrf'] ?? '', $csrf)) {
    if (is_ajax()) send_json(['success' => false, 'message' => 'Invalid CSRF token.'], 400);
    redirect('Invalid CSRF token.', true);
}

$action = trim($_POST['action'] ?? '');

// ════════════════════════════════════════════════════════════════════════════
//  CLAIM ACTIONS
// ════════════════════════════════════════════════════════════════════════════
if (in_array($action, ['approve_claim', 'reject_claim', 'reopen_claim'], true)) {

    $claim_id  = isset($_POST['claim_id'])  ? (int)$_POST['claim_id']  : 0;
    $item_type = trim($_POST['item_type']   ?? '');
    $item_id   = isset($_POST['item_id'])   ? (int)$_POST['item_id']   : 0;

    if ($claim_id <= 0) redirect('Invalid claim ID.', true);

    // Verify the claim exists
    $chk = $conn->prepare("SELECT id, status FROM claims WHERE id = ? LIMIT 1");
    $chk->bind_param('i', $claim_id);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$existing) redirect("Claim #{$claim_id} not found.", true);

    $item_table = in_array($item_type, ['lost', 'found'], true)
        ? ($item_type === 'lost' ? 'lost_items' : 'found_items')
        : null;

    // ── Approve ──────────────────────────────────────────────────────────────
    if ($action === 'approve_claim') {

        // Update claim → approved
        $s = $conn->prepare("UPDATE claims SET status = 'approved', admin_id = ? WHERE id = ?");
        $s->bind_param('ii', $admin_id, $claim_id);
        $s->execute(); $s->close();

        // Mark item as Returned
        if ($item_table && $item_id > 0) {
            $s2 = $conn->prepare("UPDATE {$item_table} SET status = 'Returned' WHERE id = ?");
            $s2->bind_param('i', $item_id); $s2->execute(); $s2->close();

            // Reject all OTHER pending claims for the same item
            $s3 = $conn->prepare(
                "UPDATE claims SET status = 'rejected', admin_id = ?
                 WHERE item_type = ? AND item_id = ? AND id <> ? AND status = 'pending'"
            );
            $s3->bind_param('isii', $admin_id, $item_type, $item_id, $claim_id);
            $s3->execute(); $s3->close();
        }

        audit($conn, $admin_id, 'approve_claim', $item_type, $item_id,
              "Claim #{$claim_id} approved. Item marked Returned.");

        redirect("Claim #{$claim_id} approved — item marked as Returned.");
    }

    // ── Reject ───────────────────────────────────────────────────────────────
    if ($action === 'reject_claim') {

        $s = $conn->prepare("UPDATE claims SET status = 'rejected', admin_id = ? WHERE id = ?");
        $s->bind_param('ii', $admin_id, $claim_id);
        $s->execute(); $s->close();

        // Only revert item to Unclaimed if no other pending/approved claims remain
        if ($item_table && $item_id > 0) {
            $chk2 = $conn->prepare(
                "SELECT COUNT(*) AS cnt FROM claims
                 WHERE item_type = ? AND item_id = ? AND id <> ?
                   AND status IN ('pending','approved')"
            );
            $chk2->bind_param('sii', $item_type, $item_id, $claim_id);
            $chk2->execute();
            $remaining = (int)($chk2->get_result()->fetch_assoc()['cnt'] ?? 0);
            $chk2->close();

            if ($remaining === 0) {
                $s2 = $conn->prepare("UPDATE {$item_table} SET status = 'Unclaimed' WHERE id = ?");
                $s2->bind_param('i', $item_id); $s2->execute(); $s2->close();
            }
        }

        audit($conn, $admin_id, 'reject_claim', $item_type, $item_id,
              "Claim #{$claim_id} rejected.");

        redirect("Claim #{$claim_id} rejected.");
    }

    // ── Re-open (revert approved/rejected → pending) ──────────────────────────
    if ($action === 'reopen_claim') {
        $s = $conn->prepare("UPDATE claims SET status = 'pending', admin_id = NULL WHERE id = ?");
        $s->bind_param('i', $claim_id); $s->execute(); $s->close();

        // Put item back to Pending
        if ($item_table && $item_id > 0) {
            $s2 = $conn->prepare("UPDATE {$item_table} SET status = 'Pending' WHERE id = ?");
            $s2->bind_param('i', $item_id); $s2->execute(); $s2->close();
        }

        audit($conn, $admin_id, 'reopen_claim', $item_type, $item_id,
              "Claim #{$claim_id} re-opened for review.");

        redirect("Claim #{$claim_id} re-opened and set back to Pending.");
    }
}

// ════════════════════════════════════════════════════════════════════════════
//  ITEM ACTIONS  (update_status / delete)
// ════════════════════════════════════════════════════════════════════════════
$type = trim($_POST['type'] ?? '');
$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!in_array($type, ['lost', 'found'], true) || $id <= 0) {
    redirect('Bad request: missing type or id.', true);
}

$table = $type === 'lost' ? 'lost_items' : 'found_items';

// ── Update status ─────────────────────────────────────────────────────────────
if ($action === 'update_status') {
    $new_status = trim($_POST['new_status'] ?? '');
    if (!in_array($new_status, ['Unclaimed', 'Pending', 'Returned'], true)) {
        redirect('Invalid status value.', true);
    }

    $stmt = $conn->prepare("UPDATE {$table} SET status = ? WHERE id = ?");
    if (!$stmt) { redirect('Database error.', true); }
    $stmt->bind_param('si', $new_status, $id);

    if ($stmt->execute()) {
        $stmt->close();
        audit($conn, $admin_id, 'update_status', $type, $id, "Status set to {$new_status}");
        redirect("Status updated to {$new_status}.");
    }
    $err = $stmt->error; $stmt->close();
    error_log("admin_action.php update_status failed: {$err}");
    redirect('Failed to update status.', true);
}

// ── Delete item ───────────────────────────────────────────────────────────────
if ($action === 'delete') {
    // Fetch photo path before deleting
    $stmt = $conn->prepare("SELECT photo FROM {$table} WHERE id = ?");
    $stmt->bind_param('i', $id); $stmt->execute();
    $row   = $stmt->get_result()->fetch_assoc();
    $photo = $row['photo'] ?? null;
    $stmt->close();

    // Delete related claims first
    $dc = $conn->prepare("DELETE FROM claims WHERE item_type = ? AND item_id = ?");
    $dc->bind_param('si', $type, $id); $dc->execute(); $dc->close();

    $stmt = $conn->prepare("DELETE FROM {$table} WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        $stmt->close();

        // Safe unlink
        if ($photo) {
            $uploadsDir = realpath(__DIR__ . '/uploads');
            $candidate  = realpath(__DIR__ . '/' . ltrim($photo, '/'));
            if ($uploadsDir && $candidate && strpos($candidate, $uploadsDir) === 0) {
                @unlink($candidate);
            }
        }

        audit($conn, $admin_id, 'delete', $type, $id,
              "Deleted {$type} item #{$id}. Photo: " . ($photo ?: 'none'));
        redirect('Item deleted successfully.');
    }

    $err = $stmt->error; $stmt->close();
    error_log("admin_action.php delete failed: {$err}");
    redirect('Failed to delete item.', true);
}

redirect('Unknown action.', true);