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

// Helper to split images
function get_images_array($str) {
    if (!$str) return [];
    $arr = array_map('trim', explode(',', $str));
    return array_filter($arr, function($val) { return $val !== ""; });
}

$field_errors = [
    'about_us_title' => '',
    'about_us_title_text' => '',
    'about_us_images' => '',
    'about_us_new_image' => '',
    'about_us_who_we_are' => '',
    'about_us_experience' => '',
    'about_us_team_members' => '',
    'about_us_team_member_1' => '',
    'about_us_team_member_1_role' => '',
    'about_us_team_member_2' => '',
    'about_us_team_member_2_role' => '',
    'about_us_team_member_3' => '',
    'about_us_team_member_3_role' => '',
    'about_us_team_member_4' => '',
    'about_us_team_member_4_role' => '',
];
$errors = [];
$success = false;

// Load data for id=1
$stmt = $conn->prepare("SELECT about_us_title, about_us_title_text, about_us_images, about_us_who_we_are, about_us_experience, about_us_team_members,
    about_us_team_member_1, about_us_team_member_1_role,
    about_us_team_member_2, about_us_team_member_2_role,
    about_us_team_member_3, about_us_team_member_3_role,
    about_us_team_member_4, about_us_team_member_4_role
    FROM about_us WHERE about_us_id = 1 LIMIT 1");
$stmt->execute();
$stmt->bind_result($about_us_title, $about_us_title_text, $about_us_images, $about_us_who_we_are, $about_us_experience, $about_us_team_members,
    $about_us_team_member_1, $about_us_team_member_1_role, $about_us_team_member_2, $about_us_team_member_2_role, $about_us_team_member_3, $about_us_team_member_3_role,
    $about_us_team_member_4, $about_us_team_member_4_role );
if (!$stmt->fetch()) {
    $stmt->close();
    $errors[] = "About Us data not found (id=1).";
} 
$stmt->close();

// Populate current images
$images_arr = get_images_array($about_us_images);

// Handle AJAX delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_image'])) {
    $delete_image = trim($_POST['ajax_delete_image']);
    $output = ['success' => false, 'msg' => 'Image not found'];

    $stmt = $conn->prepare("SELECT about_us_images FROM about_us WHERE about_us_id = 1 LIMIT 1");
    $stmt->execute();
    $stmt->bind_result($about_us_images);
    $stmt->fetch();
    $stmt->close();

    $images_arr = get_images_array($about_us_images);
    $images_arr = array_values(array_filter($images_arr, function($img) use ($delete_image) {
        return $img !== $delete_image;
    }));
    $about_us_images = implode(',', $images_arr);

    $stmt = $conn->prepare("UPDATE about_us SET about_us_images = ? WHERE about_us_id = 1");
    $stmt->bind_param("s", $about_us_images);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
    exit;
}

// --- Save on full form submit only, including add image ---
// Add images on Save (not on "Add Image" click, which is now removed)

