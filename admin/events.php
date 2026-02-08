<?php
session_start();
require_once('sidebar.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once('../database/db_connect.php');

// Add created_at and updated_at to displayed fields
$fields = [
    'event_id',
    'owner_id',
    'event_category',
    'event_title',
    'event_description',
    'event_date',
    'event_start_time',
    'event_end_time',
    'event_seats',
    'event_available_seats',
    'event_registration_deadline',
    'event_approval_status',
    'event_status',
    'event_paymeny_status',
    'event_banner_image',
    'event_gallery_images',
    'created_at',
    'updated_at'
];

// --- Handle AJAX update for dropdown in event table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['table_ajax_update'])) {
    $output = ['status' => 0, 'msg' => ''];
    $eid = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $col = isset($_POST['col']) ? $_POST['col'] : '';
    $val = isset($_POST['val']) ? $_POST['val'] : '';
    if (in_array($col, ['event_status', 'event_approval_status', 'event_paymeny_status']) && $eid > 0) {
        $extraSet = '';
        $types = 'si';
        $vals = [$val, $eid];
        if ($col === 'event_approval_status') {
            if ($val === 'approved') {
                $extraSet = ', event_status = ?';
                $types = 'ssi';
                $vals = [$val, 'published', $eid];
            } elseif ($val === 'rejected') {
                $extraSet = ', event_status = ?';
                $types = 'ssi';
                $vals = [$val, 'cancelled', $eid];
            } elseif ($val === 'pending') {
                $extraSet = ', event_status = ?';
                $types = 'ssi';
                $vals = [$val, 'draft', $eid];
            }
        }
        // Add `updated_at` field to be set to the current timestamp
        $stmt = $conn->prepare("UPDATE events SET `$col`=?$extraSet, updated_at=CURRENT_TIMESTAMP WHERE event_id=?");
        if ($stmt) {
            $stmt->bind_param($types, ...$vals);
            if ($stmt->execute()) {
                $output['status'] = 1;
                $output['msg'] = "Updated!";
                if ($col === 'event_approval_status') {
                    if ($val === 'approved') {
                        $output['event_status'] = 'published';
                    } elseif ($val === 'rejected') {
                        $output['event_status'] = 'cancelled';
                    } elseif ($val === 'pending') {
                        $output['event_status'] = 'draft';
                    }
                }
            } else {
                $output['msg'] = "Error while updating database.";
            }
            $stmt->close();
        } else {
            $output['msg'] = "Failed to prepare statement.";
        }
    } else {
        $output['msg'] = "Invalid operation.";
    }
    header('Content-Type: application/json');
    echo json_encode($output);
    exit;
}

// ---- Fetch and sort events for display ----
$events = [];
$event_columns = [];
$fetch_result = $conn->query("SELECT * FROM events");
if ($fetch_result && $fetch_result->num_rows > 0) {
    $event_columns = array_keys($fetch_result->fetch_assoc());
    $fetch_result->data_seek(0);
    while ($row = $fetch_result->fetch_assoc()) {
        $events[] = $row;
    }
} elseif ($fetch_result) {
    $event_columns = [];
}

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Sort events by event_start_time in ascending order
usort($events, function($a, $b) {
    $dateCmp = strcmp($a['event_date'], $b['event_date']);
    if ($dateCmp !== 0) {
        return $dateCmp;
    }
    $a_time = $a['event_start_time'] ?? '';
    $b_time = $b['event_start_time'] ?? '';
    return strcmp($a_time, $b_time);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Events</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="css/events.css">
    <link rel="stylesheet" href="css/index.css">
    
    <script>
    // Internalized JS for page: moved previous inline logic to event listeners below
    document.addEventListener('DOMContentLoaded', function() {
        // dropdown change logic
        document.querySelectorAll('.table-edit-select').forEach(function(el){
            el.addEventListener('change', function(){
                const selectEl = this;
                const event_id = selectEl.getAttribute('data-eid');
                const col = selectEl.getAttribute('data-col');
                const val = selectEl.value;
                const cell = selectEl.parentElement;
                let spinner = cell.querySelector('.inline-dropdown-spinner');
                if (!spinner) {
                    spinner = document.createElement('span');
                    spinner.className = 'inline-dropdown-spinner';
                    spinner.style.display = '';
                    cell.appendChild(spinner);
                } else {
                    spinner.style.display = '';
                }
                selectEl.disabled = true;
                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        table_ajax_update: 1,
                        event_id: event_id,
                        col: col,
                        val: val
                    })
                }).then(r=>r.json())
                .then(data=>{
                    spinner.style.display = 'none';
                    selectEl.disabled = false;
                    window.location.reload();
                }).catch(err=>{
                    spinner.style.display = 'none';
                    selectEl.disabled = false;
                    window.location.reload();
                });
            });
        });

        // Create Event button logic
        let createBtn = document.querySelector('.create-event-btn');
        if (createBtn) {
            createBtn.addEventListener('click', function() {
                window.location.href = 'event_create.php';
            });
        }

        // Edit buttons event delegation
        document.querySelectorAll('.edit-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                let tr = btn.closest('tr[data-event-id]');
                if (tr) {
                    let eid = tr.getAttribute('data-event-id');
                    window.location.href = 'events_edit.php?event_id=' + eid;
                }
            });
        });

        // Delete buttons event delegation
        document.querySelectorAll('.delete-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                let tr = btn.closest('tr[data-event-id]');
                if (tr) {
                    let eid = tr.getAttribute('data-event-id');
                    if (confirm('Are you sure you want to delete this event?')) {
                        window.location.href = '?delete_event_id=' + eid;
                    }
                }
            });
        });

    });
    </script>
