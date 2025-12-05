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

// Initialize variables
$error = '';
$success = '';

// Check for session messages
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Handle delete request
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    try {
        $sql = "DELETE FROM tblattendance WHERE id = :id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':id', $id, PDO::PARAM_INT);
        if ($query->execute()) {
            $_SESSION['success'] = "Attendance record deleted successfully";
            header('location:manage-attendance.php');
            exit();
        } else {
            $error = "Error deleting attendance record";
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle approval request
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    try {
        $sql = "UPDATE tblattendance SET approval_status = 'Approved' WHERE id = :id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':id', $id, PDO::PARAM_INT);
        if ($query->execute()) {
            $_SESSION['success'] = "Attendance approved successfully";
            header('location:manage-attendance.php');
            exit();
        } else {
            $error = "Error approving attendance";
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle rejection request
if (isset($_GET['reject'])) {
    $id = intval($_GET['reject']);
    try {
        $sql = "UPDATE tblattendance SET approval_status = 'Rejected', status = 'Absent' WHERE id = :id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':id', $id, PDO::PARAM_INT);
        if ($query->execute()) {
            $_SESSION['success'] = "Attendance rejected successfully";
            header('location:manage-attendance.php');
            exit();
        } else {
            $error = "Error rejecting attendance";
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get success message from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Get filter parameters
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch departments for dropdown
try {
    $deptSql = "SELECT id, DepartmentName FROM tbldepartments ORDER BY DepartmentName ASC";
    $deptQuery = $dbh->prepare($deptSql);
    $deptQuery->execute();
    $departments = $deptQuery->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error fetching departments: " . $e->getMessage();
}

// Fetch attendance settings for late detection
$settingsSql = "SELECT work_start, work_end FROM tblattendancesettings ORDER BY id DESC LIMIT 1";
$settingsQuery = $dbh->prepare($settingsSql);
$settingsQuery->execute();
$attendanceSettings = $settingsQuery->fetch(PDO::FETCH_ASSOC);
$work_start = $attendanceSettings ? $attendanceSettings['work_start'] : '09:00:00';

// Build attendance query with filters
try {
    $sql = "SELECT a.*, e.FirstName, e.LastName, e.EmpId, d.DepartmentName, des.DesignationName 
            FROM tblattendance a 
            INNER JOIN tblemployees e ON a.empid = e.id 
            LEFT JOIN tbldepartments d ON e.Department = d.id 
            LEFT JOIN tbldesignation des ON e.designationid = des.id 
            WHERE 1=1";
    
    // Apply filters
    if (!empty($date_filter)) {
        $sql .= " AND a.attendance_date = :date";
    }
    
    if (!empty($department_filter)) {
        $sql .= " AND d.DepartmentName = :department";
    }
    
    if (!empty($search_filter)) {
        $sql .= " AND (e.FirstName LIKE :search OR e.LastName LIKE :search OR e.EmpId LIKE :search)";
    }
    
    $sql .= " ORDER BY a.attendance_date DESC, e.FirstName ASC";
    
    $query = $dbh->prepare($sql);
    
    // Bind parameters
    if (!empty($date_filter)) {
        $query->bindParam(':date', $date_filter, PDO::PARAM_STR);
    }
    if (!empty($department_filter)) {
        $query->bindParam(':department', $department_filter, PDO::PARAM_STR);
    }
    if (!empty($search_filter)) {
        $searchParam = "%$search_filter%";
        $query->bindParam(':search', $searchParam, PDO::PARAM_STR);
    }
    
    $query->execute();
    $attendanceRecords = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Get today's statistics
    $today = date('Y-m-d');
    $statsSql = "SELECT 
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
                    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
                 FROM tblattendance 
                 WHERE attendance_date = :today";
    $statsQuery = $dbh->prepare($statsSql);
    $statsQuery->bindParam(':today', $today, PDO::PARAM_STR);
    $statsQuery->execute();
    $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error fetching attendance: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin | Manage Attendance</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #eef9fa;
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
      gap: 12px;
      padding: 10px 15px;
      font-size: 15px;
      font-weight: 500;
      color: #333;
      border-radius: 8px;
      margin-bottom: 10px;
      transition: all 0.2s ease-in-out;
      border: none;
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
      padding-left: 40px;
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

    .page-header {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #344C64;
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 30px;
    }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: white;
      padding: 20px;
      border-radius: 16px;
      box-shadow: 0 0 12px rgba(0, 0, 0, 0.05);
      border-left: 4px solid;
    }

    .stat-card.present {
      border-left-color: #48cfad;
    }

    .stat-card.late {
      border-left-color: #ffce54;
    }

    .stat-card.absent {
      border-left-color: #ed5565;
    }

    .stat-card h3 {
      margin: 0 0 5px 0;
      font-size: 32px;
      font-weight: 700;
      color: #344C64;
    }

    .stat-card p {
      margin: 0;
      color: #777;
      font-size: 14px;
    }

    .filter-card {
      background: white;
      padding: 25px;
      border-radius: 16px;
      box-shadow: 0 0 12px rgba(0, 0, 0, 0.05);
      margin-bottom: 30px;
    }

    .filter-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      align-items: end;
    }

    .filter-item label {
      display: block;
      margin-bottom: 5px;
      font-weight: 600;
      color: #555;
      font-size: 14px;
    }

    .filter-item input,
    .filter-item select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      transition: all 0.3s;
    }

    .filter-item input:focus,
    .filter-item select:focus {
      outline: none;
      border-color: #71C9CE;
      box-shadow: 0 0 0 3px rgba(113, 201, 206, 0.1);
    }

    .btn-filter {
      background: #71C9CE;
      color: #fff;
      border: none;
      padding: 10px 25px;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .btn-filter:hover {
      background: #5fb3b8;
    }

    .btn-reset {
      background: #ed5565;
      color: #fff;
      border: none;
      padding: 10px 25px;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .btn-reset:hover {
      background: #da4453;
    }

    .table-card {
      background: white;
      padding: 25px;
      border-radius: 16px;
      box-shadow: 0 0 12px rgba(0, 0, 0, 0.05);
    }

    .table-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 15px;
    }

    .table-title {
      font-size: 20px;
      font-weight: 700;
      color: #344C64;
    }

    .table thead th {
      background-color: #e9f8fa;
      color: #344C64;
      font-weight: 600;
      border: none;
      padding: 12px 8px;
      text-align: center;
    }

    .table tbody td {
      vertical-align: middle;
      padding: 12px 8px;
      text-align: center;
    }

    .table tbody tr:hover {
      background-color: #f0fbfd;
    }

    .status-badge {
      padding: 6px 12px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
      display: inline-block;
    }

    .status-present {
      background: #d4edda;
      color: #155724;
    }

    .status-late {
      background: #fff3cd;
      color: #856404;
    }

    .status-absent {
      background: #f8d7da;
      color: #721c24;
    }

    .approval-pending {
      background: #ffc107;
      color: #000;
    }

    .approval-approved {
      background: #28a745;
      color: #fff;
    }

    .approval-rejected {
      background: #dc3545;
      color: #fff;
    }

    .no-records {
      text-align: center;
      padding: 40px;
      color: #999;
      font-size: 16px;
    }

    .no-records .material-icons {
      font-size: 64px;
      opacity: 0.3;
      margin-bottom: 10px;
    }

    .btn-view {
      background-color: transparent;
      border: 1px solid #7AC6D2;
      color: #7AC6D2;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      display: inline-block;
      margin-right: 5px;
    }

    .btn-view:hover {
      background-color: #7AC6D2;
      color: white;
    }

    .btn-delete {
      background-color: transparent;
      border: 1px solid #dc3545;
      color: #dc3545;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      display: inline-block;
    }

    .btn-delete:hover {
      background-color: #dc3545;
      color: white;
    }

    .btn-approve {
      background-color: transparent;
      border: 1px solid #28a745;
      color: #28a745;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      display: inline-block;
      margin-right: 5px;
    }

    .btn-approve:hover {
      background-color: #28a745;
      color: white;
    }

    .btn-reject {
      background-color: transparent;
      border: 1px solid #ffc107;
      color: #856404;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      display: inline-block;
    }

    .btn-reject:hover {
      background-color: #ffc107;
      color: #000;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container-fluid">
    <button class="hamburger" id="menu-toggle">
      <span class="material-icons">menu</span>
    </button>
    <a class="navbar-brand" href="#">EMPFLOW</a>
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
    <div class="collapse" id="designationMenu">
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
<div class="main-content" id="main-content">
  <div class="page-header">
    <span class="material-icons">access_time</span>
    Manage Attendance
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($error); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($success); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Statistics Cards -->
  <div class="stats-row">
    <div class="stat-card present">
      <h3><?php echo isset($stats['present_count']) ? $stats['present_count'] : 0; ?></h3>
      <p>Present Today</p>
    </div>
    <div class="stat-card late">
      <h3><?php echo isset($stats['late_count']) ? $stats['late_count'] : 0; ?></h3>
      <p>Late Arrivals Today</p>
    </div>
    <div class="stat-card absent">
      <h3><?php echo isset($stats['absent_count']) ? $stats['absent_count'] : 0; ?></h3>
      <p>Absent Today</p>
    </div>
  </div>

  <!-- Filter Section -->
  <div class="filter-card">
    <form method="GET" action="">
      <div class="filter-row">
        <div class="filter-item">
          <label for="dateFilter">Date:</label>
          <input type="date" id="dateFilter" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
        </div>
        
        <div class="filter-item">
          <label for="departmentFilter">Department:</label>
          <select id="departmentFilter" name="department">
            <option value="">All Departments</option>
            <?php foreach($departments as $dept): ?>
              <option value="<?php echo htmlspecialchars($dept['DepartmentName']); ?>" 
                <?php echo ($department_filter == $dept['DepartmentName']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($dept['DepartmentName']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="filter-item">
          <label for="searchFilter">Search Employee:</label>
          <input type="text" id="searchFilter" name="search" 
                 placeholder="Name or Emp ID..." 
                 value="<?php echo htmlspecialchars($search_filter); ?>">
        </div>
        
        <div class="filter-item">
          <label>&nbsp;</label>
          <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn-filter">
              <span class="material-icons" style="font-size: 18px;">search</span>
              Filter
            </button>
            <button type="button" class="btn-reset" onclick="window.location.href='manage-attendance.php'">
              <span class="material-icons" style="font-size: 18px;">refresh</span>
              Reset
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- Attendance Table -->
  <div class="table-card">
    <div class="table-header">
      <div class="table-title">Attendance Records</div>
    </div>
    
    <div class="table-responsive">
      <table class="table table-hover" id="attendanceTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Employee Name</th>
            <th>Emp ID</th>
            <th>Department</th>
            <th>Designation</th>
            <th>Date</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Work Hours</th>
            <th>Status</th>
            <th>Approval</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          if (count($attendanceRecords) > 0) {
              $cnt = 1;
              foreach($attendanceRecords as $record) {
                  // Get approval status first
                  $approvalStatus = isset($record['approval_status']) ? $record['approval_status'] : 'Pending';
                  
                  // Recalculate status based on check-in time vs work start time (comparing only HH:MM)
                  $displayStatus = $record['status'];
                  
                  // If rejected, always show Absent
                  if ($approvalStatus == 'Rejected') {
                      $displayStatus = 'Absent';
                  } elseif ($record['check_in_time']) {
                      // If there's a check-in time, verify if it's late
                      $check_in_hhmm = substr($record['check_in_time'], 0, 5); // Get HH:MM
                      $work_start_hhmm = substr($work_start, 0, 5); // Get HH:MM
                      
                      if ($check_in_hhmm > $work_start_hhmm) {
                          $displayStatus = 'Late';
                      } elseif ($displayStatus != 'Absent') {
                          $displayStatus = 'Present';
                      }
                  }
                  
                  // Determine status class
                  $statusClass = 'status-present';
                  if($displayStatus == 'Late') {
                      $statusClass = 'status-late';
                  } elseif($displayStatus == 'Absent') {
                      $statusClass = 'status-absent';
                  }
          ?>
          <tr>
            <td><?php echo $cnt; ?></td>
            <td><?php echo htmlspecialchars($record['FirstName'] . ' ' . $record['LastName']); ?></td>
            <td><?php echo htmlspecialchars($record['EmpId']); ?></td>
            <td><?php echo htmlspecialchars($record['DepartmentName']); ?></td>
            <td><?php echo htmlspecialchars($record['DesignationName'] ?? 'N/A'); ?></td>
            <td><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
            <td><?php echo $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '-'; ?></td>
            <td><?php echo $record['check_out_time'] ? date('h:i A', strtotime($record['check_out_time'])) : '-'; ?></td>
            <td><?php echo $record['work_hours'] ? number_format($record['work_hours'], 2) . ' hrs' : '-'; ?></td>
            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($displayStatus); ?></span></td>
            <td>
              <?php 
              $approvalStatus = isset($record['approval_status']) ? $record['approval_status'] : 'Pending';
              $approvalClass = 'approval-pending';
              if($approvalStatus == 'Approved') {
                  $approvalClass = 'approval-approved';
              } elseif($approvalStatus == 'Rejected') {
                  $approvalClass = 'approval-rejected';
              }
              ?>
              <span class="status-badge <?php echo $approvalClass; ?>"><?php echo htmlspecialchars($approvalStatus); ?></span>
            </td>
            <td>
              <?php if($approvalStatus == 'Pending'): ?>
                <div style="display: flex; gap: 5px; white-space: nowrap;">
                  <a href="manage-attendance.php?approve=<?php echo htmlspecialchars($record['id']); ?>" class="btn-approve" onclick="return confirm('Approve this attendance record?');">Approve</a>
                  <a href="manage-attendance.php?reject=<?php echo htmlspecialchars($record['id']); ?>" class="btn-reject" onclick="return confirm('Reject this attendance record?');">Reject</a>
                </div>
              <?php else: ?>
                <div style="display: flex; gap: 5px; white-space: nowrap;">
                  <a href="view-attendance.php?empid=<?php echo htmlspecialchars($record['empid']); ?>" class="btn-view">View</a>
                  <a href="manage-attendance.php?del=<?php echo htmlspecialchars($record['id']); ?>\" class="btn-delete" onclick="return confirm('Are you sure you want to delete this attendance record?');">Delete</a>
                </div>
              <?php endif; ?>
            </td>
          </tr>
          <?php 
                  $cnt++;
              }
          } else {
          ?>
          <tr>
            <td colspan="11" class="no-records">
              <div class="material-icons">event_busy</div>
              <p>No attendance records found</p>
            </td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Sidebar toggle
  document.getElementById('menu-toggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('main-content').classList.toggle('collapsed');
  });
</script>

</body>
</html>
