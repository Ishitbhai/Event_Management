<?php
require_once "../database/db_connect.php";
session_start();
require('sidebar.php');

// Initialize field and error variables
$service_title = '';
$service_description = '';
$service_image = '';
$errors = [];
$success = '';

$field_errors = [
    'service_title' => [],
    'service_description' => [],
    'service_image' => [],
    'general' => []
];

function validate_service_form(&$field_errors, &$service_title, &$service_description, &$service_image, &$img_arr) {
    // Title
    if ($service_title === '') {
        $field_errors['service_title'][] = "Service title is required.";
    } elseif (mb_strlen($service_title) < 2) {
        $field_errors['service_title'][] = "Service title must be at least 2 characters.";
    }
    // Description
    if ($service_description === '') {
        $field_errors['service_description'][] = "Service description is required.";
    } elseif (mb_strlen($service_description) < 8) {
        $field_errors['service_description'][] = "Service description must be at least 8 characters.";
    }
    // Image now required AND must be jpg/jpeg/png only
    if (!$img_arr || $img_arr['error'] === UPLOAD_ERR_NO_FILE) {
        $field_errors['service_image'][] = "Service image is required.";
    } else {
        $allowed_exts = ['jpg','jpeg','png'];
        $ext = strtolower(pathinfo($img_arr['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts)) {
            $field_errors['service_image'][] = "Image must be a JPG, JPEG, or PNG file.";
        }
        if ($img_arr['size'] > 2 * 1024 * 1024) {
            $field_errors['service_image'][] = "Image must be less than 2MB.";
        }
        if ($img_arr['error'] !== UPLOAD_ERR_OK) {
            $field_errors['service_image'][] = "Image upload failed. (" . $img_arr['error'] . ")";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_title = trim($_POST['service_title'] ?? '');
    $service_description = trim($_POST['service_description'] ?? '');
    $image_uploaded = false;
    $img = $_FILES['service_image'] ?? null;

    // Validate all fields
    validate_service_form($field_errors, $service_title, $service_description, $service_image, $img);

    // If image passed validation, try to move (server-side)
    if (
        empty($field_errors['service_image']) &&
        $img &&
        $img['error'] !== UPLOAD_ERR_NO_FILE &&
        $img['error'] === UPLOAD_ERR_OK
    ) {
        $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
        $nameRand = bin2hex(random_bytes(8));
        $img_name = "service_" . date("Ymd_His") . "_$nameRand.$ext";
        $img_target = "../images/" . $img_name;
        if (!is_dir(dirname($img_target))) {
            mkdir(dirname($img_target), 0777, true);
        }
        if (move_uploaded_file($img['tmp_name'], $img_target)) {
            // Only store the image filename in the database (no path)
            $service_image = $img_name;
            $image_uploaded = true;
        } else {
            $field_errors['service_image'][] = "Failed to save uploaded image.";
        }
    }

    // Collect all errors for general handling (not used for inline fields)
    foreach ($field_errors as $arr) {
        foreach ($arr as $err) {
            $errors[] = $err;
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO services (service_title, service_description, service_image) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $service_title, $service_description, $service_image);
            if ($stmt->execute()) {
                $success = "Service created successfully!";
                $service_title = $service_description = $service_image = '';
            } else {
                $field_errors['general'][] = "Database error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $field_errors['general'][] = "Database connection error.";
        }
    } else {
        // Remove uploaded image if DB insert failed
        if ($image_uploaded && file_exists("../images/$service_image")) {
            unlink("../images/$service_image");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Service</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="css/events.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/service_create.css">

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function setError(field, msgArr) {
            const errEl = document.getElementById(field + '_err');
            if (errEl) {
                errEl.innerHTML = (msgArr && msgArr.length > 0)
                    ? msgArr.map(e=>escapeHTML(e)).join('<br>')
                    : '';
            }
        }
        function escapeHTML(str) {
            return str.replace(/[&<>'"]/g, function(tag) {
                const charsToReplace = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#39;',
                    '"': '&quot;'
                };
                return charsToReplace[tag] || tag;
            });
        }
        function validateTitle() {
            const v = document.getElementById('service_title').value.trim();
            let errs = [];
            if (v.length === 0) errs.push('Service title is required.');
            else if (v.length < 2) errs.push('Service title must be at least 2 characters.');
            setError('service_title', errs);
            return errs.length === 0;
        }
        function validateDesc() {
            const v = document.getElementById('service_description').value.trim();
            let errs = [];
            if (v.length === 0) errs.push('Service description is required.');
            else if (v.length < 8) errs.push('Service description must be at least 8 characters.');
            setError('service_description', errs);
            return errs.length === 0;
        }
        function validateImage(showPreview = false) {
            const input = document.getElementById('service_image');
            let errs = [];
            let allowedExts = ['jpg','jpeg','png'];
            if (input.files.length) {
                const file = input.files[0];
                const ext = file.name.split('.').pop().toLowerCase();
                if (!allowedExts.includes(ext)) {
                    errs.push('Image must be a JPG, JPEG, or PNG file.');
                }
                if (file.size > 2 * 1024 * 1024) {
                    errs.push('Image must be less than 2MB.');
                }
            } else {
                errs.push('Service image is required.');
            }
            setError('service_image', errs);

            // Preview logic
            const previewEl = document.getElementById('service_image_preview');
            if (showPreview) {
                if (input.files && input.files[0] && errs.length === 0) {
                    const url = URL.createObjectURL(input.files[0]);
                    if (previewEl) {
                        previewEl.src = url;
                        previewEl.style.display = 'block';
                    }
                } else if (previewEl) {
                    previewEl.src = "#";
                    previewEl.style.display = 'none';
                }
            }
            return errs.length === 0;
        }

        // On change
        document.getElementById('service_title').addEventListener('input', validateTitle);
        document.getElementById('service_description').addEventListener('input', validateDesc);
        document.getElementById('service_image').addEventListener('change', function(e){
            validateImage(true);
        });

        // On submit
        document.querySelector('form').addEventListener('submit', function(e){
            let valid = validateTitle();
            valid = validateDesc() && valid;
            valid = validateImage(true) && valid;
            if (!valid) {
                e.preventDefault();
            }
        });
    });
    </script>
</head>
<body>
    <div class="form-container">
        <h2>Create Service</h2>
        <?php if ($success): ?>
            <div class="success-message"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($field_errors['general'])): ?>
            <div class="error-message-inline-general">
                <?php foreach ($field_errors['general'] as $e) {
                    echo htmlspecialchars($e) . "<br>";
                } ?>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" autocomplete="off" novalidate>
            <div class="form-field">
                <label for="service_title">Service Title<span style="color:#b70c26">*</span></label>
                <input type="text" id="service_title" name="service_title" maxlength="100"
                       value="<?= htmlspecialchars($service_title) ?>">
                <div class="error-message-inline-field" id="service_title_err">
                    <?php if (!empty($field_errors['service_title'])):
                        foreach ($field_errors['service_title'] as $e) {
                            echo htmlspecialchars($e) . "<br>";
                        }
                    endif; ?>
                </div>
            </div>
            <div class="form-field">
                <label for="service_description">Service Description<span style="color:#b70c26">*</span></label>
                <textarea id="service_description" name="service_description" maxlength="500"><?= htmlspecialchars($service_description) ?></textarea>
                <div class="error-message-inline-field" id="service_description_err">
                    <?php if (!empty($field_errors['service_description'])):
                        foreach ($field_errors['service_description'] as $e) {
                            echo htmlspecialchars($e) . "<br>";
                        }
                    endif; ?>
                </div>
            </div>
            <div class="form-field">
                <label for="service_image">Service Image<span style="color:#b70c26">*</span> <span style="font-weight:400; font-size:13px;color:#B0B1BE;">(JPG, JPEG or PNG, max 2MB)</span></label>
                <input type="file" id="service_image" name="service_image" accept=".jpg,.jpeg,.png">
                <?php if ($service_image): ?>
                    <img class="preview-img" id="service_image_preview" src="<?= htmlspecialchars('../images/' . $service_image) ?>" alt="Service image">
                <?php else: ?>
                    <img class="preview-img" id="service_image_preview" style="display:none;" src="#" alt="Service image">
                <?php endif; ?>
                <div class="error-message-inline-field" id="service_image_err">
                    <?php if (!empty($field_errors['service_image'])):
                        foreach ($field_errors['service_image'] as $e) {
                            echo htmlspecialchars($e) . "<br>";
                        }
                    endif; ?>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit">Create Service</button>
                <a href="services.php" style="padding:9px 18px; background:#ebebef;color:#473b6f;text-decoration:none;border-radius:7px;font-weight:600;">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
