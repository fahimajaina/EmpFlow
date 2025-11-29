<?php
session_start();
require_once('includes/config.php');

// Check if admin is logged in
if (!isset($_SESSION['alogin'])) {
    header('location:index.php');
    exit();
}

$error = '';
$success = '';

// Handle leave type deletion
if (isset($_GET['del'])) {
    try {
        $id = intval($_GET['del']);
        
        // Check if leave type is used in any leaves
        $checkSql = "SELECT COUNT(*) FROM tblleaves WHERE LeaveTypeID = :id";
        $checkStmt = $dbh->prepare($checkSql);
        $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->fetchColumn() > 0) {
            $error = "Cannot delete this leave type as it is being used by employees";
        } else {
            // Safe to delete
            $sql = "DELETE FROM tblleavetype WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success = "Leave type deleted successfully";
            } else {
                $error = "Error deleting leave type";
            }
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

// Fetch all leave types
try {
    $sql = "SELECT * FROM tblleavetype ORDER BY LeaveType ASC";
    $query = $dbh->prepare($sql);
    $query->execute();
    $leaveTypes = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching leave types: " . $e->getMessage();
    $leaveTypes = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin | Manage Leave-Type</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #eef9fa; /* Updated background */
      color: #333;
      margin: 0;
    }

    .navbar {
      background-color: #71C9CE;
      height: 64px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1),
                  0 6px 15px rgba(0, 0, 0, 0.1);
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1050;
      padding: 0 20px;
      display: flex;
      align-items: center;
    }

    .navbar-brand {
      font-size: 22px;
      font-weight: 600;
      color: #fff;
      margin-left: 10px;
    }

    .hamburger {
      border: none;
      background: none;
      font-size: 28px;
      color: white;
      cursor: pointer;
    }
    #sidebar {
      position: fixed;
      top: 64px;
      left: 0;
      width: 240px;
      height: calc(100% - 64px);
      background-color: #fff;
      padding: 1rem;
      z-index: 999;
      transition: transform 0.3s ease;
      overflow-y: auto;
    }

    #sidebar.collapsed {
      transform: translateX(-240px);
    }

    .sidebar-header {
      text-align: center;
      margin-bottom: 1.5rem;
    }

    .sidebar-header img {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      border: 3px solid #71C9CE;
    }

    .sidebar-header p {
      font-weight: 600;
      color: #3D90D7;
      margin-top: 10px;
    }

    .list-group-item {
      display: flex;
      align-items: center;
      gap: 12px; /* space between icon and text */
      padding: 10px 15px;
      font-size: 15px;
      font-weight: 500;
      color: #333;
      border-radius: 8px;
      margin-bottom: 10px;
      transition: all 0.2s ease-in-out;
      border: none; /* Remove border */
    }

    .list-group-item span.material-icons {
      font-size: 20px;
    }

    .list-group-item:hover {
      background-color: #e6fafa;
      color: #000;
      text-decoration: none;
    }

    #sidebar .collapse .list-group-item {
      padding-left: 40px; /* indent submenu items */
      font-size: 14px;
    }

    #sidebar .collapse .list-group-item:hover {
      background-color: #f0fbfd;
      color: #344C64;
    }

    .main-content {
      margin-left: 260px;
      padding: 100px 30px 30px 30px;
      transition: margin-left 0.3s ease;
    }

    .main-content.collapsed {
      margin-left: 0;
    }

    .card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 0 12px rgba(0, 0, 0, 0.05);
    }

    .card h4 {
      color: #344C64;
    }

    .search-input {
      border-radius: 50rem;
    }

    .table thead th {
      background-color: #e9f8fa;
      color: #344C64;
      font-weight: 600;
      border: none;
    }

    .table tbody tr:hover {
      background-color: #f0fbfd;
    }

    .table td, .table th {
      vertical-align: middle;
    }

    .btn-action {
      border-radius: 20px;
      padding: 6px 12px;
      font-size: 14px;
      font-weight: 500;
    }

    .btn-view {
      background-color: transparent;
      border: 1px solid #7AC6D2;
      color: #7AC6D2;
    }    .btn-view:hover {
      background-color: #7AC6D2;
      color: white;
    }

    /* Custom Alert Styling */
    .custom-alert {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem;
      margin-bottom: 1rem;
      border-radius: 8px;
      font-weight: 500;
      animation: slideIn 0.3s ease-out;
    }

    .alert-content {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .alert-icon {
      font-size: 24px;
    }

    .alert-text {
      font-size: 15px;
    }

    .alert-close {
      background: none;
      border: none;
      padding: 0;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      transition: background-color 0.2s;
    }

    .alert-danger {
      background-color: #ffe3e3;
      color: #dc3545;
    }

    .alert-success {
      background-color: #e0f5f4;
      color: #48A6A7;
    }

    .alert-danger .alert-close {
      color: #dc3545;
    }

    .alert-success .alert-close {
      color: #48A6A7;
    }

    .alert-close:hover {
      background-color: rgba(0, 0, 0, 0.1);
    }

    @keyframes slideIn {
      from {
        transform: translateY(-20px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    @media (max-width: 768px) {
      #sidebar {
        transform: translateX(-100%);
      }

      #sidebar.collapsed {
        transform: translateX(0);
      }

      .main-content {
        margin-left: 0;
        padding-top: 100px;
      }
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
  <div class="sidebar-header">
    <img src="../assets/images/profile-image.png" alt="Profile">
    <p>Admin</p>
  </div>

  <div class="list-group" id="sidebarMenu">
    <a href="dashboard.php" class="list-group-item list-group-item-action d-flex align-items-center">
      <span class="material-icons">dashboard</span> Dashboard
    </a>

    <!-- Department -->
    <a class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="collapse" href="#deptMenu" role="button" aria-expanded="false" aria-controls="deptMenu">
      <span class="material-icons">apartment</span> Department
      <span class="ms-auto">›</span>
    </a>
    <div class="collapse" id="deptMenu">
      <a href="adddepartment.php" class="list-group-item list-group-item-action">Add Department</a>
      <a href="managedepartments.php" class="list-group-item list-group-item-action">Manage Department</a>
    </div>

    <!-- Leave Type -->
    <a class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="collapse" href="#leaveTypeMenu" role="button" aria-expanded="false" aria-controls="leaveTypeMenu">
      <span class="material-icons">event_note</span> Leave Type
      <span class="ms-auto">›</span>
    </a>
    <div class="collapse" id="leaveTypeMenu">
      <a href="addleavetype.php" class="list-group-item list-group-item-action">Add Leave Type</a>
      <a href="manageleavetype.php" class="list-group-item list-group-item-action">Manage Leave Type</a>
    </div>

    <!-- Employees -->
    <a class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="collapse" href="#employeeMenu" role="button" aria-expanded="false" aria-controls="employeeMenu">
      <span class="material-icons">people</span> Employees
      <span class="ms-auto">›</span>
    </a>
    <div class="collapse" id="employeeMenu">
      <a href="addemployee.php" class="list-group-item list-group-item-action">Add Employee</a>
      <a href="manageemployee.php" class="list-group-item list-group-item-action">Manage Employee</a>
    </div>

    <!-- attendance -->
    <a class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="collapse" href="#attendanceMenu" role="button" aria-expanded="false" aria-controls="attendanceMenu">
      <span class="material-icons">access_time</span> Attendance
      <span class="ms-auto">›</span>
    </a>
    <div class="collapse" id="attendanceMenu">
      <a href="manage-attendance.php" class="list-group-item list-group-item-action">Manage Attendance</a>
      <a href="attendance-settings.php" class="list-group-item list-group-item-action">Attendance Settings</a>
    </div>

    <!-- Leave Management -->
    <a class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="collapse" href="#leaveMgmtMenu" role="button" aria-expanded="false" aria-controls="leaveMgmtMenu">
      <span class="material-icons">assignment</span> Leave Management
      <span class="ms-auto">›</span>
    </a>
    <div class="collapse" id="leaveMgmtMenu">
      <a href="leaves.php" class="list-group-item list-group-item-action">All Leaves</a>
      <a href="pending-leavehistory.php" class="list-group-item list-group-item-action">Pending Leaves</a>
      <a href="approvedleave-history.php" class="list-group-item list-group-item-action">Approved Leaves</a>
      <a href="notapproved-leaves.php" class="list-group-item list-group-item-action">Not Approved Leaves</a>
    </div>

    <!-- Other Links -->
    <a href="changepassword.php" class="list-group-item list-group-item-action d-flex align-items-center">
      <span class="material-icons">lock</span> Change Password
    </a>
    <a href="logout.php" class="list-group-item list-group-item-action d-flex align-items-center">
      <span class="material-icons">logout</span> Sign Out
    </a>
  </div>
</div>

<!-- Main Content -->
<div class="main-content" id="mainContent">
  <h2 class="fw-bold mb-4" style="color: #344C64;">
    <i class="material-icons me-2" style="color: #7AC6D2;">event_note</i> Manage Leave Type
  </h2>
  <div class="card p-4">
    <?php if ($error): ?>
      <div class="custom-alert alert-danger" role="alert">
        <div class="alert-content">
          <i class="material-icons alert-icon">error_outline</i>
          <span class="alert-text"><?php echo $error; ?></span>
        </div>
        <button type="button" class="alert-close" onclick="this.parentElement.style.display='none';">
          <i class="material-icons">close</i>
        </button>
      </div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="custom-alert alert-success" role="alert">
        <div class="alert-content">
          <i class="material-icons alert-icon">check_circle</i>
          <span class="alert-text"><?php echo $success; ?></span>
        </div>
        <button type="button" class="alert-close" onclick="this.parentElement.style.display='none';">
          <i class="material-icons">close</i>
        </button>
      </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">Leave Type Info</h4>
      <input type="text" class="form-control w-25 search-input" placeholder="Search...">
    </div>    <div class="table-responsive">
      <table class="table align-middle text-center">
        <thead>
          <tr>
            <th>#</th>
            <th>Leave Type</th>
            <th>Description</th>
            <th>Max Times</th> 
            <th>Created At</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          if (count($leaveTypes) > 0) {
              $cnt = 1;
              foreach($leaveTypes as $type) {
          ?>
          <tr>
            <td><?php echo htmlentities($cnt);?></td>
            <td><?php echo htmlentities($type['LeaveType']);?></td>
            <td><?php echo htmlentities($type['Description']);?></td>
            <td><?php echo htmlentities($type['max']);?></td>
            <td><?php echo date('Y-m-d H:i', strtotime($type['CreationDate']));?></td>
            <td>
              <a href="editleavetype.php?id=<?php echo htmlentities($type['id']);?>" class="btn btn-view btn-action me-1">Edit</a>
              <a href="manageleavetype.php?del=<?php echo htmlentities($type['id']);?>" class="btn btn-danger btn-action" 
                 onclick="return confirm('Are you sure you want to delete this leave type?');">Delete</a>
            </td>
          </tr>
          <?php 
              $cnt++;
              }
          } else { 
          ?>
          <tr>
            <td colspan="6" class="text-center">No leave types found</td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>

    <p class="text-muted mt-3">Showing <?php echo count($leaveTypes); ?> entries</p>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('menu-toggle').addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('mainContent').classList.toggle('collapsed');
  });

  // Search functionality
  document.querySelector('.search-input').addEventListener('keyup', function() {
    let searchText = this.value.toLowerCase();
    let tableRows = document.querySelectorAll('.table tbody tr');
    
    tableRows.forEach(row => {
      let text = row.textContent.toLowerCase();
      row.style.display = text.includes(searchText) ? '' : 'none';
    });
  });
</script>
</body>
</html>
