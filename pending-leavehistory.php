<?php
session_start();
require_once('include/config.php');

// Check if employee is logged in
if (!isset($_SESSION['eid'])) {
    header('location: index.php');
    exit();
}

$empid = $_SESSION['eid'];
$error = '';
$success = '';
$leaves = array();
$employee = array();

try {
    // Fetch employee details for sidebar
    $sql = "SELECT FirstName, LastName, EmpId FROM tblemployees WHERE id = :empid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':empid', $empid, PDO::PARAM_INT);
    $query->execute();
    $employee = $query->fetch(PDO::FETCH_ASSOC);

    // Fetch pending leaves
    $sql = "SELECT l.*, lt.LeaveType 
            FROM tblleaves l
            LEFT JOIN tblleavetype lt ON l.LeaveTypeID = lt.id
            WHERE l.empid = :empid AND l.Status = 0
            ORDER BY l.PostingDate DESC";
    
    $query = $dbh->prepare($sql);
    $query->bindParam(':empid', $empid, PDO::PARAM_INT);
    $query->execute();
    $leaves = $query->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Get messages from session if any
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EMPFLOW | pending leave history</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts & Material Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f5fafa;
      margin: 0;
      padding: 0;
    }

    .navbar {
      background-color: #48A6A7;
      height: 64px;
      z-index: 1001;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1),
                  0 6px 15px rgba(0, 0, 0, 0.1);
    }

    .navbar .navbar-brand {
      font-size: 22px;
      color: #fff;
      font-weight: 600;
    }

    .hamburger {
      border: none;
      background: none;
      font-size: 28px;
      color: white;
    }

    #sidebar {
      position: fixed;
      top: 64px;
      left: 0;
      width: 240px;
      height: calc(100% - 64px);
      background: #ffffff;
      border-right: 1px solid #e0e0e0;
      box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
      transition: transform 0.3s ease;
      z-index: 1000;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    #sidebar.collapsed {
      transform: translateX(-100%);
    }

    .sidebar-content {
      overflow-y: auto;
      flex-grow: 1;
      padding-top: 10px;
    }

    #sidebar .material-icons {
      margin-right: 10px;
      font-size: 20px;
    }

    #sidebar hr {
      border-color: #e0e0e0;
    }

    #sidebar a,
    #sidebar button.sidebar-btn {
      display: flex;
      align-items: center;
      width: 100%;
      padding: 12px 20px;
      color: #333;
      text-decoration: none;
      font-weight: 500;
      background: transparent;
      border: none;
      text-align: left;
      transition: background 0.3s ease;
    }

    #sidebar a:hover,
    #sidebar button.sidebar-btn:hover {
      background-color: #e6fafa;
      color: #000;
    }

    #sidebar .collapse a {
      font-weight: 400;
      padding-left: 36px;
      color: #555;
    }

    #sidebar .collapse a:hover {
      background-color: #f0fdfd;
    }

    #sidebar .material-icons.float-end {
      margin-left: auto;
    }

    @media (max-width: 768px) {
      #sidebar {
        transform: translateX(-100%);
      }

      #sidebar.collapsed {
        transform: translateX(0);
      }

      .main-content {
        margin-left: 0 !important;
      }
    }

    .main-content {
      margin-left: 240px;
      padding: 90px 40px 40px 40px;
      transition: margin-left 0.3s ease;
      background: linear-gradient(135deg, #f9fefe, #f0fdfd);
      min-height: 100vh;
    }

    .main-content.collapsed {
      margin-left: 0;
    }

    .page-title {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #2c7a7b;
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 30px;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .card {
      border: none;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.07);
      border-radius: 20px;
      background: #ffffff;
      transition: box-shadow 0.2s ease;
    }

    .card:hover {
      /* Removed translateY to prevent table shift on hover */
      box-shadow: 0 12px 35px rgba(0, 0, 0, 0.08);
    }

    .card-title {
      color: #333;
      font-size: 20px;
    }

    .search-wrapper {
      position: relative;
      max-width: 300px;
      margin-bottom: 16px;
    }

    .search-wrapper .material-icons {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #aaa;
    }

    .search-box {
      padding-left: 40px;
      border-radius: 12px;
      border: 1px solid #ccc;
      transition: border-color 0.3s, box-shadow 0.3s;
      background-color: #fdfdfd;
    }

    .search-box:focus {
      border-color: #48A6A7;
      box-shadow: 0 0 0 0.2rem rgba(72, 166, 167, 0.25);
    }

    .table thead {
      background-color: #e0f7f7;
      font-weight: 600;
    }

    .table tbody tr:hover {
      background-color: #f1fefe;
      transition: background 0.2s ease;
    }

    .badge {
      padding: 6px 10px;
      font-size: 13px;
      border-radius: 12px;
    }

    .btn-outline-primary {
      border-radius: 10px;
      font-size: 14px;
      transition: all 0.2s ease;
    }

    .btn-outline-primary:hover {
      background-color: #48A6A7;
      color: white;
      border-color: #48A6A7;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container-fluid">
    <button class="hamburger" id="menu-toggle"><span class="material-icons">menu</span></button>
    <a class="navbar-brand ms-2" href="#">EMPFLOW</a>
  </div>
</nav>

<!-- Sidebar -->
<div id="sidebar">
  <div class="sidebar-content">
    <div class="text-center py-4">
      <img src="assets/images/profile-image.png" class="rounded-circle mb-2" width="80" alt="Profile Image">
      <h6 class="mb-0" style="font-weight:600;"><?php echo htmlspecialchars($employee['FirstName'] . ' ' . $employee['LastName']); ?></h6>
      <small class="text-muted"><?php echo htmlspecialchars($employee['EmpId']); ?></small>
    </div>
    <hr class="mx-3">

    <a href="dashboard.php"><span class="material-icons">dashboard</span> Dashboard</a>
    <a href="myprofile.php"><span class="material-icons">account_circle</span> My Profile</a>
    <a href="emp-changepassword.php"><span class="material-icons">lock</span> Change Password</a>

    <button class="sidebar-btn" type="button" data-bs-toggle="collapse" data-bs-target="#leaveMenu" aria-expanded="false" aria-controls="leaveMenu">
      <span class="material-icons">event_note</span> Leaves
      <span class="material-icons float-end">expand_more</span>
    </button>
    <div class="collapse ps-4" id="leaveMenu">
      <a href="apply-leave.php" class="d-block py-2">Apply Leave</a>
      <a href="leavehistory.php" class="d-block py-2">Leave History</a>
    </div>

    <a href="attendance.php"><span class="material-icons">access_time</span> Attendance</a>

    <a href="logout.php"><span class="material-icons">logout</span> Sign Out</a>
  </div>
</div>


<!-- Main Content -->
<div class="main-content" id="main-content">
  <div class="page-title">
    <span class="material-icons">event_note</span> Pending Leave History
  </div>

  <!-- Leave History Card -->
  <div class="card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
      <h5 class="card-title fw-bold mb-3" style="color: #333;">Leave Records</h5>

      <!-- Search Input -->
      <div class="search-wrapper">
        <span class="material-icons">search</span>
        <input type="text" id="searchInput" class="form-control search-box" placeholder="Search...">
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle" id="leaveTable">
        <thead>
          <tr class="text-secondary">
            <th>#</th>
            <th>Leave Type</th>
            <th>From</th>
            <th>To</th>
            <th>Posting Date</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          if (!empty($leaves)) {
              $cnt = 1;
              foreach($leaves as $leave) {
                  echo '<tr>';
                  echo '<td>' . $cnt . '</td>';
                  echo '<td>' . htmlentities($leave['LeaveType']) . '</td>';
                  echo '<td>' . htmlentities(date('Y-m-d', strtotime($leave['FromDate']))) . '</td>';
                  echo '<td>' . htmlentities(date('Y-m-d', strtotime($leave['ToDate']))) . '</td>';
                  echo '<td>' . htmlentities(date('Y-m-d H:i:s', strtotime($leave['PostingDate']))) . '</td>';
                  echo '<td><span class="badge bg-warning text-dark">Pending</span></td>';
                  echo '<td><a href="leave-details.php?leaveid=' . htmlentities($leave['id']) . '" class="btn btn-sm btn-outline-primary">View</a></td>';
                  echo '</tr>';
                  $cnt++;
              }
          } else {
              echo '<tr><td colspan="7" class="text-center">No pending leave applications found</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Sidebar Toggle -->
<script>
  const toggleBtn = document.getElementById('menu-toggle');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('main-content');

  toggleBtn?.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('collapsed');
  });
</script>

<!-- Search Filter Script -->
<script>
  const searchInput = document.getElementById('searchInput');
  const table = document.getElementById('leaveTable');
  searchInput.addEventListener('keyup', function () {
    const filter = searchInput.value.toLowerCase();
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(filter) ? '' : 'none';
    });
  });
</script>

</body>
</html>
