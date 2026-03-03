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
<!-- <link rel="stylesheet" href="css/categories.css"> -->
<style>
    /* --- Classic Pagination Style --- */

/* Animation for staggered appearance (one by one, fade-in-up) */
@keyframes fadeInUpRowStagger {
  from {
    opacity: 0;
    transform: translateY(18px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Animation for cells, slight scale and fade for more effect */
@keyframes fadeInCellStagger {
  from {
    opacity: 0;
    transform: scale(0.96) translateY(10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.classic-pagination {
  margin: 20px 0 0 0;
  text-align: center;
  /* make sure pagination is hidden at first and then animates in */
  opacity: 0;
  animation: fadeInUpRowStagger 0.5s 0.13s both;
}
.classic-pagination ul {
  display: inline-block;
  padding: 0;
  margin: 0;
  border: 1px solid #bbb;
  border-radius: 4px;
  background: #fafafa;
}
.classic-pagination li {
  display: inline;
  /* Animate each li delayed by index using CSS selectors */
  opacity: 0;
  animation: fadeInUpRowStagger 0.42s both;
}
.classic-pagination li:nth-child(1) { animation-delay: 0.07s; }
.classic-pagination li:nth-child(2) { animation-delay: 0.14s; }
.classic-pagination li:nth-child(3) { animation-delay: 0.21s; }
.classic-pagination li:nth-child(4) { animation-delay: 0.28s; }
.classic-pagination li:nth-child(5) { animation-delay: 0.35s; }
.classic-pagination li:nth-child(6) { animation-delay: 0.42s; }
.classic-pagination li:nth-child(7) { animation-delay: 0.49s; }
.classic-pagination li:nth-child(8) { animation-delay: 0.56s; }
.classic-pagination li:nth-child(9) { animation-delay: 0.63s; }
.classic-pagination li:nth-child(10) { animation-delay: 0.7s; }
.classic-pagination li:nth-child(n+11) { animation-delay: 0.75s; }
.classic-pagination a,
.classic-pagination span {
  color: #222;
  float: left;
  padding: 6px 16px;
  text-decoration: none;
  background: none;
  border-right: 1px solid #ddd;
  font-size: 15px;
  line-height: 24px;
  min-width: 30px;
  box-sizing: border-box;
  border-radius: 0;
  transition: background 0.13s;
  opacity: 0;
  animation: fadeInCellStagger 0.29s both;
}
/* Stagger a and span animation inside the li for one by one effect */
.classic-pagination li:nth-child(1) a,
.classic-pagination li:nth-child(1) span { animation-delay: 0.13s; }
.classic-pagination li:nth-child(2) a,
.classic-pagination li:nth-child(2) span { animation-delay: 0.21s; }
.classic-pagination li:nth-child(3) a,
.classic-pagination li:nth-child(3) span { animation-delay: 0.29s; }
.classic-pagination li:nth-child(4) a,
.classic-pagination li:nth-child(4) span { animation-delay: 0.37s; }
.classic-pagination li:nth-child(5) a,
.classic-pagination li:nth-child(5) span { animation-delay: 0.45s; }
.classic-pagination li:nth-child(6) a,
.classic-pagination li:nth-child(6) span { animation-delay: 0.53s; }
.classic-pagination li:nth-child(7) a,
.classic-pagination li:nth-child(7) span { animation-delay: 0.61s; }
.classic-pagination li:nth-child(8) a,
.classic-pagination li:nth-child(8) span { animation-delay: 0.69s; }
.classic-pagination li:nth-child(9) a,
.classic-pagination li:nth-child(9) span { animation-delay: 0.76s; }
.classic-pagination li:nth-child(10) a,
.classic-pagination li:nth-child(10) span { animation-delay: 0.83s; }
.classic-pagination li:nth-child(n+11) a,
.classic-pagination li:nth-child(n+11) span { animation-delay: 0.90s; }
.classic-pagination li:last-child a,
.classic-pagination li:last-child span {
  border-right: 0;
}
.classic-pagination a:hover {
  background: #e9e9e9;
  color: #111;
}
.classic-pagination .active,
.classic-pagination .active:hover,
.classic-pagination .active:focus {
  background: #f1f1f1;
  font-weight: 700;
  color: #184090;
  cursor: default;
}
.classic-pagination .disabled,
.classic-pagination .disabled:hover {
  background: none !important;
  color: #bbb !important;
  cursor: default;
  pointer-events: none;
}

/* --- FROM events.css (181-233), adapted for this table --- */
.paging-bar {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-top: 28px;
  margin-bottom: 10px;
  gap: 6px;
  opacity: 0;
  animation: fadeInUpRowStagger 0.5s 0.18s both;
}
.paging-bar a,
.paging-bar span {
  display: inline-block;
  padding: 7px 16px;
  margin: 0 0.5px;
  border-radius: 7px;
  font-size: 16px;
  text-decoration: none;
  color: #423074;
  background: #f5f4fb;
  font-weight: 600;
  border: 1.3px solid #ece9f7;
  transition: 0.09s;
  min-width: 35px;
  text-align: center;
  opacity: 0;
  animation: fadeInCellStagger 0.32s both;
}
.paging-bar a:nth-child(1),
.paging-bar span:nth-child(1) { animation-delay: 0.11s; }
.paging-bar a:nth-child(2),
.paging-bar span:nth-child(2) { animation-delay: 0.18s; }
.paging-bar a:nth-child(3),
.paging-bar span:nth-child(3) { animation-delay: 0.25s; }
.paging-bar a:nth-child(4),
.paging-bar span:nth-child(4) { animation-delay: 0.32s; }
.paging-bar a:nth-child(5),
.paging-bar span:nth-child(5) { animation-delay: 0.39s; }
.paging-bar a:nth-child(6),
.paging-bar span:nth-child(6) { animation-delay: 0.46s; }
.paging-bar a:nth-child(7),
.paging-bar span:nth-child(7) { animation-delay: 0.53s; }
.paging-bar a:nth-child(8),
.paging-bar span:nth-child(8) { animation-delay: 0.60s; }
.paging-bar a:nth-child(9),
.paging-bar span:nth-child(9) { animation-delay: 0.67s; }
.paging-bar a:nth-child(10),
.paging-bar span:nth-child(10) { animation-delay: 0.74s; }
.paging-bar a:nth-child(n+11),
.paging-bar span:nth-child(n+11) { animation-delay: 0.80s; }
.paging-bar a:hover {
  background: linear-gradient(90deg, #4c53e2 0, #5239d5 97%);
  color: #fff;
  border-color: #4c53e2;
}
.paging-bar .active,
.paging-bar span.active {
  color: #fff;
  background: linear-gradient(90deg, #4c53e2 0, #5239d5 97%);
  border-color: #4c53e2;
  pointer-events: none;
}
.paging-bar .arrow {
  padding: 7px 13px;
  font-size: 17px;
  background: none;
  color: #626092;
  border: 1px solid #ece9f7;
  opacity: 0;
  animation: fadeInCellStagger 0.32s both;
}
.paging-bar .arrow:nth-child(1) { animation-delay: 0.01s; }
.paging-bar .arrow:nth-child(2) { animation-delay: 0.05s; }
.paging-bar .arrow.disabled {
  opacity: 0.39;
  pointer-events: none;
}
/* --- END: Staggered animation for pagination bars --- */

body {
  overflow-x: hidden;
}
.categories-container {
  max-width: 1200px;
  margin: 40px auto 0;
  background: #f8fafd;
  border-radius: 14px;
  box-shadow: 0 1px 18px rgba(80, 80, 130, 0.13);
  padding: 26px 32px 40px 32px;
  /* Container animation */
  opacity: 0;
  animation: fadeInUpRowStagger 0.6s 0.11s both;
}
.categories-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
  /* Stagger */
  opacity: 0;
  animation: fadeInUpRowStagger 0.45s 0.16s both;
}
.categories-head {
  font-size: 2rem;
  /* font-weight: 700; */
  color: #312053;
  margin: 0;
  opacity: 0;
  animation: fadeInUpRowStagger 0.55s 0.21s both;
}
.add-cat-btn {
  background: linear-gradient(90deg, #2d397a, #594285 90%);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 700;
  padding: 10px 27px;
  cursor: pointer;
  letter-spacing: 0.03em;
  box-shadow: 0 2px 9px rgb(82 58 213 / 8%);
  opacity: 0;
  animation: fadeInUpRowStagger 0.45s 0.29s both;
}
.add-cat-btn:hover {
  background: linear-gradient(90deg, #594285, #2d397a 100%);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 700;
  padding: 10px 27px;
  cursor: pointer;
  letter-spacing: 0.03em;
  box-shadow: 0 2px 9px rgb(82 58 213 / 8%);
  opacity: 0;
  animation: fadeInUpRowStagger 0.45s 0.29s both;
}
table.category-table {
  border-collapse: collapse;
  min-width: 900px;
  width: 100%;
  margin-top: 8px;
  opacity: 0;
  animation: fadeInUpRowStagger 0.52s 0.21s both;
}
.category-table th,
.category-table td {
  padding: 12px 14px;
  text-align: left;
  border-bottom: 1px solid #e6e7f0;
  font-size: 15px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 360px;
  vertical-align: middle;
  opacity: 0;
  animation: fadeInCellStagger 0.34s both;
}
.category-table th { /* Headings, staggered left-to-right */
  background: #f4f6fb;
  color: #322053;
  font-weight: 600;
  border-top: 1px solid #e6e7f0;
}
.category-table th:nth-child(1) { animation-delay: 0.11s; }
.category-table th:nth-child(2) { animation-delay: 0.17s; }
.category-table th:nth-child(3) { animation-delay: 0.23s; }
.category-table th:nth-child(4) { animation-delay: 0.29s; }
.category-table th:nth-child(5) { animation-delay: 0.35s; }
.category-table th:nth-child(n+6) { animation-delay: 0.38s; }
.category-table td:nth-child(1) { animation-delay: 0.16s; }
.category-table td:nth-child(2) { animation-delay: 0.22s; }
.category-table td:nth-child(3) { animation-delay: 0.28s; }
.category-table td:nth-child(4) { animation-delay: 0.34s; }
.category-table td:nth-child(5) { animation-delay: 0.40s; }
.category-table td:nth-child(n+6) { animation-delay: 0.44s; }
.cat-edit-btn,
.cat-del-btn {
  border: none;
  border-radius: 5px;
  padding: 7px 16px;
  cursor: pointer;
  font-weight: 600;
  font-size: 15px;
  opacity: 0;
  animation: fadeInUpRowStagger 0.35s 0.16s both;
}
.cat-edit-btn {
  background: linear-gradient(90deg, #327ac5 20%, #225085 80%);
  color: #fff;
  margin-right: 8px;
}
.cat-edit-btn:hover {
  background: linear-gradient(90deg, #225085, #327ac5 60%);
  color: #fff;
  margin-right: 8px;
}
.cat-del-btn {
  background: linear-gradient(90deg, #e94242 20%, #b02626 80%);
  color: #fff;
}
.cat-del-btn:hover {
  background: linear-gradient(90deg, #a51818, #e94242 60%);
  color: #fff;
}
.pop-overlay {
  display: none;
  position: fixed;
  z-index: 3999;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(20, 25, 51, 0.27);
  align-items: center;
  justify-content: center;
  opacity: 0;
  animation: fadeInUpRowStagger 0.5s 0.12s both;
}
.pop-overlay.open {
  display: flex;
  opacity: 1;
}
.pop-modal {
  background: #fff;
  border-radius: 15px;
  box-shadow: 0 2px 18px rgba(50, 80, 130, 0.17);
  max-width: 410px;
  width: 100%;
  padding: 34px 28px 30px 28px;
  position: relative;
  opacity: 0;
  animation: fadeInUpRowStagger 0.38s 0.13s both;
}
.modal-close {
  position: absolute;
  top: 10px;
  right: 16px;
  background: none;
  border: none;
  font-size: 1.45rem;
  color: #7066c8;
  cursor: pointer;
  opacity: 0;
  animation: fadeInCellStagger 0.28s 0.18s both;
}
.form-group {
  margin-bottom: 19px;
  opacity: 0;
  animation: fadeInCellStagger 0.24s both;
}
.form-group:nth-child(1) { animation-delay: 0.08s; }
.form-group:nth-child(2) { animation-delay: 0.13s; }
.form-group:nth-child(3) { animation-delay: 0.19s; }
.form-group:nth-child(4) { animation-delay: 0.25s; }
.form-group:nth-child(n+5) { animation-delay: 0.3s; }
.form-group label {
  display: block;
  font-size: 1.02em;
  font-weight: 600;
  margin-bottom: 5px;
  color: #2e2252;
  opacity: 0;
  animation: fadeInCellStagger 0.18s 0.09s both;
}
.form-group input {
  padding: 8px 13px;
  border: 1.2px solid #dcddf7;
  border-radius: 6px;
  font-size: 15px;
  width: 100%;
  box-sizing: border-box;
  opacity: 0;
  animation: fadeInCellStagger 0.17s 0.15s both;
}
.form-group input.error {
  border-color: #dc2323;
  background: #fff2f2;
}
.form-feedback {
  color: #b52b34;
  min-height: 21px;
  margin-bottom: 12px;
  opacity: 0;
  animation: fadeInCellStagger 0.21s 0.21s both;
}
.success-msg-modal {
  color: #247e40;
  font-weight: 600;
  font-size: 1.07em;
  opacity: 0;
  animation: fadeInUpRowStagger 0.23s 0.22s both;
}
.cat-modal-submit {
  background: linear-gradient(90deg, #4c53e2 30%, #5239d5 92%);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 700;
  padding: 10px 24px;
  cursor: pointer;
  opacity: 0;
  animation: fadeInUpRowStagger 0.37s 0.27s both;
}

/* End animation for staggered appearance */


/* Responsive Styles Added (in addition to existing styles) */
@media (max-width: 1050px) {
  .categories-container {
    padding: 20px 10px 28px 10px;
    min-width: 0;
  }
}
@media (max-width: 900px) {
  .categories-header-row {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }
  .categories-head {
    font-size: 1.25rem;
    margin-bottom: 6px;
  }
  .add-cat-btn {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 0;
  }
  table.category-table, .category-table th, .category-table td {
    min-width: 0;
    max-width: none;
    white-space: normal;
    font-size: 15px;
  }
}
@media (max-width: 720px) {
    .categories-container {
        max-width: 100vw;
        box-shadow: none;
        border-radius: 5px;
        margin: 11px 0 0 0;
        padding: 5px 0 16px 0;
    }
    .categories-header-row {
        flex-direction: column;
        gap: 7px;
        margin-bottom: 9px;
        align-items: stretch;
    }
    .categories-head {
      font-size: 1.12rem;
      margin: 0;
      text-align: left;
    }
    .add-cat-btn {
      font-size: 15px;
      padding: 8px 0;
      width: 100%;
      min-width: unset;
    }
    .classic-pagination ul {
      font-size: 13px;
      padding: 0;
      width: 99vw;
      min-width: unset;
    }
    .pop-modal {
      max-width: 99vw;
      width: 98vw;
      padding: 18px 4vw 16px 4vw;
      min-width: 0;
    }
}

/* Mobile table: stack fields vertically, no horizontal scroll */
@media (max-width: 600px) {
  .categories-container {
    padding: 0;
  }
  .category-table, .category-table thead, .category-table tbody, .category-table th, .category-table td, .category-table tr {
    display: block !important;
    width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box;
    border-radius: 0 !important;
  }
  .category-table {
    border: none;
    margin-top: 3px;
    background: transparent;
    box-shadow: none;
  }
  .category-table thead {
    display: none !important;
  }
  .category-table tr {
    background: #f9f8ff;
    margin-bottom: 13px;
    border-radius: 9px;
    box-shadow: 0 0.5px 5px #e9e8fa;
    border: 1.5px solid #ededfb;
    display: block;
    padding: 9px 14px 9px 9px;
    opacity: 1 !important; /* Animation may be strange, so force visible */
    animation: none !important;
  }
  .category-table td {
    padding: 7px 12px 7px 7px;
    border-bottom: none;
    border: none;
    max-width: 100vw !important;
    white-space: normal !important;
    overflow-wrap: break-word;
    opacity: 1 !important;
    animation: none !important;
    background: none;
    position: relative;
    font-size: 14.25px;
    width: auto;
  }
  .category-table td::before {
    content: attr(data-label);
    font-weight: 700;
    float: left;
    width: 54%;
    color: #554995;
    display: inline-block;
    margin-bottom: 2px;
  }
  .category-table td:last-child {
    padding-bottom: 8px;
  }
  .cat-edit-btn, .cat-del-btn {
    display: inline-block;
    margin-top: 7px;
    font-size: 14px !important;
    padding: 7px 10px !important;
    width: 48%;
    min-width: 0;
  }
  .cat-edit-btn { margin-right: 0 !important; }
  .pop-modal {
    max-width: 100vw !important;
    border-radius: 7px !important;
    padding: 17px 3vw 11px 3vw !important;
  }
  .modal-close {
    top: 7px !important;
    right: 8px !important;
    font-size: 1.4rem !important;
  }
}

/* Form fields adapt for mobile */
@media (max-width: 600px){
  .form-group label {
    font-size: 0.98em !important;
  }
  .form-group input {
    font-size: 14px !important;
    padding: 7px 8px !important;
  }
}

/* Responsive: container for horizontal scrolling at xs/sm screens */
@media (max-width: 600px) {
  .categories-container {
    overflow-x: visible !important;
  }
}

/* Additional improvements for usability */
@media (max-width: 450px) {
  .pop-modal {
    padding: 10px 2vw 10px 2vw !important;
  }
  .form-group input {
    font-size: 13px !important;
    padding: 7px 3px !important;
  }
}

/* Remove the min-width:900px for table on all viewports - rely on overflow-x for desktop, stacking for mobile */
table.category-table {
    min-width: 0 !important;
}

/* End custom responsive styles */

/* --- Classic Pagination Style --- */

/* Animation for staggered appearance (one by one, fade-in-up) */
@keyframes fadeInUpRowStagger {
  from {
    opacity: 0;
    transform: translateY(18px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Animation for cells, slight scale and fade for more effect */
@keyframes fadeInCellStagger {
  from {
    opacity: 0;
    transform: scale(0.96) translateY(10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

</style>

</head>
<body>
<div class="categories-container">
    <div class="categories-header-row">
        <h2 class="categories-head">Manage Categories</h2>
        <button class="add-cat-btn" id="openModalBtn">+ Add Category</button>
    </div>
    <div class="category-table-wrapper" style="overflow-x: auto;">
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
                    <td data-label="Sr No."><?= $sr++ ?></td>
                    <td data-label="ID"><?= esc($cat['category_id']) ?></td>
                    <td data-label="Category Name"><?= esc($cat['category_name']) ?></td>
                    <td data-label="Seats Capacity"><?= esc($cat['category_seats']) ?></td>
                    <td data-label="Price per Hour">₹<?= esc($cat['category_price_per_hour']) ?></td>
                    <td data-label="Actions">
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
            <tr><td colspan="6" style="text-align:center;color:#8f84c3;font-style:italic;" data-label="No data">No categories found</td></tr>
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
