<?php
session_start();
require_once "db.php";

function is_ajax() {
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
}
function send_json($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

if (empty($_SESSION['admin_logged_in'])) {
    if (is_ajax()) send_json(['success'=>false,'message'=>'Forbidden'],403);
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$csrf = $_POST['csrf'] ?? '';

if (empty($csrf) || !hash_equals($_SESSION['admin_csrf'] ?? '', $csrf)) {
    $msg = "Invalid CSRF token.";
    error_log("action.php: {$msg}");
    if (is_ajax()) send_json(['success'=>false,'message'=>$msg],400);
    $_SESSION['error'] = $msg;
    header('Location: dashboard.php');
    exit;
}

$action = $_POST['action'] ?? '';
$type = $_POST['type'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

function audit_log($conn, $admin_id, $action, $target_type = null, $target_id = null, $details = null) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) return;
    $stmt->bind_param('issis', $admin_id, $action, $target_type, $target_id, $details);
    $stmt->execute();
    $stmt->close();
}

if ($action === 'approve_claim' || $action === 'reject_claim') {
    $claim_id = isset($_POST['claim_id']) ? (int)$_POST['claim_id'] : 0;
    if ($claim_id <= 0) {
        $_SESSION['error'] = 'Invalid claim id.';
        header('Location: dashboard.php');
        exit;
    }

    if ($action === 'approve_claim') {
        $stmt = $conn->prepare("UPDATE claims SET status = 'approved', admin_id = ? WHERE id = ?");
        $stmt->bind_param('ii', $admin_id, $claim_id);
        $stmt->execute();
        $stmt->close();

        $item_type = $_POST['item_type'] ?? '';
        $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        if (in_array($item_type, ['lost','found'], true) && $item_id > 0) {
            $table = $item_type === 'lost' ? 'lost_items' : 'found_items';
            $s2 = $conn->prepare("UPDATE {$table} SET status = 'Returned' WHERE id = ?");
            $s2->bind_param('i', $item_id);
            $s2->execute();
            $s2->close();
            audit_log($conn, $admin_id, 'approve_claim', $item_type, $item_id, "Claim #{$claim_id} approved");
        } else {
            audit_log($conn, $admin_id, 'approve_claim', null, $claim_id, "Claim #{$claim_id} approved (item update skipped)");
        }

        $_SESSION['success'] = "Claim #{$claim_id} approved.";
        header('Location: dashboard.php');
        exit;
    } else {
        $stmt = $conn->prepare("UPDATE claims SET status = 'rejected', admin_id = ? WHERE id = ?");
        $stmt->bind_param('ii', $admin_id, $claim_id);
        $stmt->execute();
        $stmt->close();

        $item_type = $_POST['item_type'] ?? '';
        $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        if (in_array($item_type, ['lost','found'], true) && $item_id > 0) {
            $table = $item_type === 'lost' ? 'lost_items' : 'found_items';
            $s2 = $conn->prepare("UPDATE {$table} SET status = 'Unclaimed' WHERE id = ?");
            $s2->bind_param('i', $item_id);
            $s2->execute();
            $s2->close();
        }

        audit_log($conn, $admin_id, 'reject_claim', null, $claim_id, "Claim #{$claim_id} rejected");
        $_SESSION['success'] = "Claim #{$claim_id} rejected.";
        header('Location: dashboard.php');
        exit;
    }
}

if (!in_array($type, ['lost','found'], true) || $id <= 0) {
    $_SESSION['error'] = 'Bad request (missing type or id).';
    header('Location: dashboard.php');
    exit;
}
$table = $type === 'lost' ? 'lost_items' : 'found_items';

if ($action === 'update_status') {
    $new_status = trim($_POST['new_status'] ?? '');
    $allowed = ['Returned','Pending','Unclaimed'];
    if (!in_array($new_status, $allowed, true)) {
        $_SESSION['error'] = 'Invalid status value.';
        header('Location: dashboard.php');
        exit;
    }

    $stmt = $conn->prepare("UPDATE {$table} SET status = ? WHERE id = ?");
    if (!$stmt) {
        error_log('action.php prepare failed: ' . $conn->error);
        $_SESSION['error'] = 'Database error.';
        header('Location: dashboard.php');
        exit;
    }
    $stmt->bind_param('si', $new_status, $id);
    if ($stmt->execute()) {
        $stmt->close();
        audit_log($conn, $admin_id, 'update_status', $type, $id, "Status set to {$new_status}");
        $_SESSION['success'] = "Status updated to {$new_status}.";
        header('Location: dashboard.php');
        exit;
    } else {
        $err = $stmt->error;
        $stmt->close();
        error_log('action.php execute failed: ' . $err);
        $_SESSION['error'] = 'Failed to update status.';
        header('Location: dashboard.php');
        exit;
    }

} elseif ($action === 'delete') {
    $stmt = $conn->prepare("SELECT photo FROM {$table} WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $photo = null;
    if ($row = $res->fetch_assoc()) $photo = $row['photo'];
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM {$table} WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $stmt->close();
        if ($photo) {
            $uploadsDir = realpath(__DIR__ . '/uploads');
            $candidate = realpath(__DIR__ . '/' . ltrim($photo, '/'));
            if ($uploadsDir && $candidate && strpos($candidate, $uploadsDir) === 0) {
                @unlink($candidate);
            } else {
                error_log("action.php: skipped unlink for photo outside uploads: {$photo}");
            }
        }
        audit_log($conn, $admin_id, 'delete', $type, $id, "Deleted item and photo: " . ($photo ?: 'none'));
        $_SESSION['success'] = 'Deleted.';
        header('Location: dashboard.php');
        exit;
    } else {
        error_log('action.php delete failed: ' . $stmt->error);
        $_SESSION['error'] = 'Failed to delete.';
        header('Location: dashboard.php');
        exit;
    }
} else {
    $_SESSION['error'] = 'Unknown action.';
    header('Location: dashboard.php');
    exit;
}