</head>
<body>
    <div class="dashboard-main">
        <div class="events-header">
            <h2 class="internal-header">Manage Events</h2>
            <button class="create-event-btn" type="button">
                Create Event
            </button>
        </div>
        <div class="event-table-container">
        <?php if (count($events) === 0): ?>
            <div class="internal-no-events">
                No events available.
            </div>
        <?php else: ?>
            <table class="event-table">
                <thead>
                    <tr>
                        <?php
                        $col_headings = [
                            'event_id' => 'Event ID',
                            'owner_id' => 'Owner ID',
                            'event_category' => 'Category',
                            'event_title' => 'Title',
                            'event_description' => 'Description',
                            'event_date' => 'Event Date',
                            'event_start_time' => 'Start Time',
                            'event_end_time' => 'End Time',
                            'event_seats' => 'Seats',
                            'event_available_seats' => 'Available Seats',
                            'event_registration_deadline' => 'Registration Deadline',
                            'event_approval_status' => 'Approval Status',
                            'event_status' => 'Status',
                            'event_paymeny_status' => 'Payment Status',
                            'event_banner_image' => 'Banner',
                            'event_gallery_images' => 'Gallery',
                            'created_at' => 'Created At',
                            'updated_at' => 'Updated At'
                        ];
                        foreach ($fields as $col): ?>
                            <th class="<?php echo esc($col); echo $col==='event_description'?' description-cell':'';?>">
                                <?= esc($col_headings[$col] ?? $col) ?>
                            </th>
                        <?php endforeach; ?>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $ev): ?>
                        <tr data-event-id="<?php echo (int)$ev['event_id']; ?>">
                            <?php foreach ($fields as $col): ?>
                                <td class="<?= esc($col) ?><?= $col === 'event_description' ? ' description-cell' : '' ?>">
                                <?php
                                    // Banner image (image only, no name)
                                    if ($col === 'event_banner_image') {
                                        $img_name = !empty($ev[$col]) ? basename($ev[$col]) : '';
                                        $img_path = !empty($img_name) ? '../images/' . $img_name : '';
                                        if (!empty($img_name) && file_exists("../images/" . $img_name)) {
                                            echo "<img src=\"" . esc($img_path) . "\" alt=\"banner\" class=\"event-banner-thumb\">";
                                        } else {
                                            echo "<span class='internal-no-image'>No image uploaded</span>";
                                        }
                                    }
                                    // Gallery images (images only, comma separated)
                                    else if ($col === 'event_gallery_images') {
                                        if (!empty($ev[$col])) {
                                            $gallery = explode(',', $ev[$col]);
                                            $hasImg = false;
                                            foreach ($gallery as $gimg) {
                                                $gimg_name = trim(basename($gimg));
                                                if ($gimg_name) {
                                                    $gimgSrc = '../images/' . $gimg_name;
                                                    if (file_exists("../images/" . $gimg_name)) {
                                                        $hasImg = true;
                                                        echo "<img src=\"" . esc($gimgSrc) . "\" class=\"event-gallery-thumb\" alt=\"gallery\">";
                                                    }
                                                }
                                            }
                                            if (!$hasImg) {
                                                echo "<span class='internal-no-image'>No image uploaded</span>";
                                            }
                                        } else {
                                            echo "<span class='internal-no-image'>No image uploaded</span>";
                                        }
                                    }
                                    // Dropdown fields for event table
                                    else if ($col === 'event_approval_status') {
                                        $opts = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
                                        echo '<select class="table-edit-select" data-eid="' . (int)$ev['event_id'] . '" data-col="event_approval_status">';
                                        foreach ($opts as $k => $v)
                                            echo '<option value="' . esc($k) . '"' . ($ev[$col] === $k ? ' selected' : '') . '>' . esc($v) . '</option>';
                                        echo '</select>';
                                        echo '<span class="inline-dropdown-spinner" style="display:none;"></span>';
                                    }
                                    else if ($col === 'event_status') {
                                        $opts = [
                                            'draft' => 'Draft',
                                            'published' => 'Published',
                                            'ongoing' => 'Ongoing',
                                            'cancelled' => 'Cancelled',
                                            'completed' => 'Completed'
                                        ];
                                        echo '<select class="table-edit-select" data-eid="' . (int)$ev['event_id'] . '" data-col="event_status">';
                                        foreach ($opts as $k => $v)
                                            echo '<option value="' . esc($k) . '"' . ($ev[$col] === $k ? ' selected' : '') . '>' . esc($v) . '</option>';
                                        echo '</select>';
                                        echo '<span class="inline-dropdown-spinner" style="display:none;"></span>';
                                    }
                                    else if ($col === 'event_paymeny_status') {
                                        $opts = ['pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed'];
                                        echo '<select class="table-edit-select" data-eid="' . (int)$ev['event_id'] . '" data-col="event_paymeny_status">';
                                        foreach ($opts as $k => $v)
                                            echo '<option value="' . esc($k) . '"' . ($ev[$col] === $k ? ' selected' : '') . '>' . esc($v) . '</option>';
                                        echo '</select>';
                                        echo '<span class="inline-dropdown-spinner" style="display:none;"></span>';
                                    }
                                    // Description: allow wrap
                                    else if ($col === 'event_description') {
                                        echo '<div class="internal-description-cell-div">'.esc($ev[$col]).'</div>';
                                    }
                                    // created_at and updated_at: show as datetime, allow wrap, smaller font
                                    else if ($col === 'created_at' || $col === 'updated_at') {
                                        $dt = esc($ev[$col]);
                                        if ($dt) {
                                            echo '<span class="internal-created-updated">'. $dt .'</span>';
                                        } else {
                                            echo '-';
                                        }
                                    }
                                    else {
                                        echo esc($ev[$col]);
                                    }
                                ?>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <button
                                    class="edit-btn"
                                    type="button"
                                >Edit</button>
                                <button 
                                    class="delete-btn"
                                    type="button"
                                >Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
    </div>
    <?php
    // --- Delete event handler (GET param for quick admin use) ---
    if (isset($_GET['delete_event_id']) && is_numeric($_GET['delete_event_id'])) {
        $del_id = intval($_GET['delete_event_id']);
        if ($del_id > 0) {
            // First, delete all bookings associated with this event
            $stmt_b = $conn->prepare("DELETE FROM bookings WHERE event_id=?");
            if ($stmt_b) {
                $stmt_b->bind_param("i", $del_id);
                $stmt_b->execute();
                $stmt_b->close();
            }
            // Then, delete the event itself
            $stmt = $conn->prepare("DELETE FROM events WHERE event_id=?");
            if ($stmt) {
                $stmt->bind_param("i", $del_id);
                $stmt->execute();
                $stmt->close();
                echo "<script>window.location.href=window.location.pathname;</script>";
                exit;
            }
        }
    }
    ?>
</body>
</html>
