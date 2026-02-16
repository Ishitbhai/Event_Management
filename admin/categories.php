<?php
session_start();
require_once('../database/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');
    $response = ['success' => false, 'msg' => ''];

    if (isset($_POST['add_category_ajax'])) {
        $name  = trim($_POST['category_name'] ?? '');
        $seats = trim($_POST['category_seats'] ?? '');
        $price = trim($_POST['category_price_per_hour'] ?? '');

        if ($name === '' || $seats === '' || $price === '') {
            $response['msg'] = "All fields are required.";
        } elseif (!is_numeric($seats) || !is_numeric($price) || intval($seats) <= 0 || floatval($price) < 0) {
            $response['msg'] = "Seats and price must be valid numbers.";
        } else {
            $stmt = $conn->prepare("INSERT INTO category (category_name, category_seats, category_price_per_hour) VALUES (?, ?, ?)");
            $stmt->bind_param("sii", $name, $seats, $price);
            if ($stmt->execute()) {
                $response['success'] = true;
            } else {
                $response['msg'] = "Failed to add category.";
            }
            $stmt->close();
        }

        echo json_encode($response);
        exit;
    }

    if (isset($_POST['update_category_ajax'])) {

        $id    = intval($_POST['category_id']);
        $name  = trim($_POST['category_name'] ?? '');
        $seats = trim($_POST['category_seats'] ?? '');
        $price = trim($_POST['category_price_per_hour'] ?? '');

        if ($name === '' || $seats === '' || $price === '') {
            $response['msg'] = "All fields are required.";
        } elseif (!is_numeric($seats) || !is_numeric($price) || intval($seats) <= 0 || floatval($price) < 0) {
            $response['msg'] = "Seats and price must be valid numbers.";
        } else {
            // NEW: Find the maximum number of booked seats from all events of this category
            // event_seats in events table
            $max_booked_seats = 0;
            $q = $conn->prepare("SELECT MAX(event_seats) as max_booked FROM events WHERE event_category=?");
            $q->bind_param("i", $id);
            $q->execute();
            $r = $q->get_result();
            if ($row = $r->fetch_assoc()) {
                $max_booked_seats = intval($row['max_booked']);
            }
            $q->close();

            if ($max_booked_seats > 0 && intval($seats) < $max_booked_seats) {
                $response['msg'] = "Cannot reduce seats below maximum booked seats in any event of this category (currently maximum: $max_booked_seats).";
            } else {
                $stmt = $conn->prepare("UPDATE category SET category_name=?, category_seats=?, category_price_per_hour=? WHERE category_id=?");
                $stmt->bind_param("siii", $name, $seats, $price, $id);
                if ($stmt->execute()) {
                    $response['success'] = true;
                } else {
                    $response['msg'] = "Update failed.";
                }
                $stmt->close();
            }
        }

        echo json_encode($response);
        exit;
    }

    // Enhanced "delete_category_id" handler to delete related events and bookings
    if (isset($_POST['delete_category_id'])) {
        $id = intval($_POST['delete_category_id']);
        // Begin transaction
        $conn->begin_transaction();
        try {
            // 1. Get all related event IDs from this category
            $event_ids = [];
            $evtStmt = $conn->prepare("SELECT event_id FROM events WHERE event_category=?");
            $evtStmt->bind_param("i", $id);
            $evtStmt->execute();
            $result = $evtStmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $event_ids[] = intval($row['event_id']);
            }
            $evtStmt->close();

            if (!empty($event_ids)) {
                // 2. Delete bookings for those events (assuming bookings.event_id exists)
                $ids_placeholders = implode(',', array_fill(0, count($event_ids), '?'));
                $types = str_repeat('i', count($event_ids));
                $delBookingsSql = "DELETE FROM bookings WHERE event_id IN ($ids_placeholders)";
                $delBookingsStmt = $conn->prepare($delBookingsSql);
                $delBookingsStmt->bind_param($types, ...$event_ids);
                $delBookingsStmt->execute();
                $delBookingsStmt->close();

                // 3. Delete events for this category
                $delEventsSql = "DELETE FROM events WHERE event_id IN ($ids_placeholders)";
                $delEventsStmt = $conn->prepare($delEventsSql);
                $delEventsStmt->bind_param($types, ...$event_ids);
                $delEventsStmt->execute();
                $delEventsStmt->close();
            }

            // 4. Delete the category
            $stmt = $conn->prepare("DELETE FROM category WHERE category_id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $conn->commit();
                $response['success'] = true;
            } else {
                $conn->rollback();
                $response['msg'] = "Delete failed (final step).";
            }
            $stmt->close();
        } catch (Exception $ex) {
            $conn->rollback();
            $response['msg'] = "Delete failed: ".$ex->getMessage();
        }

        echo json_encode($response);
        exit;
    }
}

