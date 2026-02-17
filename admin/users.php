<?php
session_start();
require_once('sidebar.php');



require_once('../database/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dropdown_update'])) {
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
<link rel="stylesheet" href="css/index.css">
<link rel="stylesheet" href="css/users.css">



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

<div style="display:flex;justify-content:space-between;align-items:center;">
    <h2 style="font-size:1.45em; margin-bottom: 0;">Manage Users</h2>
    <button class="create-event-btn">Create User</button>
</div>

<div class="event-table-container">

<?php if($total_users==0): ?>
    <p style="font-size: 15px;">No users found.</p>
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
if(isset($_GET['delete_user_id']) && is_numeric($_GET['delete_user_id'])){
    $del_id = intval($_GET['delete_user_id']);
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id=?");
    if($stmt){
        $stmt->bind_param("i",$del_id);
        $stmt->execute();
        $stmt->close();
        echo "<script>window.location.href=window.location.pathname;</script>";
        exit;
    }
}
?>

</body>
</html>
