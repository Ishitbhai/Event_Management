
<?php
session_start();
require_once('sidebar.php');
require_once('../database/db_connect.php');

// --- Initialize error messages ---
$error = '';
$success = '';
$field_errors = [
    'user_name' => '',
    'user_email' => '',
    'user_phone_number' => '',
    'user_address' => '',
    'user_password' => '',
    'confirm_user_password' => ''
];

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function is_invalid_phone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) !== 10) return true;
    if (preg_match('/^(0|1)+$/', $phone)) return true;
    if (preg_match('/(\d)\1{5,}/', $phone)) return true;
    if (preg_match('/012345|123456|234567|345678|456789/', $phone)) return true;
    return false;
}

// On submit, validate fields and retain error messages
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_name = trim($_POST['user_name'] ?? '');
    $user_email = trim($_POST['user_email'] ?? '');
    $user_phone_number = trim($_POST['user_phone_number'] ?? '');
    $user_address = trim($_POST['user_address'] ?? '');
    $user_password = $_POST['user_password'] ?? '';
    $confirm_user_password = $_POST['confirm_user_password'] ?? '';
    $user_status = $_POST['user_status'] ?? 'active';
    $user_type = $_POST['user_type'] ?? 'user';

    // Field validations
    if (empty($user_name)) {
        $field_errors['user_name'] = 'Full Name is required.';
    } elseif (mb_strlen($user_name) < 2) {
        $field_errors['user_name'] = 'Full Name must be at least 2 characters.';
    }
    if (empty($user_email)) {
        $field_errors['user_email'] = 'Email Address is required.';
    } elseif (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $field_errors['user_email'] = 'Invalid email address!';
    }
    if (empty($field_errors['user_email'])) {
        $q = $conn->prepare("SELECT user_id FROM users WHERE user_email=? LIMIT 1");
        $q->bind_param("s", $user_email);
        $q->execute();
        $q->store_result();
        if ($q->num_rows > 0) {
            $field_errors['user_email'] = 'Email already exists!';
        }
        $q->close();
    }
    if (empty($user_phone_number)) {
        $field_errors['user_phone_number'] = 'Phone number is required.';
    } elseif (!preg_match('/^\d{10}$/', preg_replace('/\D/', '', $user_phone_number))) {
        $field_errors['user_phone_number'] = 'Phone number must be exactly 10 digits.';
    } elseif (is_invalid_phone($user_phone_number)) {
        $field_errors['user_phone_number'] = 'Please enter a valid phone number.';
    }
    if (empty($user_address)) {
        $field_errors['user_address'] = 'Address is required.';
    }
    if (empty($user_password)) {
        $field_errors['user_password'] = 'Password is required.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $user_password)) {
        $field_errors['user_password'] = 'Password must be at least 8 chars, 1 uppercase, 1 lowercase, 1 digit, 1 special char.';
    }
    if (empty($confirm_user_password)) {
        $field_errors['confirm_user_password'] = 'Confirm Password is required.';
    } elseif ($user_password !== $confirm_user_password) {
        $field_errors['confirm_user_password'] = 'Passwords do not match!';
    }

    $has_errors = count(array_filter($field_errors)) > 0;

    if (!$has_errors) {
        $hashed_password = password_hash($user_password, PASSWORD_DEFAULT);
        $user_token = bin2hex(random_bytes(16));

        $stmt = $conn->prepare("INSERT INTO users (user_name, user_email, user_phone_number, user_address, user_password, user_token, user_status, user_type, registered_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssssss", $user_name, $user_email, $user_phone_number, $user_address, $hashed_password, $user_token, $user_status, $user_type);
        if ($stmt->execute()) {
            // Instead of displaying success here, redirect to users.php and store success in session
            $_SESSION['success_message'] = "User created successfully!";
            header("Location: users.php");
            exit;
        } else {
            $error = "Error: Could not create user. Please try again.";
        }
        $stmt->close();
    } else {
        $error = "Please correct the errors below.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create User</title>
    <link rel="stylesheet" href="css/events.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/users.css">
    <link rel="stylesheet" href="css/user_create.css">
    
    <script>
    // Client-side validation logic
    let formSubmitted = false;
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById('user-create-form');
        const errorMsg = {
            user_name: {
                required: "Full Name is required.",
                minlen: "Full Name must be at least 2 characters."
            },
            user_email: {
                required: "Email Address is required.",
                invalid: "Invalid email address format."
            },
            user_phone_number: {
                required: "Phone number is required.",
                format: "Phone number must be exactly 10 digits.",
                invalid: "Please enter a valid phone number."
            },
            user_address: {
                required: "Address is required."
            },
            user_password: {
                required: "Password is required.",
                invalid: "Password must be at least 8 chars, 1 uppercase, 1 lowercase, 1 digit, 1 special char."
            },
            confirm_user_password: {
                required: "Confirm Password is required.",
                mismatch: "Passwords do not match!"
            }
        };

        function phoneCustomValidation(val) {
            let digits = val.replace(/\D/g, "");
            if (digits.length !== 10) return errorMsg.user_phone_number.format;
            if (/^(0|1)+$/.test(digits)) return errorMsg.user_phone_number.invalid;
            if (/(\d)\1{5,}/.test(digits)) return errorMsg.user_phone_number.invalid;
            if (/012345|123456|234567|345678|456789/.test(digits)) return errorMsg.user_phone_number.invalid;
            return "";
        }

        function validateField(field) {
            let val = field.value.trim();
            let name = field.name;
            let errDiv = document.getElementById(name + "_error");
            let err = "";

            if (name === "user_name") {
                if (!val) err = errorMsg.user_name.required;
                else if (val.length < 2) err = errorMsg.user_name.minlen;
            } else if (name === "user_email") {
                if (!val) err = errorMsg.user_email.required;
                else {
                    let valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
                    if (!valid) err = errorMsg.user_email.invalid;
                }
            } else if (name === "user_phone_number") {
                if (!val) err = errorMsg.user_phone_number.required;
                else {
                    let customErr = phoneCustomValidation(val);
                    if (customErr) err = customErr;
                }
            } else if (name === "user_address") {
                if (!val) err = errorMsg.user_address.required;
            } else if (name === "user_password") {
                if (!val) err = errorMsg.user_password.required;
                else if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(val)) err = errorMsg.user_password.invalid;
            } else if (name === "confirm_user_password") {
                let pwd = document.getElementById('user_password').value;
                if (!val) err = errorMsg.confirm_user_password.required;
                else if (val !== pwd) err = errorMsg.confirm_user_password.mismatch;
            }
            if (errDiv) {
                errDiv.textContent = err;
                errDiv.style.display = err ? 'block' : 'none';
            }
            if (err) field.classList.add('is-invalid');
            else field.classList.remove('is-invalid');
            return err;
        }

        // Attach change listeners for validation (edit to always validate on change)
        Array.from(form.elements).forEach(function(elem) {
            if (["user_name", "user_email", "user_phone_number", "user_address", "user_password", "confirm_user_password"].includes(elem.name)) {
                elem.addEventListener('change', function(e) {
                    validateField(this, e); // added e=validation on change
                });
                elem.addEventListener('input', function(e) {
                    validateField(this, e); // instant feedback as well
                });
            }
        });

        // On change: after one submit, continual validate
        form.addEventListener('submit', function(e) {
            let hasError = false;
            formSubmitted = true;
            Array.from(form.elements).forEach(function(elem) {
                if (["user_name", "user_email", "user_phone_number", "user_address", "user_password", "confirm_user_password"].includes(elem.name)) {
                    let err = validateField(elem, e);
                    if (err) hasError = true;
                }
            });
            if (hasError) {
                e.preventDefault();
            }
        });
    });
    </script>
