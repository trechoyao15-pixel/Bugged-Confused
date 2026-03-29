<?php
session_start();
require_once __DIR__ . '/db.php';

function is_valid_return_to($r) {
    if (empty($r) || !is_string($r)) return false;
    if (strpos($r, '/') !== 0) return false;
    if (strpos($r, '//') === 0) return false;
    $parts = parse_url($r);
    if ($parts === false) return false;
    if (!empty($parts['scheme']) || !empty($parts['host'])) return false;
    return true;
}

$incoming_return_to = trim((string)($_GET['return_to'] ?? ''));
$incoming_focus = trim((string)($_GET['focus'] ?? ''));

$login_error = "";
$register_error = "";
$success_message = "";

if (!empty($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["login"])) {

    $username = trim($_POST['login_username'] ?? "");
    $password = trim($_POST['login_password'] ?? "");
    $is_admin = !empty($_POST['admin_login']);

    $post_return_to = trim((string)($_POST['return_to'] ?? ''));
    $post_focus = trim((string)($_POST['focus'] ?? ''));

    if ($username === "" || $password === "") {
        $login_error = "Please fill out all fields.";
    } else {
        if ($is_admin) {
            $stmt = $conn->prepare("SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
        } else {
            $stmt = $conn->prepare("SELECT id, username, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->bind_param("ss", $username, $username);
        }

        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            if (password_verify($password, $row["password_hash"])) {
                if ($is_admin) {
                    session_regenerate_id(true);
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $row['username'];
                    $_SESSION['admin_id'] = (int)$row['id'];
                    header("Location: dashboard.php");
                    exit;
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$row["id"];
                    $_SESSION['username'] = $row["username"];
                    $target = '';
                    $focus = '';
                    if (!empty($post_return_to)) {
                        $target = $post_return_to;
                        $focus = $post_focus;
                    } elseif (!empty($incoming_return_to)) {
                        $target = $incoming_return_to;
                        $focus = $incoming_focus;
                    }

                    if (is_valid_return_to($target)) {
                        $loc = $target;
                        if ($focus !== '') $loc .= '#' . rawurlencode($focus);
                        header("Location: {$loc}");
                        exit;
                    }

                    header("Location: index.php");
                    exit;
                }
            } else {
                $login_error = "Invalid username or password.";
            }
        } else {
            $login_error = "Invalid username or password.";
        }
        $stmt->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["register"])) {

    $username = trim($_POST['reg_username'] ?? "");
    $email = trim($_POST['reg_email'] ?? "");
    $password = trim($_POST['reg_password'] ?? "");

    $post_return_to = trim((string)($_POST['return_to'] ?? ''));
    $post_focus = trim((string)($_POST['focus'] ?? ''));

    if ($username === "" || $email === "" || $password === "") {
        $register_error = "Please fill out all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $check = $stmt->get_result();

        if ($check && $check->num_rows > 0) {
            $register_error = "Username or email already taken.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $hash);
            if ($stmt->execute()) {
                $ret_q = '';
                $rt = '';
                $f = '';
                if (!empty($post_return_to)) $rt = $post_return_to;
                elseif (!empty($incoming_return_to)) $rt = $incoming_return_to;
                if (!empty($post_focus)) $f = $post_focus;
                elseif (!empty($incoming_focus)) $f = $incoming_focus;

                $loc = 'signup_login_form.php';
                $params = [];
                if ($rt !== '') $params['return_to'] = $rt;
                if ($f !== '') $params['focus'] = $f;
                if (!empty($params)) $loc .= '?' . http_build_query($params);

                $_SESSION['success'] = "Account created. Please log in.";
                header("Location: {$loc}");
                exit;
            } else {
                $register_error = "Failed to create account. Try again.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Login/Signup Form — LTMS</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<link rel="stylesheet" href="signup_login_form.css">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>


<div class="container">

    <div class="form-box login">
        <form id="loginForm" action="signup_login_form.php" method="post">
            <h1>Login</h1>

            <?php if (!empty($success_message)): ?>
                <p class="form-inline-message success"><?= htmlspecialchars($success_message) ?></p>
            <?php endif; ?>

            <?php if ($login_error): ?>
                <p class="form-inline-message error"><?= htmlspecialchars($login_error) ?></p>
            <?php endif; ?>

            <div class="input-box">
                <input name="login_username" type="text" placeholder="Username or Email" required>
                <i class='bx bxs-user'></i>
            </div>

            <div class="input-box">
                <input name="login_password" type="password" placeholder="Password" required>
                <i class='bx bxs-lock-alt'></i>
            </div>

            <div class="input-box">
                
                <a href="admin_login.php">Login as Admin</a>
            </div>

            <input type="hidden" name="return_to" value="<?= htmlspecialchars($incoming_return_to) ?>">
            <input type="hidden" name="focus" value="<?= htmlspecialchars($incoming_focus) ?>">

            <div class="forgot-link">
                <a href="#">Forgot Password?</a>
            </div>

            <button type="submit" name="login" class="btn">Login</button>
        </form>
    </div>

    <div class="form-box register">
        <form id="registerForm" action="signup_login_form.php" method="post">
            <h1>Registration</h1>

            <?php if ($register_error): ?>
                <p class="form-inline-message error"><?= htmlspecialchars($register_error) ?></p>
            <?php endif; ?>

            <div class="input-box">
                <input name="reg_username" type="text" placeholder="Username" required>
                <i class='bx bxs-user'></i>
            </div>

            <div class="input-box">
                <input name="reg_email" type="email" placeholder="Email" required>
                <i class='bx bxs-envelope'></i>
            </div>

            <div class="input-box">
                <input name="reg_password" type="password" placeholder="Password" required>
                <i class='bx bxs-lock-alt'></i>
            </div>

            <input type="hidden" name="return_to" value="<?= htmlspecialchars($incoming_return_to) ?>">
            <input type="hidden" name="focus" value="<?= htmlspecialchars($incoming_focus) ?>">

            <button type="submit" name="register" class="btn">Register</button>
        </form>
    </div>

    <div class="toggle-box">
        <div class="toggle-panel toggle-left">
            <h1>Hello, Welcome!</h1>
            <p>Don't have an account?</p>
            <button class="btn register-btn" type="button">Register</button>
        </div>

        <div class="toggle-panel toggle-right">
            <h1>Welcome Back!</h1>
            <p>Already have an account?</p>
            <button class="btn login-btn" type="button">Login</button>
        </div>
    </div>

</div>

<script src="signup_login_form.js"></script>
</body>
</html>