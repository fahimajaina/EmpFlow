<?php
session_start();
include('includes/config.php');

// Check if admin is logged in
if (!isset($_SESSION['alogin'])) {
    header('location: index.php');
    exit();
}

// Check if attendance ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('location: manage-attendance.php');
    exit();
}

$attendance_id = intval($_GET['id']);

// Fetch attendance record with employee information
$sql = "SELECT a.*, e.FirstName, e.LastName, e.EmpId, e.EmailId, e.Phonenumber, e.Gender,
        d.DepartmentName
        FROM tblattendance a 
        INNER JOIN tblemployees e ON a.empid = e.id 
        LEFT JOIN tbldepartments d ON e.Department = d.id
        WHERE a.id = :attendance_id";

try {
    $query = $dbh->prepare($sql);
    $query->bindParam(':attendance_id', $attendance_id, PDO::PARAM_INT);
    $query->execute();
    $attendance = $query->fetch(PDO::FETCH_ASSOC);

    if (!$attendance) {
        header('location: manage-attendance.php');
        exit();
    }

    $empid = $attendance['empid'];

    // Calculate attendance statistics
    $statsSql = "SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as total_present,
                    SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as total_late,
                    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as total_absent,
                    AVG(work_hours) as avg_hours
                 FROM tblattendance 
                 WHERE empid = :empid 
                 AND YEAR(attendance_date) = YEAR(CURRENT_DATE())";
    $statsQuery = $dbh->prepare($statsSql);
    $statsQuery->bindParam(':empid', $empid, PDO::PARAM_INT);
    $statsQuery->execute();
    $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

    // Calculate leave statistics
    $leavesSql = "SELECT 
                    SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) as approved_leaves,
                    SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) as pending_leaves,
                    SUM(CASE WHEN Status = 2 THEN 1 ELSE 0 END) as rejected_leaves
                  FROM tblleaves 
                  WHERE empid = :empid 
                  AND YEAR(PostingDate) = YEAR(CURRENT_DATE())";
    $leavesQuery = $dbh->prepare($leavesSql);
    $leavesQuery->bindParam(':empid', $empid, PDO::PARAM_INT);
    $leavesQuery->execute();
    $leaves = $leavesQuery->fetch(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error in view-attendance.php: " . $e->getMessage());
    header('location: manage-attendance.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin | View Attendance</title>
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
      margin-left: 240px;
      padding: 100px 30px 30px 30px;
      background-color: #f7fdfd;
      transition: margin-left 0.3s ease;
    }

    .main-content.collapsed {
      margin-left: 0;
    }

    .page-title {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 30px;
    }

    .page-title .material-icons {
      font-size: 32px;
      color: rgb(66, 155, 193);
    }

    .page-title h3 {
      margin: 0;
      font-weight: 600;
      font-size: 26px;
      color: rgb(66, 155, 193);
    }

    .card {
      border: none;
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
      margin-bottom: 30px;
    }

    .card-title {
      font-size: 22px;
      font-weight: 600;
      color: #48A6A7;
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 20px;
    }

    .info-table td {
      padding: 12px 15px;
      border-bottom: 1px solid #f0f0f0;
    }

    .info-table td:first-child {
      font-weight: 600;
      color: #555;
      width: 200px;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }

    .stat-box {
      background: linear-gradient(135deg, #71C9CE 0%, #5fb3b8 100%);
      color: white;
      padding: 25px;
      border-radius: 12px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .stat-box h2 {
      font-size: 36px;
      font-weight: 700;
      margin: 0;
    }

    .stat-box p {
      margin: 10px 0 0 0;
      font-size: 14px;
      opacity: 0.9;
    }

    .status-badge {
      padding: 6px 12px;
      border-radius: 12px;
      font-size: 13px;
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

    .btn-back {
      background: #71C9CE;
      color: #fff;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-weight: 600;
      transition: all 0.3s;
    }

    .btn-back:hover {
      background: #5fb3b8;
      color: #fff;
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
    <img src="../assets/images/profile-image.png" alt="Admin">
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

    <!-- Attendance -->
    <a class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="collapse" href="#attendanceMenu" role="button" aria-expanded="false" aria-controls="attendanceMenu">
      <span class="material-icons">access_time</span> Attendance
      <span class="ms-auto">›</span>
    </a>
    <div class="collapse" id="attendanceMenu">
      <a href="manage-attendance.php" class="list-group-item list-group-item-action">Manage Attendance</a>
      <a href="attendance-settings.php" class="list-group-item list-group-item-action">Settings</a>
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
  <div class="page-title">
    <span class="material-icons">access_time</span>
    <h3>Attendance Details</h3>
  </div>

  <a href="manage-attendance.php" class="btn-back mb-4">
    <span class="material-icons" style="font-size: 18px;">arrow_back</span>
    Back to Manage Attendance
  </a>

  <!-- Employee Information -->
  <div class="card">
    <div class="card-body">
      <div class="card-title">
        <span class="material-icons">person</span>
        Employee Information
      </div>
      <table class="table info-table mb-0">
        <tbody>
          <tr>
            <td>Employee Name</td>
            <td><?php echo htmlspecialchars($attendance['FirstName'] . ' ' . $attendance['LastName']); ?></td>
          </tr>
          <tr>
            <td>Employee ID</td>
            <td><?php echo htmlspecialchars($attendance['EmpId']); ?></td>
          </tr>
          <tr>
            <td>Email</td>
            <td><?php echo htmlspecialchars($attendance['EmailId']); ?></td>
          </tr>
          <tr>
            <td>Phone Number</td>
            <td><?php echo htmlspecialchars($attendance['Phonenumber']); ?></td>
          </tr>
          <tr>
            <td>Department</td>
            <td><?php echo htmlspecialchars($attendance['DepartmentName']); ?></td>
          </tr>
          <tr>
            <td>Gender</td>
            <td><?php echo htmlspecialchars($attendance['Gender']); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Attendance Record Details -->
  <div class="card">
    <div class="card-body">
      <div class="card-title">
        <span class="material-icons">event</span>
        Attendance Record
      </div>
      <table class="table info-table mb-0">
        <tbody>
          <tr>
            <td>Date</td>
            <td><?php echo date('F d, Y', strtotime($attendance['attendance_date'])); ?></td>
          </tr>
          <tr>
            <td>Check In Time</td>
            <td><?php echo $attendance['check_in_time'] ? date('h:i A', strtotime($attendance['check_in_time'])) : '-'; ?></td>
          </tr>
          <tr>
            <td>Check Out Time</td>
            <td><?php echo $attendance['check_out_time'] ? date('h:i A', strtotime($attendance['check_out_time'])) : '-'; ?></td>
          </tr>
          <tr>
            <td>Work Hours</td>
            <td><?php echo $attendance['work_hours'] ? number_format($attendance['work_hours'], 2) . ' hrs' : '-'; ?></td>
          </tr>
          <tr>
            <td>Status</td>
            <td>
              <?php 
              $statusClass = 'status-present';
              if($attendance['status'] == 'Late') {
                  $statusClass = 'status-late';
              } elseif($attendance['status'] == 'Absent') {
                  $statusClass = 'status-absent';
              }
              ?>
              <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($attendance['status']); ?></span>
            </td>
          </tr>
          <tr>
            <td>Approval Status</td>
            <td>
              <?php 
              $approvalStatus = isset($attendance['approval_status']) ? $attendance['approval_status'] : 'Pending';
              $approvalClass = 'approval-pending';
              if($approvalStatus == 'Approved') {
                  $approvalClass = 'approval-approved';
              } elseif($approvalStatus == 'Rejected') {
                  $approvalClass = 'approval-rejected';
              }
              ?>
              <span class="status-badge <?php echo $approvalClass; ?>"><?php echo htmlspecialchars($approvalStatus); ?></span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Attendance Summary -->
  <div class="card">
    <div class="card-body">
      <div class="card-title">
        <span class="material-icons">assessment</span>
        Attendance Summary (Current Year)
      </div>
      <div class="stats-grid">
        <div class="stat-box">
          <h2><?php echo $stats['total_days'] ? $stats['total_days'] : 0; ?></h2>
          <p>Total Working Days</p>
        </div>
        <div class="stat-box">
          <h2><?php echo $stats['total_present'] ? $stats['total_present'] : 0; ?></h2>
          <p>Total Present</p>
        </div>
        <div class="stat-box">
          <h2><?php echo $stats['total_late'] ? $stats['total_late'] : 0; ?></h2>
          <p>Total Late</p>
        </div>
        <div class="stat-box">
          <h2><?php echo $stats['total_absent'] ? $stats['total_absent'] : 0; ?></h2>
          <p>Total Absent</p>
        </div>
        <div class="stat-box">
          <h2><?php echo $stats['avg_hours'] ? number_format($stats['avg_hours'], 1) : 0; ?></h2>
          <p>Average Daily Working Hours</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Leave Summary -->
  <div class="card">
    <div class="card-body">
      <div class="card-title">
        <span class="material-icons">beach_access</span>
        Leave Summary (Current Year)
      </div>
      <div class="stats-grid">
        <div class="stat-box" style="background: linear-gradient(135deg, #28a745 0%, #20a038 100%);">
          <h2><?php echo $leaves['approved_leaves'] ? $leaves['approved_leaves'] : 0; ?></h2>
          <p>Total Leaves Taken (Approved)</p>
        </div>
        <div class="stat-box" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
          <h2><?php echo $leaves['pending_leaves'] ? $leaves['pending_leaves'] : 0; ?></h2>
          <p>Total Leaves Pending</p>
        </div>
        <div class="stat-box" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
          <h2><?php echo $leaves['rejected_leaves'] ? $leaves['rejected_leaves'] : 0; ?></h2>
          <p>Total Leaves Rejected</p>
        </div>
      </div>
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