// Only admins allowed
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header("Location: login.php");
    exit();
}
require_once('sidebar.php');

// ---- PAGINATION LOGIC (like events) ----
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$qTotal = $conn->query("SELECT COUNT(*) as count FROM category");
$totalRows = ($qTotal && $qTotal->num_rows > 0) ? intval($qTotal->fetch_assoc()['count']) : 0;
$totalPages = max(1, ceil($totalRows / $perPage));
$offset = ($page-1)*$perPage;

$categories = [];
$res = $conn->query("SELECT category_id, category_name, category_seats, category_price_per_hour FROM category ORDER BY category_id DESC LIMIT $perPage OFFSET $offset");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
}
function esc($x) { return htmlspecialchars($x ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Categories</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="css/categories.css">
</head>
<body>
<div class="categories-container">
    <div class="categories-header-row">
        <h2 class="categories-head">Manage Categories</h2>
        <button class="add-cat-btn" id="openModalBtn">+ Add Category</button>
    </div>
    <div style="overflow-x:auto;">
        <table class="category-table">
            <thead>
                <tr>
                    <th>Sr No.</th>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Seats Capacity</th>
                    <th>Price per Hour</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php 
                $sr = 1 + $perPage * ($page-1);
                foreach($categories as $cat): ?>
                <tr>
                    <td><?= $sr++ ?></td>
                    <td><?= esc($cat['category_id']) ?></td>
                    <td><?= esc($cat['category_name']) ?></td>
                    <td><?= esc($cat['category_seats']) ?></td>
                    <td>₹<?= esc($cat['category_price_per_hour']) ?></td>
                    <td>
                        <button class="cat-edit-btn editBtn"
                            data-id="<?= esc($cat['category_id']) ?>"
                            data-name="<?= esc($cat['category_name']) ?>"
                            data-seats="<?= esc($cat['category_seats']) ?>"
                            data-price="<?= esc($cat['category_price_per_hour']) ?>">
                            Edit
                        </button>
                        <button class="cat-del-btn deleteBtn" data-id="<?= esc($cat['category_id']) ?>">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if(count($categories) == 0): ?>
            <tr><td colspan="6" style="text-align:center;color:#8f84c3;font-style:italic;">No categories found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <!-- Classic Pagination Bar -->
    <?php if($totalPages > 1): ?>
    <div class="classic-pagination">
        <ul>
            <?php
            function cp_pageUrl($p) {
                $q = $_GET; $q['page']=$p;
                return '?' . http_build_query($q);
            }
            // prev
            if($page > 1) {
                echo '<li><a href="'.cp_pageUrl($page-1).'">&laquo;</a></li>';
            } else {
                echo '<li><span class="disabled">&laquo;</span></li>';
            }

            $win = 2;
            $start = max(1, $page - $win);
            $end = min($totalPages, $page + $win);

            if ($start > 1) {
                echo '<li><a href="'.cp_pageUrl(1).'">1</a></li>';
                if ($start > 2)
                    echo '<li><span class="disabled">...</span></li>';
            }
            for($i = $start; $i <= $end; $i++) {
                if ($i == $page) 
                    echo '<li><span class="active">'.$i.'</span></li>';
                else 
                    echo '<li><a href="'.cp_pageUrl($i).'">'.$i.'</a></li>';
            }
            if ($end < $totalPages) {
                if ($end < $totalPages-1)
                    echo '<li><span class="disabled">...</span></li>';
                echo '<li><a href="'.cp_pageUrl($totalPages).'">'.$totalPages.'</a></li>';
            }
            // next
            if ($page < $totalPages) {
                echo '<li><a href="'.cp_pageUrl($page+1).'">&raquo;</a></li>';
            } else {
                echo '<li><span class="disabled">&raquo;</span></li>';
            }
            ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<div class="pop-overlay" id="catModalOverlay">
    <div class="pop-modal">
        <button type="button" class="modal-close" id="closeModalBtn">&times;</button>
        <h3 id="addCatTitle">Add New Category</h3>
        <div class="form-feedback" id="formFeedback"></div>
        <form id="addCatForm" autocomplete="off">
            <input type="hidden" id="edit_category_id" name="category_id">
            <div class="form-group">
                <label for="category_name">Category Name</label>
                <input type="text" name="category_name" id="category_name" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="category_seats">Seats Capacity</label>
                <input type="number" name="category_seats" id="category_seats" min="1" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="category_price_per_hour">Price per Hour (₹)</label>
                <input type="number" name="category_price_per_hour" id="category_price_per_hour" min="0" autocomplete="off" step="any">
            </div>
            <button type="submit" class="cat-modal-submit" id="catModalSubmitBtn">Add Category</button>
        </form>
    </div>
</div>

<script>
// Helper function to show and reset validation UI
function setInputError(input, msg = '') {
    if (msg) {
        input.classList.add('error');
    } else {
        input.classList.remove('error');
    }
    return msg;
}

const overlay = document.getElementById('catModalOverlay');
const addCatForm = document.getElementById('addCatForm');
const feedbackDiv = document.getElementById('formFeedback');
const nameInput = document.getElementById('category_name');
const seatsInput = document.getElementById('category_seats');
const priceInput = document.getElementById('category_price_per_hour');

// Form field validation: returns error message or empty string
function validateFields() {
    let err = '';
    feedbackDiv.innerText = '';
    let nameVal = nameInput.value.trim();
    let seatsVal = seatsInput.value.trim();
    let priceVal = priceInput.value.trim();

    setInputError(nameInput, '');
    setInputError(seatsInput, '');
    setInputError(priceInput, '');

    if (nameVal === '') {
        err = setInputError(nameInput, 'Category name required.');
    }
    if (seatsVal === '') {
        setInputError(seatsInput, 'Seats required.');
        err = 'All fields are required.';
    } else if (isNaN(seatsVal) || parseInt(seatsVal) <= 0) {
        setInputError(seatsInput, 'Seats must be a positive number.');
        err = 'Seats must be a valid positive number.';
    }
    if (priceVal === '') {
        setInputError(priceInput, 'Price required.');
        err = 'All fields are required.';
    } else if (isNaN(priceVal) || parseFloat(priceVal) < 0) {
        setInputError(priceInput, 'Price must be a valid number >= 0.');
        err = 'Price must be a valid non-negative number.';
    }
    return err;
}

// Live validation on change
nameInput.addEventListener('input', function() {
    setInputError(nameInput, nameInput.value.trim() === '' ? 'Category name required.' : '');
    feedbackDiv.innerText = '';
});
seatsInput.addEventListener('input', function() {
    setInputError(
        seatsInput,
        seatsInput.value.trim() === '' ? 'Seats required.'
        : (isNaN(seatsInput.value) || parseInt(seatsInput.value) <= 0 ? 'Seats must be a positive number.' : '')
    );
    feedbackDiv.innerText = '';
});
priceInput.addEventListener('input', function() {
    setInputError(
        priceInput,
        priceInput.value.trim() === '' ? 'Price required.'
        : (isNaN(priceInput.value) || parseFloat(priceInput.value) < 0 ? 'Price must be a valid number >= 0.' : '')
    );
    feedbackDiv.innerText = '';
});

document.getElementById('openModalBtn').onclick = function() {
    overlay.classList.add('open');
    addCatForm.reset();
    document.getElementById('edit_category_id').value = '';
    document.getElementById('addCatTitle').innerText = "Add New Category";
    document.getElementById('catModalSubmitBtn').innerText = "Add Category";
    [nameInput, seatsInput, priceInput].forEach(i => setInputError(i, ''));
    feedbackDiv.innerText = '';
};
document.getElementById('closeModalBtn').onclick = function() {
    overlay.classList.remove('open');
};
overlay.onclick = e => {
    if (e.target === overlay) overlay.classList.remove('open');
};

document.querySelectorAll('.editBtn').forEach(btn => {
    btn.onclick = function() {
        overlay.classList.add('open');
        document.getElementById('edit_category_id').value = this.dataset.id;
        nameInput.value = this.dataset.name;
        seatsInput.value = this.dataset.seats;
        priceInput.value = this.dataset.price;
        document.getElementById('addCatTitle').innerText = "Edit Category";
        document.getElementById('catModalSubmitBtn').innerText = "Update Category";
        [nameInput, seatsInput, priceInput].forEach(i => setInputError(i, ''));
        feedbackDiv.innerText = '';
    };
});

addCatForm.onsubmit = function(e) {
    e.preventDefault();
    // Validate before submit
    const errorMsg = validateFields();
    if (errorMsg) {
        feedbackDiv.innerText = errorMsg;
        return false;
    }
    let id = document.getElementById('edit_category_id').value;
    let data = new FormData(this);
    if (id) {
        data.append('update_category_ajax', 1);
    } else {
        data.append('add_category_ajax', 1);
    }
    fetch('categories.php', {method: 'POST', body: data})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                location.reload();
            } else {
                feedbackDiv.innerText = res.msg;
            }
        });
};

// New delete handler with more complete message.
document.querySelectorAll('.deleteBtn').forEach(btn => {
    btn.onclick = function() {
        if (!confirm("Are you sure? If you delete this category, ALL EVENTS from this category will be deleted, and all bookings for those events will be deleted as well. Do you want to continue?")) return;
        let data = new URLSearchParams();
        data.append('delete_category_id', this.dataset.id);
        fetch('categories.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: data})
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    location.reload();
                } else {
                    alert(res.msg);
                }
            });
    };
});
</script>
</body>
</html>
