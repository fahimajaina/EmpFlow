<?php
session_start();
include('includes/config.php');

// Check if admin is logged in
if (!isset($_SESSION['alogin'])) {
    header('location: index.php');
    exit();
}

// Check if employee ID is provided
if (!isset($_GET['empid']) || empty($_GET['empid'])) {
    $_SESSION['error'] = "Employee ID is required";
    header('location: manage-attendance.php');
    exit();
}

$empid = intval($_GET['empid']);

// Get date range from URL or set defaults (last 30 days)
$from_date = isset($_GET['from_date']) && !empty($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d', strtotime('-30 days'));
$to_date = isset($_GET['to_date']) && !empty($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// Fetch employee information
$empSql = "SELECT e.FirstName, e.LastName, e.EmpId, e.EmailId, e.Phonenumber, e.Gender,
           d.DepartmentName, des.DesignationName
           FROM tblemployees e 
           LEFT JOIN tbldepartments d ON e.Department = d.id
           LEFT JOIN tbldesignation des ON e.designationid = des.id
           WHERE e.id = :empid";

try {
    $empQuery = $dbh->prepare($empSql);
    $empQuery->bindParam(':empid', $empid, PDO::PARAM_INT);
    $empQuery->execute();
    $employee = $empQuery->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        $_SESSION['error'] = "Employee not found with ID: " . $empid;
        header('location: manage-attendance.php');
        exit();
    }

    // Fetch attendance settings for late detection
    $settingsSql = "SELECT work_start FROM tblattendancesettings ORDER BY id DESC LIMIT 1";
    $settingsQuery = $dbh->prepare($settingsSql);
    $settingsQuery->execute();
    $attendanceSettings = $settingsQuery->fetch(PDO::FETCH_ASSOC);
    $work_start = $attendanceSettings ? $attendanceSettings['work_start'] : '09:00:00';

    // Fetch attendance records for date range
    $attSql = "SELECT * FROM tblattendance 
               WHERE empid = :empid 
               AND attendance_date BETWEEN :from_date AND :to_date
               ORDER BY attendance_date ASC";
    $attQuery = $dbh->prepare($attSql);
    $attQuery->bindParam(':empid', $empid, PDO::PARAM_INT);
    $attQuery->bindParam(':from_date', $from_date, PDO::PARAM_STR);
    $attQuery->bindParam(':to_date', $to_date, PDO::PARAM_STR);
    $attQuery->execute();
    $attendanceRecords = $attQuery->fetchAll(PDO::FETCH_ASSOC);

    // Create array indexed by date
    $attendanceByDate = [];
    foreach ($attendanceRecords as $record) {
        $attendanceByDate[$record['attendance_date']] = $record;
    }

    // Fetch approved leaves that overlap with date range
    $leaveSql = "SELECT l.FromDate, l.ToDate, lt.LeaveType 
                 FROM tblleaves l
                 INNER JOIN tblleavetype lt ON l.LeaveTypeID = lt.id
                 WHERE l.empid = :empid 
                 AND l.Status = 1
                 AND (
                     (l.FromDate BETWEEN :from_date AND :to_date)
                     OR (l.ToDate BETWEEN :from_date AND :to_date)
                     OR (l.FromDate <= :from_date AND l.ToDate >= :to_date)
                 )";
    $leaveQuery = $dbh->prepare($leaveSql);
    $leaveQuery->bindParam(':empid', $empid, PDO::PARAM_INT);
    $leaveQuery->bindParam(':from_date', $from_date, PDO::PARAM_STR);
    $leaveQuery->bindParam(':to_date', $to_date, PDO::PARAM_STR);
    $leaveQuery->execute();
    $leaveRecords = $leaveQuery->fetchAll(PDO::FETCH_ASSOC);

    // Create array of dates with leave types
    $leaveByDate = [];
    foreach ($leaveRecords as $leave) {
        $currentDate = strtotime($leave['FromDate']);
        $endDate = strtotime($leave['ToDate']);
        
        while ($currentDate <= $endDate) {
            $dateStr = date('Y-m-d', $currentDate);
            $leaveByDate[$dateStr] = $leave['LeaveType'];
            $currentDate = strtotime('+1 day', $currentDate);
        }
    }

    // Generate all dates in range
    $dateRange = [];
    $currentDate = strtotime($from_date);
    $endDate = strtotime($to_date);
    
    while ($currentDate <= $endDate) {
        $dateStr = date('Y-m-d', $currentDate);
        $dateRange[$dateStr] = [
            'date' => $dateStr,
            'attendance' => isset($attendanceByDate[$dateStr]) ? $attendanceByDate[$dateStr] : null,
            'leave_type' => isset($leaveByDate[$dateStr]) ? $leaveByDate[$dateStr] : null
        ];
        $currentDate = strtotime('+1 day', $currentDate);
    }

    // Calculate statistics for selected date range
    $total_days = count($dateRange);
    $total_present = 0;
    $total_late = 0;
    $total_absent = 0;
    $total_on_leave = 0;
    $total_work_hours = 0;

    foreach ($dateRange as $day) {
        if ($day['leave_type']) {
            $total_on_leave++;
        } elseif ($day['attendance']) {
            // Recalculate status based on check-in time vs work start time (same logic as display)
            $status = $day['attendance']['status'];
            $displayStatus = $status;
            
            // Get approval status
            $approvalStatus = isset($day['attendance']['approval_status']) ? $day['attendance']['approval_status'] : 'Pending';
            
            // If rejected, show Absent
            if ($approvalStatus == 'Rejected') {
                $displayStatus = 'Absent';
            } elseif ($day['attendance']['check_in_time']) {
                // If there's a check-in time, verify if it's late
                $check_in_hhmm = substr($day['attendance']['check_in_time'], 0, 5); // Get HH:MM
                $work_start_hhmm = substr($work_start, 0, 5); // Get HH:MM
                
                if ($check_in_hhmm > $work_start_hhmm) {
                    $displayStatus = 'Late';
                } elseif ($displayStatus != 'Absent') {
                    $displayStatus = 'Present';
                }
            }
            
            // Count based on recalculated status
            if ($displayStatus == 'Present') {
                $total_present++;
                $total_work_hours += floatval($day['attendance']['work_hours']);
            } elseif ($displayStatus == 'Late') {
                $total_late++;
                $total_work_hours += floatval($day['attendance']['work_hours']);
            } elseif ($displayStatus == 'Absent') {
                $total_absent++;
            }
        }
    }

    $avg_hours = ($total_present + $total_late > 0) ? $total_work_hours / ($total_present + $total_late) : 0;

} catch(PDOException $e) {
    error_log("Error in view-attendance.php: " . $e->getMessage());
    $_SESSION['error'] = "Database error: " . $e->getMessage();
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

    <!-- Salary Management -->
     <a class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="collapse" href="#payrollMenu" role="button" aria-expanded="false" aria-controls="leaveMgmtMenu">
      <span class="material-icons">payments</span> Payroll
      <span class="ms-auto">›</span>
    </a>
    <div class="collapse" id="payrollMenu">
      <a href="add-salary.php" class="list-group-item list-group-item-action">Add Salary</a>
      <a href="manage-salary.php" class="list-group-item list-group-item-action">Manage Salary</a>
      <a href="generate-payroll.php" class="list-group-item list-group-item-action">Generate Payroll</a>
      <a href="manage-payroll.php" class="list-group-item list-group-item-action">Manage Payroll</a>
      <a href="payroll-settings.php" class="list-group-item list-group-item-action">Settings</a>
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
            <td><?php echo htmlspecialchars($employee['FirstName'] . ' ' . $employee['LastName']); ?></td>
          </tr>
          <tr>
            <td>Employee ID</td>
            <td><?php echo htmlspecialchars($employee['EmpId']); ?></td>
          </tr>
          <tr>
            <td>Email</td>
            <td><?php echo htmlspecialchars($employee['EmailId']); ?></td>
          </tr>
          <tr>
            <td>Phone Number</td>
            <td><?php echo htmlspecialchars($employee['Phonenumber']); ?></td>
          </tr>
          <tr>
            <td>Department</td>
            <td><?php echo htmlspecialchars($employee['DepartmentName']); ?></td>
          </tr>
          <tr>
            <td>Designation</td>
            <td><?php echo htmlspecialchars($employee['DesignationName'] ?? 'N/A'); ?></td>
          </tr>
          <tr>
            <td>Gender</td>
            <td><?php echo htmlspecialchars($employee['Gender']); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Date Range Filter -->
  <div class="card">
    <div class="card-body">
      <div class="card-title">
        <span class="material-icons">date_range</span>
        Select Date Range
      </div>
      <form method="GET" action="view-attendance.php" class="row g-3">
        <input type="hidden" name="empid" value="<?php echo $empid; ?>">
        <div class="col-md-4">
          <label for="from_date" class="form-label">From Date</label>
          <input type="date" class="form-control" id="from_date" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" required>
        </div>
        <div class="col-md-4">
          <label for="to_date" class="form-label">To Date</label>
          <input type="date" class="form-control" id="to_date" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" required>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100">
            <span class="material-icons" style="font-size: 18px; vertical-align: middle;">search</span>
            Filter
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Attendance Records -->
  <div class="card">
    <div class="card-body">
      <div class="card-title">
        <span class="material-icons">event</span>
        Attendance Records (<?php echo date('M d, Y', strtotime($from_date)); ?> - <?php echo date('M d, Y', strtotime($to_date)); ?>)
      </div>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead style="background-color: #71C9CE; color: white;">
            <tr>
              <th>Date</th>
              <th>Check In Time</th>
              <th>Check Out Time</th>
              <th>Work Hours</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($dateRange as $day): ?>
            <tr>
              <td><?php echo date('F d, Y (D)', strtotime($day['date'])); ?></td>
              <td>
                <?php 
                if ($day['leave_type']) {
                    echo '-';
                } elseif ($day['attendance'] && $day['attendance']['check_in_time']) {
                    echo date('h:i A', strtotime($day['attendance']['check_in_time']));
                } else {
                    echo '-';
                }
                ?>
              </td>
              <td>
                <?php 
                if ($day['leave_type']) {
                    echo '-';
                } elseif ($day['attendance'] && $day['attendance']['check_out_time']) {
                    echo date('h:i A', strtotime($day['attendance']['check_out_time']));
                } else {
                    echo '-';
                }
                ?>
              </td>
              <td>
                <?php 
                if ($day['leave_type']) {
                    echo '-';
                } elseif ($day['attendance'] && $day['attendance']['work_hours']) {
                    echo number_format($day['attendance']['work_hours'], 2) . ' hrs';
                } else {
                    echo '-';
                }
                ?>
              </td>
              <td>
                <?php 
                if ($day['leave_type']) {
                    echo '<span class="status-badge" style="background: #d1ecf1; color: #0c5460;">On ' . htmlspecialchars($day['leave_type']) . '</span>';
                } elseif ($day['attendance']) {
                    // Recalculate status based on check-in time vs work start time
                    $status = $day['attendance']['status'];
                    $displayStatus = $status;
                    
                    // Get approval status
                    $approvalStatus = isset($day['attendance']['approval_status']) ? $day['attendance']['approval_status'] : 'Pending';
                    
                    // If rejected, show Absent
                    if ($approvalStatus == 'Rejected') {
                        $displayStatus = 'Absent';
                    } elseif ($day['attendance']['check_in_time']) {
                        // If there's a check-in time, verify if it's late
                        $check_in_hhmm = substr($day['attendance']['check_in_time'], 0, 5); // Get HH:MM
                        $work_start_hhmm = substr($work_start, 0, 5); // Get HH:MM
                        
                        if ($check_in_hhmm > $work_start_hhmm) {
                            $displayStatus = 'Late';
                        } elseif ($displayStatus != 'Absent') {
                            $displayStatus = 'Present';
                        }
                    }
                    
                    $statusClass = 'status-present';
                    if($displayStatus == 'Late') {
                        $statusClass = 'status-late';
                    } elseif($displayStatus == 'Absent') {
                        $statusClass = 'status-absent';
                    }
                    echo '<span class="status-badge ' . $statusClass . '">' . htmlspecialchars($displayStatus) . '</span>';
                } else {
                    echo '<span class="status-badge" style="background: #e2e3e5; color: #6c757d;">No Record</span>';
                }
                ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Attendance Summary -->
  <div class="card">
    <div class="card-body">
      <div class="card-title">
        <span class="material-icons">assessment</span>
        Attendance Summary (Selected Period)
      </div>
      <div class="stats-grid">
        <div class="stat-box">
          <h2><?php echo $total_days; ?></h2>
          <p>Total Days</p>
        </div>
        <div class="stat-box">
          <h2><?php echo $total_present; ?></h2>
          <p>Present</p>
        </div>
        <div class="stat-box">
          <h2><?php echo $total_late; ?></h2>
          <p>Late</p>
        </div>
        <div class="stat-box">
          <h2><?php echo $total_absent; ?></h2>
          <p>Absent</p>
        </div>
        <div class="stat-box">
          <h2><?php echo $total_on_leave; ?></h2>
          <p>On Leave</p>
        </div>
        <div class="stat-box">
          <h2><?php echo number_format($avg_hours, 1); ?></h2>
          <p>Avg Work Hours/Day</p>
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