</head>
<body>
<div class="dashboard-main">
    <div class="event-create-main-box">
        <h2 style="margin-bottom: 18px;">Create User</h2>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2" style="margin-bottom: 17px;"><?= esc($error) ?></div>
        <?php endif; ?>

        <form method="post" id="user-create-form" autocomplete="off" novalidate>
            <div class="form-section">
                <label class="form-label" for="user_name">Full Name</label>
                <input type="text" class="form-control<?= $field_errors['user_name'] ? ' is-invalid' : '' ?>" id="user_name" name="user_name" required value="<?= esc($_POST['user_name'] ?? '') ?>">
                <div id="user_name_error" class="form-error" style="<?= $field_errors['user_name'] ? '' : 'display:none;' ?>"><?= esc($field_errors['user_name']) ?></div>
            </div>
            <div class="form-section">
                <label class="form-label" for="user_email">Email Address</label>
                <input type="email" class="form-control<?= $field_errors['user_email'] ? ' is-invalid' : '' ?>" id="user_email" name="user_email" required value="<?= esc($_POST['user_email'] ?? '') ?>">
                <div id="user_email_error" class="form-error" style="<?= $field_errors['user_email'] ? '' : 'display:none;' ?>"><?= esc($field_errors['user_email']) ?></div>
            </div>
            <div class="form-section">
                <label class="form-label" for="user_phone_number">Phone Number</label>
                <input type="tel" class="form-control<?= $field_errors['user_phone_number'] ? ' is-invalid' : '' ?>" id="user_phone_number" name="user_phone_number" required pattern="\d{10}" value="<?= esc($_POST['user_phone_number'] ?? '') ?>">
                <div id="user_phone_number_error" class="form-error" style="<?= $field_errors['user_phone_number'] ? '' : 'display:none;' ?>"><?= esc($field_errors['user_phone_number']) ?></div>
            </div>
            <div class="form-section">
                <label class="form-label" for="user_address">Address</label>
                <input type="text" class="form-control<?= $field_errors['user_address'] ? ' is-invalid' : '' ?>" id="user_address" name="user_address" required value="<?= esc($_POST['user_address'] ?? '') ?>">
                <div id="user_address_error" class="form-error" style="<?= $field_errors['user_address'] ? '' : 'display:none;' ?>"><?= esc($field_errors['user_address']) ?></div>
            </div>
            <div class="form-section">
                <label class="form-label" for="user_password">Password</label>
                <input type="password" class="form-control<?= $field_errors['user_password'] ? ' is-invalid' : '' ?>" id="user_password" name="user_password" required>
                <div id="user_password_error" class="form-error" style="<?= $field_errors['user_password'] ? '' : 'display:none;' ?>"><?= esc($field_errors['user_password']) ?></div>
            </div>
            <div class="form-section">
                <label class="form-label" for="confirm_user_password">Confirm Password</label>
                <input type="password" class="form-control<?= $field_errors['confirm_user_password'] ? ' is-invalid' : '' ?>" id="confirm_user_password" name="confirm_user_password" required>
                <div id="confirm_user_password_error" class="form-error" style="<?= $field_errors['confirm_user_password'] ? '' : 'display:none;' ?>"><?= esc($field_errors['confirm_user_password']) ?></div>
            </div>
            <div class="form-section">
                <label class="form-label" for="user_status">Status</label>
                <select class="form-control" id="user_status" name="user_status">
                    <option value="active" <?= (($_POST['user_status'] ?? '') === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= (($_POST['user_status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="form-section">
                <label class="form-label" for="user_type">User Type</label>
                <select class="form-control" id="user_type" name="user_type">
                    <option value="user"   <?= (($_POST['user_type'] ?? '') === 'user') ? 'selected' : '' ?>>User</option>
                    <option value="admin"  <?= (($_POST['user_type'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <button type="submit" class="btn-save">Create User</button>
            <a href="users.php" class="btn btn-secondary" style="margin-left:10px;vertical-align:middle;">Return to users</a>
        </form>
    </div>
</div>
</body>
</html>
