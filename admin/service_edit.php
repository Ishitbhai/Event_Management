<?php
require_once("../database/db_connect.php");
session_start();
require('sidebar.php');

// Only admins allowed
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header("Location: login.php");
    exit();
}

// Check if service_id is set
if (!isset($_GET['id'])) {
    echo "Service ID not specified.";
    exit();
}

$service_id = intval($_GET['id']);

// Fetch the existing service data
$sql = "SELECT * FROM services WHERE service_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $service_id);
$stmt->execute();
$result = $stmt->get_result();
$service = $result->fetch_assoc();

if (!$service) {
    echo "Service not found.";
    exit();
}

// Form fields initialization
$service_title = isset($_POST['service_title']) ? trim($_POST['service_title']) : $service['service_title'];
$service_description = isset($_POST['service_description']) ? trim($_POST['service_description']) : $service['service_description'];
$service_image = $service['service_image'];
$field_errors = [
    'service_title' => [],
    'service_description' => [],
    'service_image' => [],
    'general' => []
];
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate service title
    if (empty($service_title)) {
        $field_errors['service_title'][] = "Service title is required.";
    } elseif (strlen($service_title) > 100) {
        $field_errors['service_title'][] = "Service title must be 100 characters or less.";
    }

    // Validate description
    if (empty($service_description)) {
        $field_errors['service_description'][] = "Service description is required.";
    } elseif (strlen($service_description) > 500) {
        $field_errors['service_description'][] = "Service description must be 500 characters or less.";
    }

    $img_uploaded = false;
    // Handle image upload if a new file was submitted
    if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $img = $_FILES['service_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $allowed_exts = ['jpg','jpeg','png'];
        $file_type = $img['type'];
        $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));

        if (!in_array($file_type, $allowed_types) || !in_array($ext, $allowed_exts)) {
            $field_errors['service_image'][] = "Invalid image type. Only JPG, JPEG, PNG are allowed.";
        }
        // No max size validation - 2MB check removed
        if (empty($field_errors['service_image'])) {
            $nameRand = bin2hex(random_bytes(8));
            $img_name = "service_" . date("Ymd_His") . "_$nameRand.$ext";
            $img_target = "../images/" . $img_name;
            if (!is_dir(dirname($img_target))) {
                mkdir(dirname($img_target), 0777, true);
            }
            if (move_uploaded_file($img['tmp_name'], $img_target)) {
                // Only store the image filename in the database (no path)
                $service_image = $img_name;
                $img_uploaded = true;
            } else {
                $field_errors['service_image'][] = "Failed to save uploaded image.";
            }
        }
    }

    // Collect all errors for display
    foreach ($field_errors as $arr) {
        foreach ($arr as $err) {
            $errors[] = $err;
        }
    }

    if (empty($errors)) {
        $update_sql = "UPDATE services SET service_title = ?, service_description = ?, service_image = ? WHERE service_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssi", $service_title, $service_description, $service_image, $service_id);

        if ($update_stmt->execute()) {
            $success = "Service updated successfully!";
            // Reload current
            $sql = "SELECT * FROM services WHERE service_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $service_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $service = $result->fetch_assoc();
            $service_title = $service['service_title'];
            $service_description = $service['service_description'];
            $service_image = $service['service_image'];
        } else {
            $field_errors['general'][] = "Failed to update service. " . htmlspecialchars($update_stmt->error);
        }
    } else {
        // Remove uploaded image if database failed
        if ($img_uploaded && file_exists("../images/$service_image")) {
            unlink("../images/$service_image");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Service</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="css/events.css">    
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/service_edit.css">
    <script src="js/jquery-4.0.0.min.js"></script>
    <script>
    $(function(){
        function setError(field, errs) {
            const el = $('#' + field + '_err');
            if (Array.isArray(errs)) {
                el.html(errs.filter(x=>x).map(e=>$('<div>').text(e).html()).join("<br>"));
            } else {
                el.html('');
            }
        }
        function validateTitle() {
            let val = $('#service_title').val().trim();
            let errs = [];
            if (val === '') errs.push('Service title is required.');
            else if (val.length > 100) errs.push('Service title must be 100 characters or less.');
            setError('service_title', errs);
            return errs.length === 0;
        }
        function validateDesc() {
            let val = $('#service_description').val().trim();
            let errs = [];
            if (val === '') errs.push('Service description is required.');
            else if (val.length > 500) errs.push('Service description must be 500 characters or less.');
            setError('service_description', errs);
            return errs.length === 0;
        }
        function validateImage(showPreview) {
            const input = document.getElementById("service_image");
            let errs = [];
            let allowedExts = ['jpg','jpeg','png'];
            if (input.files.length) {
                const file = input.files[0];
                const ext = file.name.split('.').pop().toLowerCase();
                if (!allowedExts.includes(ext)) {
                    errs.push('Image must be a JPG, JPEG, or PNG file.');
                }
                // Removed 2MB size check from JS
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
                    if ($(previewEl).data('default')) {
                        previewEl.src = $(previewEl).data('default');
                        previewEl.style.display = 'block';
                    } else {
                        previewEl.src = "#";
                        previewEl.style.display = 'none';
                    }
                }
            }
            return errs.length === 0;
        }

        $('#service_title').on('input', validateTitle);
        $('#service_description').on('input', validateDesc);
        $('#service_image').on('change', function(){
            validateImage(true);
        });

        $('form').on('submit', function(e){
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
        <h2>Edit Service</h2>
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
                <label for="service_image">Service Image <span style="font-weight:400; font-size:13px;color:#B0B1BE;">(JPG, JPEG or PNG, optional)</span></label>
                <?php if ($service_image): ?>
                    <img class="preview-img" id="service_image_preview"
                        src="<?= htmlspecialchars('../images/' . $service_image) ?>"
                        data-default="<?= htmlspecialchars('../images/' . $service_image) ?>"
                        alt="Service image">
                <?php else: ?>
                    <img class="preview-img" id="service_image_preview" style="display:none;" src="#" alt="Service image">
                <?php endif; ?>
                <input type="file" id="service_image" name="service_image" accept=".jpg,.jpeg,.png">
                <div class="error-message-inline-field" id="service_image_err">
                    <?php if (!empty($field_errors['service_image'])):
                        foreach ($field_errors['service_image'] as $e) {
                            echo htmlspecialchars($e) . "<br>";
                        }
                    endif; ?>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit">Update Service</button>
                <a href="services.php" style="padding:9px 18px; background:#ebebef;color:#473b6f;text-decoration:none;border-radius:7px;font-weight:600;">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
</html>
