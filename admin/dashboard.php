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
$stats = array();
$latestLeaves = array();

try {
    // Get total employees count
    $sql = "SELECT COUNT(*) as total FROM tblemployees";
    $query = $dbh->prepare($sql);
    $query->execute();
    $stats['employees'] = $query->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total departments count
    $sql = "SELECT COUNT(*) as total FROM tbldepartments";
    $query = $dbh->prepare($sql);
    $query->execute();
    $stats['departments'] = $query->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total leave types count
    $sql = "SELECT COUNT(*) as total FROM tblleavetype";
    $query = $dbh->prepare($sql);
    $query->execute();
    $stats['leaveTypes'] = $query->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total leaves count
    $sql = "SELECT COUNT(*) as total FROM tblleaves";
    $query = $dbh->prepare($sql);
    $query->execute();
    $stats['totalLeaves'] = $query->fetch(PDO::FETCH_ASSOC)['total'];

    // Get approved leaves count
    $sql = "SELECT COUNT(*) as total FROM tblleaves WHERE Status = 1";
    $query = $dbh->prepare($sql);
    $query->execute();
    $stats['approvedLeaves'] = $query->fetch(PDO::FETCH_ASSOC)['total'];

    // Get pending leaves count
    $sql = "SELECT COUNT(*) as total FROM tblleaves WHERE Status = 0";
    $query = $dbh->prepare($sql);
    $query->execute();
    $stats['pendingLeaves'] = $query->fetch(PDO::FETCH_ASSOC)['total'];

    // Get latest leave applications
    $sql = "SELECT l.id, l.PostingDate, l.Status, 
            e.FirstName, e.LastName, lt.LeaveType
            FROM tblleaves l
            INNER JOIN tblemployees e ON l.empid = e.id
            INNER JOIN tblleavetype lt ON l.LeaveTypeID = lt.id
            ORDER BY l.PostingDate DESC LIMIT 5";
    
    $query = $dbh->prepare($sql);
    $query->execute();
    $latestLeaves = $query->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
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
  <meta charset="UTF-8">
  <title>EMPFLOW Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts & Material Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
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


    .content {
      margin-left: 240px;
      padding: 80px 20px 20px 20px;
      transition: margin-left 0.3s ease;
    }

    .content.shifted {
      margin-left: 0;
    }

    h2 {
      font-weight: 600;
      color: #3D90D7;
    }

    .card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
      transition: transform 0.2s ease;
      cursor: pointer;
      text-decoration: none;
    }

    .card:hover {
      transform: translateY(-4px);
      text-decoration: none;
    }

    .btn-outline-primary {
      border-color: #3D90D7;
      color: #3D90D7;
    }

    .btn-outline-primary:hover {
      background-color: #3D90D7;
      color: white;
    }

    .badge {
      padding: 0.5em 0.75em;
      font-size: 0.75rem;
      border-radius: 12px;
      font-weight: 600;
    }

    .badge-approved {
      background-color: #28a745;
      color: white;
    }

    .badge-pending {
      background-color: #ffc107;
      color: black;
    }

    .badge-rejected {
      background-color: #dc3545;
      color: white;
    }

    @media (max-width: 768px) {
      #sidebar {
        transform: translateX(-240px);
      }

      #sidebar.show {
        transform: translateX(0);
      }

      .content {
        margin-left: 0;
        padding-top: 100px;
      }
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top">
  <button class="hamburger me-3" id="toggleSidebar">
    <span class="material-icons">menu</span>
  </button>
  <a class="navbar-brand ms-2 d-flex align-items-center" href="dashboard.php" style="pointer-events: none;">
    <span class="material-icons me-2">admin_panel_settings</span>
    <span style="text-decoration: none; color: white;">Admin Dashboard</span>
  </a>
  <div class="ms-auto">
    <a href="dashboard.php" class="text-white text-decoration-none" style="font-size: 22px; font-weight: 600;">
      EMPFLOW
    </a>
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
<div class="content" id="mainContent">
  <div class="container-fluid">
    <h2 class="mb-4">Welcome, Admin!</h2>

    <div class="row g-4">
      <!-- Clickable Cards -->
      <div class="col-md-4">
        <a href="manageemployee.php" class="card text-white" style="background-color:rgb(99, 175, 205);">
          <div class="card-body">
            <h5>Total Registered Employees</h5>
            <p class="fs-4"><?php echo htmlentities($stats['employees']); ?></p>
          </div>
        </a>
      </div>
      <div class="col-md-4">
        <a href="managedepartments.php" class="card text-white" style="background-color:rgb(99, 175, 205);">
          <div class="card-body">
            <h5>Listed Departments</h5>
            <p class="fs-4"><?php echo htmlentities($stats['departments']); ?></p>
          </div>
        </a>
      </div>
      <div class="col-md-4">
        <a href="manageleavetype.php" class="card text-white" style="background-color:rgb(99, 175, 205);">
          <div class="card-body">
            <h5>Listed Leave Types</h5>
            <p class="fs-4"><?php echo htmlentities($stats['leaveTypes']); ?></p>
          </div>
        </a>
      </div>
      <div class="col-md-4">
        <a href="leaves.php" class="card text-white" style="background-color:rgb(99, 175, 205);">
          <div class="card-body">
            <h5>Total Leaves</h5>
            <p class="fs-4"><?php echo htmlentities($stats['totalLeaves']); ?></p>
          </div>
        </a>
      </div>
      <div class="col-md-4">
        <a href="approvedleave-history.php" class="card text-white" style="background-color:rgb(99, 175, 205);">
          <div class="card-body">
            <h5>Approved Leaves</h5>
            <p class="fs-4"><?php echo htmlentities($stats['approvedLeaves']); ?></p>
          </div>
        </a>
      </div>
      <div class="col-md-4">
        <a href="pending-leavehistory.php" class="card text-white" style="background-color:rgb(99, 175, 205);">
          <div class="card-body">
            <h5>New Leave Applications</h5>
            <p class="fs-4"><?php echo htmlentities($stats['pendingLeaves']); ?></p>
          </div>
        </a>
      </div>
    </div>

    <!-- Latest Leave Applications Table -->
    <div class="card mt-5">
      <div class="card-body">
        <h4 class="mb-4" style="color:rgb(99, 175, 205);">Latest Leave Applications</h4>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead class="table-dark">
              <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Leave Type</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              if (!empty($latestLeaves)) {
                  $cnt = 1;
                  foreach($latestLeaves as $leave) {
                      $statusClass = '';
                      $statusText = '';
                      
                      switch($leave['Status']) {
                          case 1:
                              $statusClass = 'badge-approved';
                              $statusText = 'Approved';
                              break;
                          case 2:
                              $statusClass = 'badge-rejected';
                              $statusText = 'Not Approved';
                              break;
                          default:
                              $statusClass = 'badge-pending';
                              $statusText = 'Pending';
                      }
                      ?>
                      <tr>
                          <td><?php echo $cnt; ?></td>
                          <td><?php echo htmlentities($leave['FirstName'] . ' ' . $leave['LastName']); ?></td>
                          <td><?php echo htmlentities($leave['LeaveType']); ?></td>
                          <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                          <td><a href="leave-details.php?leaveid=<?php echo htmlentities($leave['id']); ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                      </tr>
                      <?php
                      $cnt++;
                  }
              } else { ?>
                  <tr>
                      <td colspan="5" class="text-center">No leave applications found</td>
                  </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript for Bootstrap 5 -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>

<script>
  document.getElementById('toggleSidebar').addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('mainContent').classList.toggle('shifted');
  });
</script>

</body>
</html>
