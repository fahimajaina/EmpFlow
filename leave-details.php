<?php
session_start();
include('include/config.php');

// Check if user is logged in
if (!isset($_SESSION['eid'])) {
    header('location: index.php');
    exit();
}

// Check if leave ID is provided
if (!isset($_GET['leaveid']) || empty($_GET['leaveid'])) {
    header('location: leavehistory.php');
    exit();
}

$eid = $_SESSION['eid'];
$lid = intval($_GET['leaveid']);

// Fetch leave details with employee information
$sql = "SELECT l.*, lt.LeaveType, lt.max as max_allowed, 
        e.FirstName, e.LastName, e.EmpId, e.Gender, e.EmailId, e.Phonenumber,
        CASE 
            WHEN l.Status = 1 THEN 'Approved'
            WHEN l.Status = 2 THEN 'Not Approved'
            ELSE 'Pending'
        END as StatusText,
        CASE 
            WHEN l.Status = 1 THEN 'bg-success'
            WHEN l.Status = 2 THEN 'bg-danger'
            ELSE 'bg-warning text-dark'
        END as StatusClass
        FROM tblleaves l 
        INNER JOIN tblleavetype lt ON l.LeaveTypeID = lt.id 
        INNER JOIN tblemployees e ON l.empid = e.id
        WHERE l.id = :leaveid AND l.empid = :empid";

$query = $dbh->prepare($sql);
$query->bindParam(':leaveid', $lid, PDO::PARAM_INT);
$query->bindParam(':empid', $eid, PDO::PARAM_INT);
$query->execute();
$leaveDetails = $query->fetch(PDO::FETCH_ASSOC);

if (!$leaveDetails) {
    header('location: leavehistory.php');
    exit();
}

// Calculate leave statistics
$sql = "SELECT COUNT(*) as used FROM tblleaves 
        WHERE LeaveTypeID = :leavetypeid 
        AND empid = :empid 
        AND Status = 1 
        AND YEAR(PostingDate) = YEAR(CURRENT_DATE())";
$query = $dbh->prepare($sql);
$query->bindParam(':leavetypeid', $leaveDetails['LeaveTypeID'], PDO::PARAM_INT);
$query->bindParam(':empid', $eid, PDO::PARAM_INT);
$query->execute();
$leaveCount = $query->fetch(PDO::FETCH_ASSOC);

$maxAllowed = $leaveDetails['max_allowed'];
$usedLeaves = $leaveCount['used'];
$remainingLeaves = $maxAllowed - $usedLeaves;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EMPFLOW | Leave Details</title>

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
      color: #48A6A7;
    }

    .page-title h3 {
      margin: 0;
      font-weight: 600;
      font-size: 26px;
      color: #2d4f4f;
    }

    .card {
      border: none;
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
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

    .table td {
      vertical-align: middle;
      padding: 14px 12px;
      font-size: 15px;
      color: #333;
    }

    .label {
      font-weight: 600;
      color: #6c757d;
      font-size: 15px;
    }

    .value {
      font-weight: 500;
      font-size: 15.5px;
      color: #2d4f4f;
    }

    .badge {
      font-size: 14px;
      padding: 6px 14px;
      font-weight: 600;
      border-radius: 12px;
    }

    .section-divider {
      border-top: 1px solid #e0e0e0;
      margin: 20px 0;
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

      .main-content.collapsed {
        margin-left: 240px !important;
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
  <div class="container-fluid">
    <div class="page-title">
      <span class="material-icons">event_note</span>
      <h3>Leave Details</h3>
    </div>

    <div class="card p-4">
      <div class="card-title"><span class="material-icons">info</span>Leave Information</div>

      <table class="table table-borderless">
        <tbody>
          <tr>
            <td class="label">Employee Name:</td>
            <td class="value"><?php echo htmlspecialchars($leaveDetails['FirstName'] . ' ' . $leaveDetails['LastName']); ?></td>
            <td class="label">Emp ID:</td>
            <td class="value"><?php echo htmlspecialchars($leaveDetails['EmpId']); ?></td>
            <td class="label">Gender:</td>
            <td class="value"><?php echo htmlspecialchars($leaveDetails['Gender']); ?></td>
          </tr>
          <tr>
            <td class="label">Emp Email ID:</td>
            <td class="value"><?php echo htmlspecialchars($leaveDetails['EmailId']); ?></td>
            <td class="label">Emp Contact No.:</td>
            <td class="value"><?php echo htmlspecialchars($leaveDetails['Phonenumber']); ?></td>
            <td></td><td></td>
          </tr>
          <tr>
            <td class="label">Leave Type:</td>
            <td class="value"><?php echo htmlspecialchars($leaveDetails['LeaveType']); ?></td>
            <td class="label">Leave Date:</td>
            <td class="value"><?php echo htmlspecialchars($leaveDetails['FromDate'] . ' - ' . $leaveDetails['ToDate']); ?></td>
            <td class="label">Posting Date:</td>
            <td class="value"><?php echo htmlspecialchars($leaveDetails['PostingDate']); ?></td>
          </tr>
          <tr>
            <td colspan="6">
              <div class="d-flex flex-wrap gap-3">
                <div class="p-3 bg-light rounded shadow-sm flex-fill text-center">
                  <div class="label">Max Allowed (per year)</div>
                  <div class="value fs-5 fw-semibold text-primary"><?php echo $maxAllowed; ?></div>
                </div>
                <div class="p-3 bg-light rounded shadow-sm flex-fill text-center">
                  <div class="label">Requested So Far</div>
                  <div class="value fs-5 fw-semibold text-warning"><?php echo $usedLeaves; ?></div>
                </div>
                <div class="p-3 bg-light rounded shadow-sm flex-fill text-center">
                  <div class="label">Remaining</div>
                  <div class="value fs-5 fw-semibold text-success"><?php echo $remainingLeaves; ?></div>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="section-divider"></div>

      <table class="table table-borderless mt-3">
        <tbody>
          <tr>
            <td class="label">Leave Description:</td>
            <td class="value" colspan="5"><?php echo htmlspecialchars($leaveDetails['Description']); ?></td>
          </tr>
          <tr>
            <td class="label">Leave Status:</td>
            <td colspan="5"><span class="badge <?php echo $leaveDetails['StatusClass']; ?> text-white"><?php echo htmlspecialchars($leaveDetails['StatusText']); ?></span></td>
          </tr>          <tr>
            <td class="label">Admin Remark:</td>
            <td class="value" colspan="5"><?php echo !empty($leaveDetails['AdminRemark']) ? htmlspecialchars($leaveDetails['AdminRemark']) : 'Not Available'; ?></td>
          </tr>          <tr>
            <td class="label">Admin Action Date:</td>
            <td class="value" colspan="5"><?php echo !empty($leaveDetails['AdminRemarkDate']) ? htmlspecialchars($leaveDetails['AdminRemarkDate']) : 'Not Available'; ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const toggleBtn = document.getElementById('menu-toggle');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('main-content');

  toggleBtn?.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('collapsed');
  });
</script>

</body>
</html>
