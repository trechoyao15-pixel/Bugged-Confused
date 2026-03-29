<?php
session_start();

function send_json($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function redirect_with_error($url, $msg) {
    $_SESSION['error'] = $msg;
    header('Location: ' . $url);
    exit;
}

$acceptsJson = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
               || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

if (empty($_SESSION['user_id']) && empty($_SESSION['admin_logged_in'])) {
    if ($acceptsJson) {
        send_json(['success' => false, 'message' => 'Authentication required. Please sign in.'], 401);
    } else {
        redirect_with_error('SignUp_LogIn_Form.php', 'Please sign in to submit a found item.');
    }
}

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$date_found = trim($_POST['date_found'] ?? '');
$location_found = trim($_POST['location_found'] ?? '');

if ($title === '' || $description === '' || $date_found === '' || $location_found === '') {
    $msg = 'Please fill all required fields.';
    if ($acceptsJson) send_json(['success' => false, 'message' => $msg], 400);
    redirect_with_error('found.php', $msg);
}

$photo_path = null;
if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $msg = 'File upload error code: ' . $_FILES['photo']['error'];
        if ($acceptsJson) send_json(['success' => false, 'message' => $msg], 400);
        redirect_with_error('found.php', $msg);
    }

    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES['photo']['tmp_name'] ?? '');
    if (!in_array($mime, $allowed, true)) {
        $msg = 'Unsupported image format. Allowed: jpg, png, gif, webp.';
        if ($acceptsJson) send_json(['success' => false, 'message' => $msg], 400);
        redirect_with_error('found.php', $msg);
    }

    $uploads_dir = __DIR__ . '/uploads';
    if (!is_dir($uploads_dir)) {
        if (!mkdir($uploads_dir, 0755, true)) {
            $msg = 'Failed to create uploads directory.';
            error_log($msg);
            if ($acceptsJson) send_json(['success' => false, 'message' => $msg], 500);
            redirect_with_error('found.php', $msg);
        }
    }

    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $safe_name = bin2hex(random_bytes(12)) . '.' . strtolower($ext);
    $destination = $uploads_dir . '/' . $safe_name;
    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
        $msg = 'Failed to move uploaded file to destination.';
        error_log($msg);
        if ($acceptsJson) send_json(['success' => false, 'message' => $msg], 500);
        redirect_with_error('found.php', $msg);
    }
    $photo_path = 'uploads/' . $safe_name;
}

include __DIR__ . '/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    $msg = 'Database connection ($conn) not available from db.php';
    error_log($msg);
    if ($acceptsJson) send_json(['success' => false, 'message' => $msg], 500);
    redirect_with_error('found.php', $msg);
}

$reporter_username = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if ($reporter_username !== null) {
    $stmt = $conn->prepare("INSERT INTO found_items (title, description, date_found, location_found, photo, status, reporter_username) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log('DB prepare failed (found with reporter): ' . $conn->error);
        redirect_with_error('found.php', 'Database error.');
    }
    $status_val = 'Unclaimed';
    $stmt->bind_param('ssssssi', $title, $description, $date_found, $location_found, $photo_path, $status_val, $reporter_username);
} else {
    $stmt = $conn->prepare("INSERT INTO found_items (title, description, date_found, location_found, photo, status) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log('DB prepare failed (found): ' . $conn->error);
        redirect_with_error('found.php', 'Database error.');
    }
    $status_val = 'Unclaimed';
    $stmt->bind_param('ssssss', $title, $description, $date_found, $location_found, $photo_path, $status_val);
}

if (!$stmt->execute()) {
    $msg = 'DB execute failed: ' . $stmt->error;
    error_log($msg);
    $stmt->close();
    if ($acceptsJson) send_json(['success' => false, 'message' => 'Failed to save report.'], 500);
    redirect_with_error('found.php', 'Failed to save report.');
}
$inserted_id = $stmt->insert_id;
$stmt->close();

try {
    $date_from = date('Y-m-d', strtotime($date_found . ' -7 days'));
    $date_to   = date('Y-m-d', strtotime($date_found . ' +7 days'));

    $like_title = '%' . $title . '%';
    $like_desc  = '%' . $description . '%';
    $like_loc   = '%' . $location_found . '%';

    $sql = "SELECT id, title, description, date_lost, location_lost, photo, reporter_username FROM lost_items
            WHERE (title LIKE ? OR description LIKE ?)
              AND location_lost LIKE ?
              AND date_lost BETWEEN ? AND ?
            LIMIT 50";
    $ms = $conn->prepare($sql);
    if ($ms) {
        $ms->bind_param('sssss', $like_title, $like_desc, $like_loc, $date_from, $date_to);
        $ms->execute();
        $res = $ms->get_result();
        $matches = [];
        while ($r = $res->fetch_assoc()) $matches[] = $r;
        $ms->close();

        if (!empty($matches)) {
            $count = count($matches);
            $first = $matches[0];
            $admin_msg = "Found {$count} possible match(es) for the found item \"{$title}\". Example: \"{$first['title']}\" lost at {$first['location_lost']} on {$first['date_lost']}.";

            $n = $conn->prepare("INSERT INTO notifications (recipient_type, recipient_id, type, message, related_type, related_id) VALUES ('admin', NULL, 'match_found', ?, 'found_item', ?)");
            if ($n) {
                $n->bind_param('si', $admin_msg, $inserted_id);
                $n->execute();
                $n->close();
            } else {
                error_log('Failed to prepare admin notification insert: ' . $conn->error);
            }

            if ($reporter_username !== null) {
                $user_msg = "We found {$count} possible match(es) for your reported found item \"{$title}\". Admin staff will review them.";
                $n2 = $conn->prepare("INSERT INTO notifications (recipient_type, recipient_id, type, message, related_type, related_id) VALUES ('user', ?, 'match_found', ?, 'found_item', ?)");
                if ($n2) {
                    $n2->bind_param('isi', $reporter_username, $user_msg, $inserted_id);
                    $n2->execute();
                    $n2->close();
                }
            }

            foreach ($matches as $m) {
                if (!empty($m['reporter_username'])) {
                    $lost_reporter_msg = "Your lost item \"{$m['title']}\" may match a newly reported found item \"{$title}\". Admin staff will review it.";
                    $n3 = $conn->prepare("INSERT INTO notifications (recipient_type, recipient_id, type, message, related_type, related_id) VALUES ('user', ?, 'match_found', ?, 'lost_item', ?)");
                    if ($n3) {
                        $rid = (int)$m['reporter_username'];
                        $n3->bind_param('isi', $rid, $lost_reporter_msg, $m['id']);
                        $n3->execute();
                        $n3->close();
                    }
                }
            }
        }
    } else {
        error_log('Match query prepare failed: ' . $conn->error);
    }
} catch (Throwable $ex) {
    error_log('Error during match detection (save_found.php): ' . $ex->getMessage());
}

if ($acceptsJson) {
    send_json(['success' => true, 'message' => 'Report saved.', 'redirect' => 'found_list.php']);
} else {
    $_SESSION['success'] = 'Found item reported successfully.';
    header('Location: found_list.php');
}
exit;
?>