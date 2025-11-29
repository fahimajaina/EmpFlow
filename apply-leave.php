<?php
// Start session and include required files
session_start();
include('include/config.php');

// Check if employee is logged in
if (!isset($_SESSION['eid'])) {
    header('location: index.php');
    exit();
}

// Get messages from session
$error = isset($_SESSION['error_msg']) ? $_SESSION['error_msg'] : "";
$msg = isset($_SESSION['success_msg']) ? $_SESSION['success_msg'] : "";
// Clear the messages
unset($_SESSION['error_msg']);
unset($_SESSION['success_msg']);

$empid = $_SESSION['eid'];

// Fetch employee details for sidebar
$sql = "SELECT FirstName, LastName, EmpId FROM tblemployees WHERE id = :empid";
$query = $dbh->prepare($sql);
$query->bindParam(':empid', $empid, PDO::PARAM_STR);
$query->execute();
$employee = $query->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if (isset($_POST['apply'])) {
    $leavetype = trim($_POST['leavetype']);
    $fromdate = trim($_POST['fromdate']);
    $todate = trim($_POST['todate']);
    $description = trim($_POST['description']);
    
    // Validate inputs
    if (empty($leavetype) || empty($fromdate) || empty($todate) || empty($description)) {
        $error = "All fields are required";
    } else {
        // Convert dates for comparison
        $from_timestamp = strtotime($fromdate);
        $to_timestamp = strtotime($todate);
        $current_timestamp = strtotime(date('Y-m-d'));
        
        // Validate dates
        if ($from_timestamp < $current_timestamp) {
            $error = "From date cannot be in the past";
        } else if ($to_timestamp < $from_timestamp) {
            $error = "To date cannot be before from date";
        } else {
            // Check for overlapping leaves
            $sql = "SELECT COUNT(*) FROM tblleaves WHERE empid = :empid 
                    AND ((FromDate BETWEEN :fromdate AND :todate) 
                    OR (ToDate BETWEEN :fromdate AND :todate)
                    OR (:fromdate BETWEEN FromDate AND ToDate))
                    AND Status != 2"; // 2 = Rejected
            $query = $dbh->prepare($sql);
            $query->bindParam(':empid', $empid, PDO::PARAM_STR);
            $query->bindParam(':fromdate', $fromdate, PDO::PARAM_STR);
            $query->bindParam(':todate', $todate, PDO::PARAM_STR);
            $query->execute();
            
            if($query->fetchColumn() > 0) {
                $error = "You already have a leave application for these dates";
            } else { 
               // Get leave type ID and maximum allowed
                $sql = "SELECT id, max FROM tblleavetype WHERE LeaveType = :leavetype";
                $query = $dbh->prepare($sql);
                $query->bindParam(':leavetype', $leavetype, PDO::PARAM_STR);
                $query->execute();
                $leave_info = $query->fetch(PDO::FETCH_ASSOC);
                  if (!$leave_info) {
                    $error = "Invalid leave type";
                } else {
                    $leavetype_id = $leave_info['id'];
                    $max_allowed = $leave_info['max']; // Maximum number of times leave can be applied
                    
                    // Count how many times this type of leave has been applied
                    $sql = "SELECT COUNT(*) FROM tblleaves 
                           WHERE LeaveTypeID = :leavetypeid 
                           AND empid = :empid 
                           AND Status = 1"; // 1 = Approved
                    $query = $dbh->prepare($sql);
                    $query->bindParam(':leavetypeid', $leavetype_id, PDO::PARAM_INT);
                    $query->bindParam(':empid', $empid, PDO::PARAM_STR);
                    $query->execute();
                    $used_count = $query->fetchColumn();
                
                    if($used_count >= $max_allowed) {
                        $error = "You have already used the maximum number of applications for this leave type";
                    } else {
                        // Insert leave application
                        $status = 0; // 0 = Pending
                        $sql = "INSERT INTO tblleaves(LeaveTypeID, ToDate, FromDate, Description, Status, empid) 
                                VALUES(:leavetypeid, :todate, :fromdate, :description, :status, :empid)";
                        $query = $dbh->prepare($sql);
                        $query->bindParam(':leavetypeid', $leavetype_id, PDO::PARAM_INT);
                        $query->bindParam(':fromdate', $fromdate, PDO::PARAM_STR);
                        $query->bindParam(':todate', $todate, PDO::PARAM_STR);
                        $query->bindParam(':description', $description, PDO::PARAM_STR);
                        $query->bindParam(':status', $status, PDO::PARAM_INT);
                        $query->bindParam(':empid', $empid, PDO::PARAM_STR);    
                        if($query->execute()) {
                            $_SESSION['success_msg'] = "Leave application submitted successfully";
                        } else {
                            $_SESSION['error_msg'] = "Something went wrong. Please try again";
                        }
                        header("Location: apply-leave.php");
                        exit();}
                }
            }
        }
    }
}