// Now handle the FULL FORM submit (i.e. Save button)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['ajax_delete_image'])) {
    // Start from DB state for images
    $stmt = $conn->prepare("SELECT about_us_images FROM about_us WHERE about_us_id = 1 LIMIT 1");
    $stmt->execute();
    $stmt->bind_result($about_us_images);
    $stmt->fetch();
    $stmt->close();
    $images_arr = get_images_array($about_us_images);

    // Handle new upload
    if (isset($_FILES['about_us_new_image']) && $_FILES['about_us_new_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['about_us_new_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['about_us_new_image'];
            $allowed_types = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp'
            ];
            $detected_type = mime_content_type($file['tmp_name']);

            if (isset($allowed_types[$detected_type])) {
                $ext = $allowed_types[$detected_type];
                $base = preg_replace('/[^a-zA-Z0-9_\-]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
                $fname = $base . "-" . time() . "." . $ext;

                $target = __DIR__ . "/../images/" . $fname;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $images_arr[] = $fname;
                } else {
                    $field_errors['about_us_new_image'] = "Failed to upload image. Try again.";
                }
            } else {
                $field_errors['about_us_new_image'] = "Invalid image type.";
            }
        } else {
            $field_errors['about_us_new_image'] = "Failed to upload image.";
        }
    }

    $about_us_title = trim($_POST['about_us_title'] ?? '');
    $about_us_title_text = trim($_POST['about_us_title_text'] ?? '');
    $about_us_who_we_are = trim($_POST['about_us_who_we_are'] ?? '');
    $about_us_experience = trim($_POST['about_us_experience'] ?? '');
    $about_us_team_members = trim($_POST['about_us_team_members'] ?? '');
    $about_us_team_member_1 = trim($_POST['about_us_team_member_1'] ?? '');
    $about_us_team_member_1_role = trim($_POST['about_us_team_member_1_role'] ?? '');
    $about_us_team_member_2 = trim($_POST['about_us_team_member_2'] ?? '');
    $about_us_team_member_2_role = trim($_POST['about_us_team_member_2_role'] ?? '');
    $about_us_team_member_3 = trim($_POST['about_us_team_member_3'] ?? '');
    $about_us_team_member_3_role = trim($_POST['about_us_team_member_3_role'] ?? '');
    $about_us_team_member_4 = trim($_POST['about_us_team_member_4'] ?? '');
    $about_us_team_member_4_role = trim($_POST['about_us_team_member_4_role'] ?? '');

    if (count($images_arr) < 1) $field_errors['about_us_images'] = "At least one image is required.";
    if ($about_us_title === '') $field_errors['about_us_title'] = "Title is required.";
    if ($about_us_title_text === '') $field_errors['about_us_title_text'] = "Title text is required.";
    if ($about_us_who_we_are === '') $field_errors['about_us_who_we_are'] = "Who We Are is required.";
    if ($about_us_experience === '' || !is_numeric($about_us_experience)) $field_errors['about_us_experience'] = "Valid Experience is required.";
    if ($about_us_team_members === '' || !is_numeric($about_us_team_members) || $about_us_team_members < 1) $field_errors['about_us_team_members'] = "Team Members must be at least 1.";
    if ($about_us_team_member_1 === '') $field_errors['about_us_team_member_1'] = "Team Member 1 is required.";
    if ($about_us_team_member_1_role === '') $field_errors['about_us_team_member_1_role'] = "Role for Member 1 is required.";

    $about_us_images = implode(',', $images_arr);

    $has_error = false;
    foreach ($field_errors as $ferr) {
        if (!empty($ferr)) {
            $has_error = true;
            break;
        }
    }

    if (!$has_error) {
        $stmt = $conn->prepare("UPDATE about_us SET 
            about_us_title = ? , about_us_title_text = ?, about_us_images = ?, about_us_who_we_are = ?, about_us_experience = ?, about_us_team_members = ?,
            about_us_team_member_1 = ?, about_us_team_member_1_role = ?,
            about_us_team_member_2 = ?, about_us_team_member_2_role = ?,
            about_us_team_member_3 = ?, about_us_team_member_3_role = ?,
            about_us_team_member_4 = ?, about_us_team_member_4_role = ?
            WHERE about_us_id = 1
        ");
        if ($stmt) {
            $stmt->bind_param(
                "ssssisssssssss",
                $about_us_title, $about_us_title_text, $about_us_images, $about_us_who_we_are, $about_us_experience, $about_us_team_members,
                $about_us_team_member_1, $about_us_team_member_1_role,
                $about_us_team_member_2, $about_us_team_member_2_role,
                $about_us_team_member_3, $about_us_team_member_3_role,
                $about_us_team_member_4, $about_us_team_member_4_role
            );
            if ($stmt->execute()) {
                $success = true;
                header("Location: about_us.php?success=1");
                exit;
            } else {
                $errors[] = "Failed to update. Please try again.";
            }
            $stmt->close();
        } else {
            $errors[] = "Database error. Please try again.";
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = true;
}

// Ensure $images_arr is synced with DB/state
// $stmt = $conn->prepare("SELECT about_us_images FR 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit About Us</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="js/jquery-4.0.0.min.js"></script>
    <link rel="stylesheet" href="css/about_us.css">

</head>
<body>
<div class="dashboard-main">
    <h2 class="internal-header">Edit About Us</h2>
    <div class="coupon-form-wrap">
        <?php if (!empty($errors)): ?>
            <div class="error-msg">
                <?= implode('<br>', $errors) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-msg">
                About Us updated successfully.
            </div>
        <?php endif; ?>
        <form method="post" id="aboutUsForm" autocomplete="off" novalidate enctype="multipart/form-data">
            <div class="form-group">
                <label for="about_us_title">Title <span style="color:red">*</span></label>
                <input type="text" name="about_us_title" id="about_us_title" maxlength="100" value="<?= esc($about_us_title ?? '') ?>" />
                <span class="form-error" id="err_about_us_title"><?= $field_errors['about_us_title'] ?></span>
            </div>
            <div class="form-group">
                <label for="about_us_title_text">Title Text <span style="color:red">*</span></label>
                <input type="text" name="about_us_title_text" id="about_us_title_text" maxlength="100" value="<?= esc($about_us_title_text ?? '') ?>" />
                <span class="form-error" id="err_about_us_title_text"><?= $field_errors['about_us_title_text'] ?></span>
            </div>
            <div class="form-group about-us-img-list-col">
                <label>Images <span style="color:red">*</span></label>
                <div id="about_us_images_existing" class="img-upload-wrap">
                    <?php 
                    $img_idx = 0;
                    foreach ($images_arr as $img): ?>
                        <div class="about-us-img-row" id="imgrow-<?=esc($img_idx)?>">
                            <img class="about-us-img-thumb" src="../images/<?=esc($img)?>" alt="about image <?=esc($img_idx+1)?>" onerror="this.style.opacity='0.3';this.title='Image not found'"/>
                            <div style="flex:1"><?=esc($img)?></div>
                            <button 
                                type="button"
                                class="btn-del-img"
                                data-img="<?=esc($img)?>"
                                onclick="if(confirm('Delete this image?')) deleteAboutUsImage(this);"
                                title="Delete image"
                            >&times;</button>
                        </div>
                    <?php $img_idx++; endforeach; ?>
                </div>
                <div class="img-upload-input-row">
                    <input type="file" name="about_us_new_image" id="about_us_new_image" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" />
                    <br><br>
                </div>
                <input type="hidden" name="about_us_images" id="about_us_images" value="<?= esc($about_us_images ?? '') ?>">
                <span class="form-error" id="err_about_us_images"><?= $field_errors['about_us_images'] ?></span>
                <span class="form-error" id="err_about_us_new_image"><?= $field_errors['about_us_new_image'] ?></span>
            </div>
            <div class="form-group">
                <label for="about_us_who_we_are">Who We Are <span style="color:red">*</span></label>
                <textarea name="about_us_who_we_are" id="about_us_who_we_are" maxlength="255" style="min-height:120px;"><?= esc($about_us_who_we_are ?? '') ?></textarea>
                <span class="form-error" id="err_about_us_who_we_are"><?= $field_errors['about_us_who_we_are'] ?></span>
            </div>
            <div class="form-group">
                <label for="about_us_experience">Experience (years) <span style="color:red">*</span></label>
                <input type="number" name="about_us_experience" id="about_us_experience" min="0" value="<?= esc($about_us_experience ?? '') ?>" />
                <span class="form-error" id="err_about_us_experience"><?= $field_errors['about_us_experience'] ?></span>
            </div>
            <div class="form-group">
                <label for="about_us_team_members">Team Members <span style="color:red">*</span></label>
                <input type="number" name="about_us_team_members" id="about_us_team_members" min="1" value="<?= esc($about_us_team_members ?? '') ?>" />
                <span class="form-error" id="err_about_us_team_members"><?= $field_errors['about_us_team_members'] ?></span>
            </div>
            <hr>
            <div class="form-group">
                <label for="about_us_team_member_1">Team Member 1 <span style="color:red">*</span></label>
                <input type="text" name="about_us_team_member_1" id="about_us_team_member_1" maxlength="100" value="<?= esc($about_us_team_member_1 ?? '') ?>" />
                <span class="form-error" id="err_about_us_team_member_1"><?= $field_errors['about_us_team_member_1'] ?></span>
            </div>
            <div class="form-group">
                <label for="about_us_team_member_1_role">Role (Member 1) <span style="color:red">*</span></label>
                <input type="text" name="about_us_team_member_1_role" id="about_us_team_member_1_role" maxlength="100" value="<?= esc($about_us_team_member_1_role ?? '') ?>" />
                <span class="form-error" id="err_about_us_team_member_1_role"><?= $field_errors['about_us_team_member_1_role'] ?></span>
            </div>
            <div class="form-group">
                <label for="about_us_team_member_2">Team Member 2</label>
                <input type="text" name="about_us_team_member_2" id="about_us_team_member_2" maxlength="100" value="<?= esc($about_us_team_member_2 ?? '') ?>" />
                <span class="form-error" id="err_about_us_team_member_2"><?= $field_errors['about_us_team_member_2'] ?></span>
            </div>
            <div class="form-group">
                <label for="about_us_team_member_2_role">Role (Member 2)</label>
                <input type="text" name="about_us_team_member_2_role" id="about_us_team_member_2_role" maxlength="100" value="<?= esc($about_us_team_member_2_role ?? '') ?>" />
                <span class="form-error" id="err_about_us_team_member_2_role"><?= $field_errors['about_us_team_member_2_role'] ?></span>
            </div>
            <div class="form-group">
                <label for="about_us_team_member_3">Team Member 3</label>
                <input type="text" name="about_us_team_member_3" id="about_us_team_member_3" maxlength="100" value="<?= esc($about_us_team_member_3 ?? '') ?>" />
                <span class="form-error" id="err_about_us_team_member_3"><?= $field_errors['about_us_team_member_3'] ?></span>
            </div>
            <div class="form-group">
                <label for="about_us_team_member_3_role">Role (Member 3)</label>
                <input type="text" name="about_us_team_member_3_role" id="about_us_team_member_3_role" maxlength="100" value="<?= esc($about_us_team_member_3_role ?? '') ?>" />
                <span class="form-error" id="err_about_us_team_member_3_role"><?= $field_errors['about_us_team_member_3_role'] ?></span>
            </div>
            <div class="form-group">
                <label for="about_us_team_member_4">Team Member 4</label>
                <input type="text" name="about_us_team_member_4" id="about_us_team_member_4" maxlength="100" value="<?= esc($about_us_team_member_4 ?? '') ?>" />
                <span class="form-error" id="err_about_us_team_member_4"><?= $field_errors['about_us_team_member_4'] ?></span>
            </div>
            <div class="form-group">
                <label for="about_us_team_member_4_role">Role (Member 4)</label>
                <input type="text" name="about_us_team_member_4_role" id="about_us_team_member_4_role" maxlength="100" value="<?= esc($about_us_team_member_4_role ?? '') ?>" />
                <span class="form-error" id="err_about_us_team_member_4_role"><?= $field_errors['about_us_team_member_4_role'] ?></span>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Save</button>
            </div>
        </form>
        <script>
        function deleteAboutUsImage(btn) {
            var $row = $(btn).closest('.about-us-img-row');
            var img = $(btn).attr('data-img');
            if (!img) return;
            $.post('', {ajax_delete_image: img}, function(resp) {
                if (resp.success) {
                    $row.remove();
                    // Update the hidden field for images for validation (not used on save, but for client validation)
                    updateClientAboutUsImagesInput();
                } else {
                    alert('Failed to delete image.');
                }
            }, 'json');
        }
        function updateClientAboutUsImagesInput() {
            // For client-side validation, update the hidden input reflecting the images shown
            var imgs = [];
            $('#about_us_images_existing .about-us-img-row').each(function() {
                var filename = $(this).find('.btn-del-img').attr('data-img');
                if (filename) imgs.push(filename);
            });
            $('#about_us_images').val(imgs.join(','));
        }
        $(function() {
            // Validate on change and show error under each field individually
            function validateField(id, name, required, type, extra) {
                var val = $('#' + id).val();
                if(val && typeof val === 'string') val = val.trim();
                if(required && (!val || val === "")) {
                    $('#err_' + id).text(name + " is required.");
                    return false;
                }
                if(type === "number" && required) {
                    var num = parseInt(val, 10);
                    if(isNaN(num) || val === "" || (extra && typeof extra.min !== 'undefined' && num < extra.min)) {
                        $('#err_' + id).text("Valid " + name + (extra && extra.min ? (" (min "+extra.min+")") : "") + " is required.");
                        return false;
                    }
                }
                $('#err_' + id).text("");
                return true;
            }

            $('#about_us_title').on('change keyup blur', function() {
                validateField('about_us_title', "Title", true, "text");
            });
            $('#about_us_title_text').on('change keyup blur', function() {
                validateField('about_us_title_text', "Title Text", true, "text");
            });

            // Image upload validation on file pick (client-side only)
            $('#about_us_new_image').on('change', function() {
                var fileInput = this;
                var file = fileInput.files[0];
                var error = '';
                if (file) {
                    var allowed = ['image/jpeg','image/png','image/gif','image/webp'];
                    if (allowed.indexOf(file.type) == -1) {
                        error = "Please upload an image file (JPG, PNG, GIF, WEBP).";
                    }
                }
                $('#err_about_us_new_image').text(error);
            });

            $('#about_us_who_we_are').on('change keyup blur', function() {
                validateField('about_us_who_we_are', "Who We Are", true, "text");
            });
            $('#about_us_experience').on('change keyup blur', function() {
                validateField('about_us_experience', "Experience", true, "number");
            });
            $('#about_us_team_members').on('change keyup blur', function() {
                validateField('about_us_team_members', "Team Members", true, "number", {min:1});
            });
            $('#about_us_team_member_1').on('change keyup blur', function() {
                validateField('about_us_team_member_1', "Team Member 1", true, "text");
            });
            $('#about_us_team_member_1_role').on('change keyup blur', function() {
                validateField('about_us_team_member_1_role', "Role for Member 1", true, "text");
            });

            // Optional team members/roles validate but not required
            $('#about_us_team_member_2, #about_us_team_member_2_role, #about_us_team_member_3, #about_us_team_member_3_role, #about_us_team_member_4, #about_us_team_member_4_role')
                .on('change keyup blur', function() {
                var id = $(this).attr('id');
                $('#err_' + id).text("");
            });

            $('#aboutUsForm').on('submit', function(e) {
                var valid = true;
                valid &= validateField('about_us_title', "Title", true, "text");
                valid &= validateField('about_us_title_text', "Title Text", true, "text");
                // Validate images: require at least one
                updateClientAboutUsImagesInput(); // always update the hidden input to match DOM
                if ($('#about_us_images_existing .about-us-img-row').length < 1 && !$('#about_us_new_image').val()) {
                    $('#err_about_us_images').text("At least one image is required.");
                    valid = false;
                } else {
                    $('#err_about_us_images').text("");
                }
                valid &= validateField('about_us_who_we_are', "Who We Are", true, "text");
                valid &= validateField('about_us_experience', "Experience", true, "number");
                valid &= validateField('about_us_team_members', "Team Members", true, "number", {min:1});
                valid &= validateField('about_us_team_member_1', "Team Member 1", true, "text");
                valid &= validateField('about_us_team_member_1_role', "Role for Member 1", true, "text");
                if (!valid) e.preventDefault();
            });
        });
        </script>
    </div>
</div>
</body>
</html>
