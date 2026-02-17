
<?php
require_once("../database/db_connect.php");
session_start();
require('sidebar.php');

// Only admins allowed
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header("Location: login.php");
    exit();
}

// Helper function for escaping output
function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

$field_errors = ['title' => '', 'description' => ''];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validate
    if ($title === '') {
        $field_errors['title'] = "Title is required.";
    }
    if ($description === '') {
        $field_errors['description'] = "Description is required.";
    }

    if ($field_errors['title'] || $field_errors['description']) {
        // We want individual field errors, not a top error summary
    } else {
        $stmt = $conn->prepare("INSERT INTO why_aone_hub (why_title, why_description) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param("ss", $title, $description);
            if ($stmt->execute() && $stmt->affected_rows === 1) {
                header("Location: services.php?why_aone_hub_created=1");
                exit();
            } else {
                $errors[] = "Failed to create entry. Please try again.";
            }
            $stmt->close();
        } else {
            $errors[] = "Database error. Please try again.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Why Aone Hub Reason</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="js/jquery-4.0.0.min.js"></script>
    <style>
    body {margin:0; background: #f4f6fb; overflow-x:hidden;}
    .dashboard-main {padding: 40px;}
    .internal-header {margin: 0 0 18px 0; color: #322053;}
    .coupon-form-wrap {
        max-width: 500px;
        margin: 40px auto;
        background: #fff; 
        border-radius: 12px;
        box-shadow: 0 1px 10px rgba(44,62,80,0.10);
        padding: 30px 32px 22px 32px;
    }
    .form-group {margin-bottom: 22px;}
    .form-group label {display:block; font-weight:600; margin-bottom:5px; color:#594285;}
    .form-group input[type=text],
    .form-group input[type=number],
    .form-group input[type=date],
    .form-group input[type=datetime-local],
    .form-group select,
    textarea {
        width:100%; border-radius:6px; border:1px solid #ddd; padding: 8px 10px; font-size:15px;
        box-sizing: border-box; background: #fafbff;
    }
    .form-group input[type=text]:focus,
    .form-group input[type=number]:focus,
    .form-group input[type=date]:focus,
    .form-group input[type=datetime-local]:focus,
    .form-group select:focus,
    textarea:focus {
        outline: none;
        border-color: #7090f5;
        background: #fff;
    }
    .form-actions {
        margin-top: 24px;
        display: flex;
        gap: 18px;
        justify-content: flex-end;
    }
    .btn {
        background: linear-gradient(90deg, #2d397a, #594285 90%);
        color: #fff;
        padding: 8px 24px;
        border: none;
        border-radius: 7px; font-size: 15px; font-weight: 700;
        cursor: pointer;
        transition: background .16s;
    }
    .btn:hover {background: linear-gradient(90deg, #594285, #2d397a 100%);}
    .error-msg {background: #fde8e4; color: #a5092c; border-radius: 4px; padding:9px 14px;margin-bottom:14px;}
    .success-msg {background: #dbfadd;color: #18793a; border-radius: 4px; padding:9px 14px;margin-bottom:14px;}
    .form-error {
        color: #a5092c;
        font-size: 14px;
        padding: 6px 0 0 2px;
        margin: 0;
        min-height: 0;
        display: block;
        height: auto;
    }
    .form-error:empty {
        padding: 0;
        min-height: 0;
        height: 0;
        margin: 0;
    }
    textarea { resize: vertical; }
    </style>
</head>
<body>
<div class="dashboard-main">
    <h2 class="internal-header">Create Why Aone Hub Reason</h2>
    <div class="coupon-form-wrap">
        <?php if (!empty($errors)): ?>
            <div class="error-msg">
                <?= implode('<br>', $errors) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-msg">
                Entry created successfully.<br>
                <a href="services.php" style="color:#225085;text-decoration:underline;">Back to list</a>
            </div>
        <?php else: ?>
        <form method="post" id="whyAoneHubForm" autocomplete="off" novalidate>
            <div class="form-group">
                <label for="title">Title <span style="color:red">*</span></label>
                <input type="text" name="title" id="title" maxlength="128" value="<?= esc($_POST['title'] ?? '') ?>" autocomplete="off" />
                <span class="form-error" id="err_title"><?php if (!empty($field_errors['title'])) echo $field_errors['title']; ?></span>
            </div>
            <div class="form-group">
                <label for="description">Description <span style="color:red">*</span></label>
                <textarea name="description" id="description" rows="3" maxlength="500"><?= esc($_POST['description'] ?? '') ?></textarea>
                <span class="form-error" id="err_description"><?php if (!empty($field_errors['description'])) echo $field_errors['description']; ?></span>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Create</button>
                <a href="services.php" class="btn" style="background:#eee;color:#322053;">Cancel</a>
            </div>
        </form>
        <script>
        $(function() {
            // Validate on change and show error under each field individually
            function validateTitle(showMsg) {
                var val = $('#title').val().trim();
                if(val === "") {
                    if (showMsg) $('#err_title').text("Title is required.");
                    return false;
                } else {
                    if (showMsg) $('#err_title').text("");
                    return true;
                }
            }
            function validateDescription(showMsg) {
                var val = $('#description').val().trim();
                if(val === "") {
                    if (showMsg) $('#err_description').text("Description is required.");
                    return false;
                } else {
                    if (showMsg) $('#err_description').text("");
                    return true;
                }
            }

            $('#title').on('change keyup blur', function() {
                validateTitle(true);
            });
            $('#description').on('change keyup blur', function() {
                validateDescription(true);
            });

            $('#whyAoneHubForm').on('submit', function(e) {
                var valid = true;
                if(!validateTitle(true)) valid = false;
                if(!validateDescription(true)) valid = false;
                if (!valid) {
                    e.preventDefault();
                }
            });
        });
        </script>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