// Fetch available leave types with remaining balance in a single query
$sql = "SELECT lt.id, lt.LeaveType, lt.max, 
        COALESCE(COUNT(l.id), 0) as used_count 
        FROM tblleavetype lt 
        LEFT JOIN tblleaves l ON lt.id = l.LeaveTypeID 
            AND l.empid = :empid 
            AND l.Status = 1 
        GROUP BY lt.id, lt.LeaveType, lt.max
        ORDER BY lt.LeaveType";
$query = $dbh->prepare($sql);
$query->bindParam(':empid', $empid, PDO::PARAM_STR);
$query->execute();
$leave_types = array_map(function($leave) {
    return array(
        'type' => $leave['LeaveType'],
        'remaining' => $leave['max'] - $leave['used_count'],
        'used' => $leave['used_count'],
        'max' => $leave['max']
    );
}, $query->fetchAll(PDO::FETCH_ASSOC));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EMPFLOW | Apply Leave</title>

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
      padding: 80px 30px 30px 30px;
      transition: margin-left 0.3s ease;
    }

    .main-content.collapsed {
      margin-left: 0;
    }    /* Alert styles unified with Bootstrap */
    .alert {
      padding: 12px 20px;
      margin-bottom: 20px;
      background: #ffffff;
      border-left: 5px solid;
      border-radius: 8px;
      font-weight: 500;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    }

    .alert-success {
      border-color: #28a745;
      color: #155724;
    }

    .alert-danger {
      border-color: #dc3545;
      color: #721c24;
    }

    /* Form styles consolidated */
    .form-label, 
    .form-control, 
    .form-select {
      transition: all 0.3s ease;
      color: #333;
    }

    .main-content .form-control:focus,
    .main-content .form-select:focus {
      border-color: #48A6A7;
      box-shadow: 0 0 0 0.25rem rgba(72, 166, 167, 0.2);
    }

    .form-control:hover,
    .form-select:hover {
      background-color: #f9fdfd;
    }

    .btn-primary {
      background-color: #48A6A7;
      border-color: #48A6A7;
    }

    .btn-primary:hover {
      background-color: #3f9393;
      border-color: #3f9393;
    }

    .text-leave-title {
      color: #2c7a7b;
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
  <div class="container-fluid px-0">
    <div class="card shadow-sm rounded-4 p-5 border-0" style="background: #ffffff;">
      <h4 class="mb-4 fw-semibold text-leave-title">Apply for Leave</h4>     
       <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
          <?php echo htmlentities($error); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php elseif ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show">
          <?php echo htmlentities($msg); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <form method="post">
        <div class="row g-4 mb-4">
          <div class="col-md-6">
            <label for="leavetype" class="form-label fw-medium text-secondary">Leave Type</label>
            <select class="form-select rounded-3 shadow-sm border-1" id="leavetype" name="leavetype" required>             
             <option value="">Select leave type...</option>
              <?php foreach ($leave_types as $leave) { ?>
                <option value="<?php echo htmlentities($leave['type']); ?>">
                  <?php echo htmlentities($leave['type']); ?> 
                  (<?php echo htmlentities($leave['used']); ?>/<?php echo htmlentities($leave['max']); ?> applications used)
                </option>
              <?php } ?>
            </select>
          </div>
        </div>

        <div class="row g-4 mb-4">
          <div class="col-md-6">
            <label for="fromdate" class="form-label fw-medium text-secondary">From Date</label>
            <input type="date" class="form-control rounded-3 shadow-sm border-1" id="fromdate" name="fromdate" required>
          </div>
          <div class="col-md-6">
            <label for="todate" class="form-label fw-medium text-secondary">To Date</label>
            <input type="date" class="form-control rounded-3 shadow-sm border-1" id="todate" name="todate" required>
          </div>
        </div>

        <div class="mb-4">
          <label for="description" class="form-label fw-medium text-secondary">Description</label>
          <textarea class="form-control rounded-3 shadow-sm border-1" id="description" name="description" rows="4" placeholder="Enter the reason for leave..." required></textarea>
        </div>

        <button type="submit" name="apply" id="apply" class="btn btn-primary px-5 py-2 rounded-3 shadow-sm">Apply</button>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Sidebar Toggle Script -->
<script>
  const toggleBtn = document.getElementById('menu-toggle');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('main-content');
  toggleBtn?.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('collapsed');
  });

  // Auto-hide alerts after 5 seconds
  document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
      setTimeout(function() {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
      }, 5000);
    });
  });
</script>
</body>
</html>
