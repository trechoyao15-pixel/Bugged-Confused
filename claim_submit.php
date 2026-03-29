<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (empty($_SESSION['user_id']) && empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please sign in to submit a claim.']);
    exit;
}

$item_type = $_POST['item_type'] ?? '';
$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$claimant_name = trim($_POST['claimant_name'] ?? '');
$claimant_contact = trim($_POST['claimant_contact'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!in_array($item_type, ['lost','found'], true) || $item_id <= 0 || $claimant_name === '' || $claimant_contact === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters.']);
    exit;
}

require_once __DIR__ . '/db.php';

$table = $item_type === 'lost' ? 'lost_items' : 'found_items';

$stmt = $conn->prepare("SELECT id, status FROM {$table} WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $item_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Item not found.']);
    exit;
}

$item = $res->fetch_assoc();
$stmt->close();

function normalize_status_local($s) {
    $s = strtolower(trim((string)$s));
    if ($s === 'returned') return 'Returned';
    if ($s === 'pending') return 'Pending';
    if (in_array($s, ['unclaimed','available','claimed',''], true)) return 'Unclaimed';
    return 'Unclaimed';
}

$status_norm = normalize_status_local($item['status'] ?? '');
if ($status_norm !== 'Unclaimed') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Item cannot be claimed (not unclaimed).']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO claims (item_type, item_id, claimant_name, claimant_contact, message, status) VALUES (?, ?, ?, ?, ?, 'pending')");
$stmt->bind_param('sisss', $item_type, $item_id, $claimant_name, $claimant_contact, $message);
$ok = $stmt->execute();
if (!$ok) {
    error_log('Claim insert failed: ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to submit claim.']);
    exit;
}
$claim_id = $stmt->insert_id;
$stmt->close();

$update = $conn->prepare("UPDATE {$table} SET status = 'Pending' WHERE id = ?");
$update->bind_param('i', $item_id);
$update->execute();
$update->close();

echo json_encode(['success' => true, 'message' => 'Claim submitted successfully. Item status updated to Pending.', 'claim_id' => $claim_id]);
exit;
?>