<?php
session_start();
require_once('sidebar.php');
require_once('../database/db_connect.php');

// --- Dropdown update fix: correct POST param name ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dropdown_update'])) {
    // Correct param name should be user_id not delet_user_id
    $uid = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $col = $_POST['col'] ?? '';
    $val = $_POST['val'] ?? '';
    if ($uid > 0 && in_array($col, ['user_status','user_type'])) {
        $stmt = $conn->prepare("UPDATE users SET `$col`=? WHERE user_id=?");
        if ($stmt) {
            $stmt->bind_param("si", $val, $uid);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

$users = [];
$result = $conn->query("SELECT * FROM users ORDER BY registered_at DESC");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

function esc($str){
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

$page = isset($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$per_page = 10;
$total_users = count($users);
$total_pages = ceil($total_users / $per_page);
$start = ($page - 1) * $per_page;
$paged_users = array_slice($users, $start, $per_page);
$serial_start = $start + 1;
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Manage Users</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    body {
    margin: 0;
    background: #f4f6fb;
}
.dashboard-main {
    padding: 40px;
}
.dashboard-header {
    margin-bottom: 30px;
}
.dashboard-header h2 {
    margin: 0;
    color: #322053;
}
.dashboard-header p {
    color: #6c757d;
    margin-top: 8px;
}
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
}
@media (max-width: 980px) {
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 700px) {
    .dashboard-cards {
        grid-template-columns: 1fr;
    }
}
.dashboard-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
    transition: box-shadow 0.3s ease, transform 0.5s cubic-bezier(.68,-0.55,.27,1.55);
    cursor: pointer;
    opacity: 0;
    transform: translateY(40px) scale(0.93);
    animation: fadeInUp 0.8s cubic-bezier(.68,-0.55,.27,1.55) forwards;
}
.dashboard-card:nth-child(1) { animation-delay: 0.10s; }
.dashboard-card:nth-child(2) { animation-delay: 0.20s; }
.dashboard-card:nth-child(3) { animation-delay: 0.30s; }
.dashboard-card:nth-child(4) { animation-delay: 0.40s; }
.dashboard-card:nth-child(5) { animation-delay: 0.50s; }
.dashboard-card:nth-child(6) { animation-delay: 0.60s; }
.dashboard-card:nth-child(7) { animation-delay: 0.70s; }
.dashboard-card:nth-child(8) { animation-delay: 0.80s; }
@keyframes fadeInUp {
    0% {
        opacity: 0;
        transform: translateY(40px) scale(0.93);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
.dashboard-card:hover {
    transform: translateY(-6px) scale(1.04);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    z-index: 1;
}
.card-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
}
.card-number {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 8px;
    transition: color 0.5s;
}
.card-link {
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    color: black;
}
.events { border-left: 6px solid #5236d6; }
.bookings { border-left: 6px solid #197655; }
.users { border-left: 6px solid #c82f2f; }
.services { border-left: 6px solid #197655; }
.reviews { border-left: 6px solid #c82f2f; }
.categories { border-left: 6px solid #5236d6; }
.coupons { border-left: 6px solid #c82f2f; }
.settings { border-left: 6px solid #5236d6; }

.events .card-title { color: #5236d6; }
.bookings .card-title { color: #197655; }
.users .card-title { color: #c82f2f; }
.services .card-title { color: #197655; }
.reviews .card-title { color: #c82f2f; }
.categories .card-title { color: #5236d6; }
.coupons .card-title { color: #c82f2f; }
.settings .card-title { color: #5236d6; }
/* --- Animations for user table, values come in "one by one" --- */
@keyframes fadeInUpRowStaggerUser {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
@keyframes fadeInCellStaggerUser {
  from {
    opacity: 0;
    transform: scale(0.97) translateY(10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

body {
    font-size: 16px;
}
.dashboard-main {
    /* Ref: bookings.php plus more width, NOT full width (room for sidebar) */
    max-width: 1600px;
    min-width: 1020px;
    margin: 40px auto 0 auto;
    padding: 0 38px 38px 38px;
    font-size: 16px;
    /* Animate container */
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.60s 0.08s both;
}
.event-table-container {
    overflow-x:auto;
    margin-top:10px;
    background:#fff;
    border-radius:12px;
    box-shadow:0 1px 10px rgba(44,62,80,0.09);
    padding:24px 20px 24px 20px;
    /* Animate container */
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.45s 0.10s both;
}
table.event-table {
    border-collapse: collapse;
    min-width:1500px;
    width:100%;
    font-size: 16px;
    /* Animate table coming in */
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.45s 0.15s both;
}
/* Animate rows one by one */
.event-table tr {
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.37s both;
}
.event-table tr:nth-child(1) { animation-delay: 0.18s; }
.event-table tr:nth-child(2) { animation-delay: 0.23s; }
.event-table tr:nth-child(3) { animation-delay: 0.28s; }
.event-table tr:nth-child(4) { animation-delay: 0.33s; }
.event-table tr:nth-child(5) { animation-delay: 0.38s; }
.event-table tr:nth-child(n+6) { animation-delay: 0.43s; }
/* Animate each cell staggered inside their row */
.event-table th,
.event-table td {
    padding:10px 13px;
    border-bottom:1px solid #e6e7f0;
    font-size: 16px;
    white-space:nowrap;
    opacity: 0;
    animation: fadeInCellStaggerUser 0.28s both;
}
.event-table th:nth-child(1), .event-table td:nth-child(1) { animation-delay: 0.14s; }
.event-table th:nth-child(2), .event-table td:nth-child(2) { animation-delay: 0.19s; }
.event-table th:nth-child(3), .event-table td:nth-child(3) { animation-delay: 0.24s; }
.event-table th:nth-child(4), .event-table td:nth-child(4) { animation-delay: 0.29s; }
.event-table th:nth-child(5), .event-table td:nth-child(5) { animation-delay: 0.34s; }
.event-table th:nth-child(n+6), .event-table td:nth-child(n+6) { animation-delay: 0.39s; }
.event-table th{
    background:#f4f6fb;
    color:#322053;
    font-weight:600;
    font-size: 16px;
}
.event-table tr:nth-child(even){
    background:#f9fafe;
}
.create-event-btn{
    background:linear-gradient(90deg,#2d397a,#594285);
    color:#fff;
    padding:7px 20px;
    border:none;
    border-radius:7px;
    font-weight:700;
    cursor:pointer;
    font-size: 16px;
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.39s 0.32s both;
}
.table-edit-select {
    padding:7px 13px;
    border-radius:6px;
    border:1px solid #ccc;
    font-size: 16px;
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.36s 0.36s both;
}
.edit-btn{
    background:#327ac5;
    color:#fff;
    border:none;
    padding:7px 14px;
    border-radius:5px;
    cursor:pointer;
    font-size: 16px;
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.33s 0.40s both;
}
.delete-btn{
    background:#e94242;
    color:#fff;
    border:none;
    padding:7px 14px;
    border-radius:5px;
    cursor:pointer;
    font-size: 16px;
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.33s 0.44s both;
}

    /* Animations for user table, values come in "one by one" */
    @keyframes fadeInUpRowStaggerUser {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    @keyframes fadeInCellStaggerUser {
      from {
        opacity: 0;
        transform: scale(0.97) translateY(10px);
      }
      to {
        opacity: 1;
        transform: scale(1) translateY(0);
      }
    }

    html, body {
        font-size: 16px;
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        width: 100%;
    }

    body {
        min-height: 100vh;
        background: #f4f6fb;
    }

    .dashboard-main {
        max-width: 1600px;
        width: 100%;
        margin: 40px auto 0 auto;
        padding: 0 24px 24px 24px;
        font-size: 16px;
        box-sizing: border-box;
        background: transparent; /* Don't force bg, only for the table-container */
        opacity: 0;
        animation: fadeInUpRowStaggerUser 0.60s 0.08s both;
    }

    .event-table-container {
        overflow-x: auto;
        margin-top: 16px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 10px rgba(44,62,80,0.09);
        padding: 24px 12px 24px 12px;
        opacity: 0;
        animation: fadeInUpRowStaggerUser 0.45s 0.10s both;
        transition: box-shadow 0.2s;
    }

    table.event-table {
        border-collapse: collapse;
        min-width: 1200px;
        width: 100%;
        font-size: 16px;
        background: #fff;
        opacity: 0;
        animation: fadeInUpRowStaggerUser 0.45s 0.15s both;
        transition: font-size 0.25s;
    }
    /* Responsive: Table min-width is 900px on small screens, but can scroll. */
    @media (max-width: 1020px) {
        .dashboard-main {
            min-width: 0;
            padding: 0 4vw 24px 4vw;
        }
        .event-table-container {
            padding: 12px 3vw 16px 3vw;
            overflow-x: auto;
        }
        table.event-table {
            min-width: 900px;
            font-size: 15px;
        }
    }
    @media (max-width: 700px) {
        .dashboard-main {
            min-width: 0;
            padding: 0 1vw 8vw 1vw;
        }
        .event-table-container {
            padding: 4px 0 10px 0;
        }
        table.event-table {
            min-width:650px;
            font-size: 14px;
        }
    }
    /* Hide some columns on small screens with horizontal scrolling */
    @media (max-width: 600px) {
        .event-table th:nth-child(2), .event-table td:nth-child(2),
        .event-table th:nth-child(5), .event-table td:nth-child(5),
        .event-table th:nth-child(6), .event-table td:nth-child(6),
        .event-table th:nth-child(9), .event-table td:nth-child(9),
        .event-table th:nth-child(10), .event-table td:nth-child(10)
        {
            display: none;
        }
        .dashboard-main {
            padding: 0 0vw 14vw 0vw;
        }
    }

    /* Animations */
    .event-table tr {
        opacity: 0;
        animation: fadeInUpRowStaggerUser 0.37s both;
    }
    .event-table tr:nth-child(1) { animation-delay: 0.18s; }
    .event-table tr:nth-child(2) { animation-delay: 0.23s; }
    .event-table tr:nth-child(3) { animation-delay: 0.28s; }
    .event-table tr:nth-child(4) { animation-delay: 0.33s; }
    .event-table tr:nth-child(5) { animation-delay: 0.38s; }
    .event-table tr:nth-child(n+6) { animation-delay: 0.43s; }
    .event-table th,
    .event-table td {
        padding: 8px 6px;
        border-bottom: 1px solid #e6e7f0;
        font-size: inherit;
        white-space: nowrap;
        overflow: auto;
        opacity: 0;
        animation: fadeInCellStaggerUser 0.28s both;
    }
    .event-table th:nth-child(1), .event-table td:nth-child(1) { animation-delay: 0.14s; }
    .event-table th:nth-child(2), .event-table td:nth-child(2) { animation-delay: 0.19s; }
    .event-table th:nth-child(3), .event-table td:nth-child(3) { animation-delay: 0.24s; }
    .event-table th:nth-child(4), .event-table td:nth-child(4) { animation-delay: 0.29s; }
    .event-table th:nth-child(5), .event-table td:nth-child(5) { animation-delay: 0.34s; }
    .event-table th:nth-child(n+6), .event-table td:nth-child(n+6) { animation-delay: 0.39s; }
    .event-table th{
        background: #f4f6fb;
        color: #322053;
        font-weight: 600;
        font-size: inherit;
        text-align: left;
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .event-table tr:nth-child(even){
        background: #f9fafe;
    }

    .create-event-btn{
        background: linear-gradient(90deg,#2d397a,#594285);
        color: #fff;
        padding: 7px 20px;
        border: none;
        border-radius: 7px;
        font-weight: 700;
        cursor: pointer;
        font-size: 16px;
        opacity: 0;
        animation: fadeInUpRowStaggerUser 0.39s 0.32s both;
        transition: background 0.15s;
    }
    .create-event-btn:hover, .edit-btn:hover {
        filter: brightness(1.13);
    }
    .edit-btn, .delete-btn {
        font-size: 16px;
        padding: 7px 14px;
        border-radius:5px;
        border:none;
        cursor:pointer;
        opacity: 0;
        animation: fadeInUpRowStaggerUser 0.33s 0.40s both;
        transition: filter 0.13s;
    }
    .edit-btn {
        background: #327ac5;
        color: #fff;
        margin-right: 4px;
    }
    .delete-btn {
        background: #e94242;
        color: #fff;
        margin-right: 0;
        animation-delay: 0.44s;
    }
    .table-edit-select {
        padding: 7px 13px;
        border-radius: 6px;
        border: 1px solid #ccc;
        font-size: inherit;
        min-width: 80px;
        opacity: 0;
        animation: fadeInUpRowStaggerUser 0.36s 0.36s both;
    }

    /* Responsive Header/Button row */
    .responsive-manage-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 0;
    }
    .responsive-manage-header h2 {
        font-size: 1.25em;
        margin: 0 0 8px 0;
    }
    @media (max-width: 700px) {
        .responsive-manage-header h2 {
            font-size: 1.1em;
        }
        .create-event-btn {
            font-size: 14px;
            padding: 7px 10px;
        }
    }
    @media (max-width: 480px) {
        .responsive-manage-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    /* Responsive Message for no users */
    .no-user-msg {
        font-size: 15px;
        padding: 6vw 2vw 6vw 2vw;
        text-align: center;
        color: #722a2a;
    }

    /* Scrollbar styling for table containers */
    .event-table-container {
        scrollbar-width: thin;
        scrollbar-color: #b3b3e7 #f4f6fb;
    }
    .event-table-container::-webkit-scrollbar {
        height: 8px;
        background: #f4f6fb;
    }
    .event-table-container::-webkit-scrollbar-thumb {
        background: #babdea;
        border-radius: 4px;
    }

</style>
<!-- <link rel="stylesheet" href="css/index.css"> -->
    <!-- <link rel="stylesheet" href="css/users.css"> -->

<script>
document.addEventListener('DOMContentLoaded', function(){

    // Dropdown Update handled via HTML forms (see HTML rendering below)
    document.querySelectorAll('.table-edit-select').forEach(function(el){
        el.addEventListener('change', function(){
            this.form.submit();
        });
    });

    /* Create User Button */
    let btn = document.querySelector('.create-event-btn');
    if(btn){
        btn.addEventListener('click', function(){
            window.location.href='user_create.php';
        });
    }

    /* Edit */
    document.querySelectorAll('.edit-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            let uid = this.closest('tr').dataset.uid;
            window.location.href='user_edit.php?user_id='+uid;
        });
    });

    /* Delete */
    document.querySelectorAll('.delete-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            let uid = this.closest('tr').dataset.uid;
            if(confirm('Delete this user?')){
                window.location.href='?delete_user_id='+uid;
            }
        });
    });

});
</script>
</head>
<body>

<div class="dashboard-main">

    <div class="responsive-manage-header">
        <h2>Manage Users</h2>
        <button class="create-event-btn">Create User</button>
    </div>

    <div class="event-table-container">

    <?php if($total_users==0): ?>
        <p class="no-user-msg">No users found.</p>
    <?php else: ?>

    <table class="event-table">
    <thead>
    <tr>
    <th>Sr No</th>
    <th>User ID</th>
    <th>Name</th>
    <th>Email</th>
    <th>Phone</th>
    <th>Address</th>
    <th>Status</th>
    <th>Type</th>
    <th>Registered At</th>
    <th>Last Login</th>
    <th>Action</th>
    </tr>
    </thead>

    <tbody>
    <?php 
    $s=$serial_start;
    foreach($paged_users as $u): ?>
    <tr data-uid="<?= (int)$u['user_id'] ?>">
    <td><?= $s++ ?></td>
    <td><?= esc($u['user_id']) ?></td>
    <td><?= esc($u['user_name']) ?></td>
    <td><?= esc($u['user_email']) ?></td>
    <td><?= esc($u['user_phone_number']) ?></td>
    <td><?= esc($u['user_address']) ?></td>

    <td>
        <form method="post" style="display:inline;">
            <input type="hidden" name="dropdown_update" value="1">
            <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
            <input type="hidden" name="col" value="user_status">
            <select class="table-edit-select" name="val">
                <option value="active" <?= $u['user_status']=='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $u['user_status']=='inactive'?'selected':'' ?>>Inactive</option>
            </select>
        </form>
    </td>

    <td>
        <form method="post" style="display:inline;">
            <input type="hidden" name="dropdown_update" value="1">
            <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
            <input type="hidden" name="col" value="user_type">
            <select class="table-edit-select" name="val">
                <option value="user" <?= $u['user_type']=='user'?'selected':'' ?>>User</option>
                <option value="owner" <?= $u['user_type']=='owner'?'selected':'' ?>>Owner</option>
                <option value="admin" <?= $u['user_type']=='admin'?'selected':'' ?>>Admin</option>
            </select>
        </form>
    </td>

    <td><?= esc($u['registered_at']) ?></td>
    <td><?= esc($u['last_login']) ?></td>

    <td>
    <button class="edit-btn">Edit</button>
    <button class="delete-btn">Delete</button>
    </td>

    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>

    <?php endif; ?>
    </div>
</div>

<?php
/* ===============================
   DELETE USER
=================================*/
/*
    The original code deleted the user's events before bookings, which can fail due to a foreign key constraint,
    if any bookings reference events (which must exist according to the booking table's constraint).
    To fix, delete bookings belonging to this user's events before deleting those events.
*/
if(isset($_GET['delete_user_id']) && is_numeric($_GET['delete_user_id'])){
    $del_id = intval($_GET['delete_user_id']);
    $conn->begin_transaction();

    $error_occurred = false;

    // 1. Get all event_ids owned by this user
    $event_ids = [];
    $res_evt = $conn->prepare("SELECT event_id FROM events WHERE owner_id=?");
    if($res_evt){
        $res_evt->bind_param("i", $del_id);
        if($res_evt->execute()){
            $result_evt = $res_evt->get_result();
            while($row_evt = $result_evt->fetch_assoc()){
                $event_ids[] = $row_evt['event_id'];
            }
            $result_evt->free();
        } else {
            $error_occurred = true;
        }
        $res_evt->close();
    } else {
        $error_occurred = true;
    }

    // 2. Delete bookings for those events (event_id IN ...)
    if(!$error_occurred && !empty($event_ids)){
        // Prepare list for binding
        $placeholders = implode(',', array_fill(0, count($event_ids), '?'));
        $types = str_repeat('i', count($event_ids));
        $stmt_bkgs = $conn->prepare("DELETE FROM bookings WHERE event_id IN ($placeholders)");
        if($stmt_bkgs){
            // bind_param by reference: build arg list
            $bind_names[] = $types;
            foreach ($event_ids as $k => $id) {
                $bind_name = 'bid' . $k;
                $$bind_name = (int)$id;
                $bind_names[] = &$$bind_name;
            }
            call_user_func_array([$stmt_bkgs, 'bind_param'], $bind_names);
            if(!$stmt_bkgs->execute()){
                $error_occurred = true;
            }
            $stmt_bkgs->close();
        } else {
            $error_occurred = true;
        }
    }

    // 3. Delete user's own event records
    if(!$error_occurred){
        $stmt1 = $conn->prepare("DELETE FROM events WHERE owner_id=?");
        if($stmt1){
            $stmt1->bind_param("i",$del_id);
            if(!$stmt1->execute()){
                $error_occurred = true;
            }
            $stmt1->close();
        } else {
            $error_occurred = true;
        }
    }

    // 4. Delete user's own bookings (they may have booked other events)
    if(!$error_occurred){
        $stmt2 = $conn->prepare("DELETE FROM bookings WHERE user_id=?");
        if($stmt2){
            $stmt2->bind_param("i",$del_id);
            if(!$stmt2->execute()){
                $error_occurred = true;
            }
            $stmt2->close();
        } else {
            $error_occurred = true;
        }
    }

    // 5. Delete user from users table
    if(!$error_occurred){
        $stmt3 = $conn->prepare("DELETE FROM users WHERE user_id=?");
        if($stmt3){
            $stmt3->bind_param("i",$del_id);
            if(!$stmt3->execute()){
                $error_occurred = true;
            }
            $stmt3->close();
        } else {
            $error_occurred = true;
        }
    }

    // Commit or rollback as needed
    if($error_occurred){
        $conn->rollback();
        echo "<script>alert('Error deleting user data. Please try again.');window.location.href=window.location.pathname;</script>";
        exit;
    } else {
        $conn->commit();
        echo "<script>window.location.href=window.location.pathname;</script>";
        exit;
    }
}
?>

</body>
</html>
