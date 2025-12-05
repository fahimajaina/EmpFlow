<?php
// Start the session
session_start();

// Include database connection
require_once('includes/config.php');

// Check if admin is logged in
if (!isset($_SESSION['alogin'])) {
    header('location:index.php');
    exit();
}

// Handle designation deletion
if (isset($_GET['del'])) {
    $designationId = intval($_GET['del']);
    
    // First check if any employees are assigned to this designation
    $checkSql = "SELECT COUNT(*) as empCount FROM tblemployees WHERE designationid = :designationId";
    $checkStmt = $dbh->prepare($checkSql);
    $checkStmt->bindParam(':designationId', $designationId, PDO::PARAM_INT);
    $checkStmt->execute();
    $empCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['empCount'];
    
    if ($empCount > 0) {
        $_SESSION['error'] = "Designation cannot be deleted. There are employees assigned to it.";
    } else {
        // No employees assigned, safe to delete
        $sql = "DELETE FROM tbldesignation WHERE id=:designationId";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':designationId', $designationId, PDO::PARAM_INT);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Designation deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting designation";
        }
    }
    header("Location: managedesignation.php");
    exit();
}

// Fetch all designations with department names
try {
    $sql = "SELECT d.id, d.DesignationName, d.created_at, dept.DepartmentName 
            FROM tbldesignation d 
            LEFT JOIN tbldepartments dept ON d.deptid = dept.id 
            ORDER BY dept.DepartmentName ASC, d.DesignationName ASC";
    $query = $dbh->prepare($sql);
    $query->execute();
    $designations = $query->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    $designations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin | Manage Designations</title>
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
    }

    .btn-view:hover {
      background-color: #7AC6D2;
      color: white;
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

    <!-- Designation -->
    <a class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="collapse" href="#designationMenu" role="button" aria-expanded="false" aria-controls="designationMenu">
      <span class="material-icons">badge</span> Designation
      <span class="ms-auto">›</span>
    </a>
    <div class="collapse show" id="designationMenu">
      <a href="adddesignation.php" class="list-group-item list-group-item-action">Add Designation</a>
      <a href="managedesignation.php" class="list-group-item list-group-item-action">Manage Designation</a>
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
    <i class="material-icons me-2" style="color: #7AC6D2;">badge</i> Manage Designations
  </h2>

  <?php   // Display Messages
  if(isset($_SESSION['error'])) {
      echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
              '.htmlentities($_SESSION['error']).'
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
      unset($_SESSION['error']);
  }
  if(isset($_SESSION['success'])) { 
      echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              '.htmlentities($_SESSION['success']).'
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
      unset($_SESSION['success']);
  }
  ?>

  <div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">Designations Info</h4>
      <input type="text" class="form-control w-25 search-input" id="searchInput" placeholder="Search designations...">
    </div>

    <div class="table-responsive">
      <table class="table align-middle text-center">
        <thead>
          <tr>
            <th>#</th>
            <th>Designation Name</th>
            <th>Department</th>
            <th>Created At</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          if(!empty($designations)) {
              $cnt = 1;
              foreach($designations as $designation) {
          ?>
          <tr>
            <td><?php echo htmlentities($cnt);?></td>
            <td><?php echo htmlentities($designation['DesignationName']);?></td>
            <td><?php echo htmlentities($designation['DepartmentName']);?></td>
            <td><?php echo htmlentities($designation['created_at']);?></td>
            <td>
              <a href="editdesignation.php?id=<?php echo htmlentities($designation['id']);?>" class="btn btn-view btn-action me-1">Edit</a>
              <a href="managedesignation.php?del=<?php echo htmlentities($designation['id']);?>" class="btn btn-danger btn-action" 
                 onclick="return confirm('Are you sure you want to delete this designation?');">Delete</a>
            </td>
          </tr>
          <?php 
              $cnt++;
              }
          } else { ?>
          <tr>
              <td colspan="5" class="text-center">No designations found</td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>

    <p class="text-muted mt-3">Showing <?php echo count($designations);?> entries</p>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Toggle sidebar
  document.getElementById('menu-toggle').addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('mainContent').classList.toggle('collapsed');
  });

  // Search functionality
  document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('table tbody tr');
    
    tableRows.forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(searchValue) ? '' : 'none';
    });
  });
</script>
</body>
</html>
