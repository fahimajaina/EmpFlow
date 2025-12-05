<?php
session_start();
require_once('includes/config.php');

if (!isset($_SESSION['alogin'])) {
    header('location:index.php');
    exit();
}

$error = '';
$success = '';
$preview = false;
$payrollData = [];

// Get payroll settings
try {
    $sql = "SELECT * FROM tblpayrollsettings WHERE id = 1";
    $query = $dbh->prepare($sql);
    $query->execute();
    $payrollSettings = $query->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading payroll settings: " . $e->getMessage();
}

// Handle form submission - Generate/Preview
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    $action = $_POST['action']; // 'preview' or 'generate'
    
    $empid = isset($_POST['empid']) && $_POST['empid'] != 'all' ? intval($_POST['empid']) : 'all';
    
    // Calculate working days in month
    $working_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    
    // Get employees to process
    try {
        if ($empid === 'all') {
            $sql = "SELECT s.*, e.id as empid, e.EmpId, e.FirstName, e.LastName, d.DepartmentName
                    FROM tblsalary s
                    INNER JOIN tblemployees e ON s.empid = e.id
                    LEFT JOIN tbldepartments d ON e.Department = d.id
                    WHERE e.Status = 1
                    ORDER BY e.FirstName ASC";
            $query = $dbh->prepare($sql);
        } else {
            $sql = "SELECT s.*, e.id as empid, e.EmpId, e.FirstName, e.LastName, d.DepartmentName
                    FROM tblsalary s
                    INNER JOIN tblemployees e ON s.empid = e.id
                    LEFT JOIN tbldepartments d ON e.Department = d.id
                    WHERE s.empid = :empid";
            $query = $dbh->prepare($sql);
            $query->bindParam(':empid', $empid, PDO::PARAM_INT);
        }
        
        $query->execute();
        $employees = $query->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($employees)) {
            $error = "No employees found with salary information";
        } else {
            // Process each employee
            foreach ($employees as $emp) {
                $employee_id = $emp['empid'];
                
                // Check if payroll already exists
                $checkSql = "SELECT id FROM tblpayroll WHERE empid = :empid AND month = :month AND year = :year";
                $checkQuery = $dbh->prepare($checkSql);
                $checkQuery->bindParam(':empid', $employee_id, PDO::PARAM_INT);
                $checkQuery->bindParam(':month', $month, PDO::PARAM_INT);
                $checkQuery->bindParam(':year', $year, PDO::PARAM_INT);
                $checkQuery->execute();
                
                if ($checkQuery->rowCount() > 0 && $action == 'generate') {
                    continue; // Skip if already exists
                }
                
                // Calculate attendance statistics
                $firstDay = sprintf("%04d-%02d-01", $year, $month);
                $lastDay = sprintf("%04d-%02d-%02d", $year, $month, $working_days);
                
                // Count present days (Present or Late status)
                $attSql = "SELECT 
                           COUNT(CASE WHEN status IN ('Present', 'Late') THEN 1 END) as present_count,
                           COUNT(CASE WHEN status = 'Late' THEN 1 END) as late_count,
                           COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent_marked
                           FROM tblattendance 
                           WHERE empid = :empid 
                           AND attendance_date BETWEEN :first_day AND :last_day";
                $attQuery = $dbh->prepare($attSql);
                $attQuery->bindParam(':empid', $employee_id, PDO::PARAM_INT);
                $attQuery->bindParam(':first_day', $firstDay, PDO::PARAM_STR);
                $attQuery->bindParam(':last_day', $lastDay, PDO::PARAM_STR);
                $attQuery->execute();
                $attData = $attQuery->fetch(PDO::FETCH_ASSOC);
                
                $present_days = intval($attData['present_count']);
                $late_days = intval($attData['late_count']);
                $absent_marked = intval($attData['absent_marked']);
                
                // Count approved leaves
                $leaveSql = "SELECT FromDate, ToDate 
                            FROM tblleaves 
                            WHERE empid = :empid 
                            AND Status = 1
                            AND ((FromDate BETWEEN :first_day AND :last_day) 
                                 OR (ToDate BETWEEN :first_day AND :last_day)
                                 OR (FromDate <= :first_day AND ToDate >= :last_day))";
                $leaveQuery = $dbh->prepare($leaveSql);
                $leaveQuery->bindParam(':empid', $employee_id, PDO::PARAM_INT);
                $leaveQuery->bindParam(':first_day', $firstDay, PDO::PARAM_STR);
                $leaveQuery->bindParam(':last_day', $lastDay, PDO::PARAM_STR);
                $leaveQuery->execute();
                $leaves = $leaveQuery->fetchAll(PDO::FETCH_ASSOC);
                
                // Count leave days in this month
                $leave_days = 0;
                foreach ($leaves as $leave) {
                    $leaveStart = max(strtotime($leave['FromDate']), strtotime($firstDay));
                    $leaveEnd = min(strtotime($leave['ToDate']), strtotime($lastDay));
                    $leave_days += floor(($leaveEnd - $leaveStart) / 86400) + 1;
                }
                
                // Calculate absent days (working days - present - leaves)
                $absent_days = $working_days - $present_days - $leave_days;
                if ($absent_days < 0) $absent_days = 0;
                
                // Assume all approved leaves are paid (you can add unpaid leave logic here)
                $paid_leave_days = $leave_days;
                $unpaid_leave_days = 0;
                
                // Calculate base salary components
                $base_salary = floatval($emp['base_salary']);
                $hra = floatval($emp['hra'] ?? 0);
                $medical = floatval($emp['medical'] ?? 0);
                $transport = floatval($emp['transport'] ?? 0);
                $bonus = floatval($emp['bonus'] ?? 0);
                $other_allowances = floatval($emp['other_allowances'] ?? 0);
                
                $total_allowances = $hra + $medical + $transport + $bonus + $other_allowances;
                $gross_salary = $base_salary + $total_allowances;
                
                // Calculate deductions
                $per_day_salary = $base_salary / $working_days;
                
                // Absent deduction
                $absent_deduction = $per_day_salary * $absent_days;
                
                // Unpaid leave deduction
                $unpaid_leave_deduction = $per_day_salary * $unpaid_leave_days;
                
                // Late deduction based on settings
                $late_deduction = 0;
                if ($payrollSettings['deduction_type'] == 'count_as_absent') {
                    $late_as_absent = floor($late_days / $payrollSettings['late_count_as_absent']);
                    $late_deduction = $per_day_salary * $late_as_absent;
                } else {
                    $late_deduction = $payrollSettings['fixed_late_deduction'] * $late_days;
                }
                
                $other_deductions = 0; // Can be added manually
                $total_deductions = $absent_deduction + $late_deduction + $unpaid_leave_deduction + $other_deductions;
                
                // Calculate net salary
                $net_salary = $gross_salary - $total_deductions;
                
                // Store payroll data
                $payrollData[] = [
                    'empid' => $employee_id,
                    'emp_code' => $emp['EmpId'],
                    'name' => $emp['FirstName'] . ' ' . $emp['LastName'],
                    'department' => $emp['DepartmentName'],
                    'base_salary' => $base_salary,
                    'hra' => $hra,
                    'medical' => $medical,
                    'transport' => $transport,
                    'bonus' => $bonus,
                    'other_allowances' => $other_allowances,
                    'total_allowances' => $total_allowances,
                    'working_days' => $working_days,
                    'present_days' => $present_days,
                    'absent_days' => $absent_days,
                    'late_days' => $late_days,
                    'leave_days' => $leave_days,
                    'paid_leave_days' => $paid_leave_days,
                    'unpaid_leave_days' => $unpaid_leave_days,
                    'absent_deduction' => $absent_deduction,
                    'late_deduction' => $late_deduction,
                    'unpaid_leave_deduction' => $unpaid_leave_deduction,
                    'other_deductions' => $other_deductions,
                    'total_deductions' => $total_deductions,
                    'gross_salary' => $gross_salary,
                    'net_salary' => $net_salary
                ];
            }
            
            if ($action == 'preview') {
                $preview = true;
            } elseif ($action == 'generate') {
                // Save to database
                $saved_count = 0;
                $admin_id = $_SESSION['alogin']; // Assuming session stores admin ID
                
                foreach ($payrollData as $data) {
                    try {
                        // Check again if exists
                        $checkSql = "SELECT id FROM tblpayroll WHERE empid = :empid AND month = :month AND year = :year";
                        $checkQuery = $dbh->prepare($checkSql);
                        $checkQuery->bindParam(':empid', $data['empid'], PDO::PARAM_INT);
                        $checkQuery->bindParam(':month', $month, PDO::PARAM_INT);
                        $checkQuery->bindParam(':year', $year, PDO::PARAM_INT);
                        $checkQuery->execute();
                        
                        if ($checkQuery->rowCount() > 0) {
                            continue;
                        }
                        
                        $sql = "INSERT INTO tblpayroll (
                                empid, month, year, base_salary, total_allowances,
                                hra, medical, transport, bonus, other_allowances,
                                working_days, present_days, absent_days, late_days, leave_days,
                                paid_leave_days, unpaid_leave_days,
                                absent_deduction, late_deduction, unpaid_leave_deduction, other_deductions,
                                total_deductions, gross_salary, net_salary
                                ) VALUES (
                                :empid, :month, :year, :base_salary, :total_allowances,
                                :hra, :medical, :transport, :bonus, :other_allowances,
                                :working_days, :present_days, :absent_days, :late_days, :leave_days,
                                :paid_leave_days, :unpaid_leave_days,
                                :absent_deduction, :late_deduction, :unpaid_leave_deduction, :other_deductions,
                                :total_deductions, :gross_salary, :net_salary
                                )";
                        
                        $query = $dbh->prepare($sql);
                        $query->bindParam(':empid', $data['empid'], PDO::PARAM_INT);
                        $query->bindParam(':month', $month, PDO::PARAM_INT);
                        $query->bindParam(':year', $year, PDO::PARAM_INT);
                        $query->bindParam(':base_salary', $data['base_salary'], PDO::PARAM_STR);
                        $query->bindParam(':total_allowances', $data['total_allowances'], PDO::PARAM_STR);
                        $query->bindParam(':hra', $data['hra'], PDO::PARAM_STR);
                        $query->bindParam(':medical', $data['medical'], PDO::PARAM_STR);
                        $query->bindParam(':transport', $data['transport'], PDO::PARAM_STR);
                        $query->bindParam(':bonus', $data['bonus'], PDO::PARAM_STR);
                        $query->bindParam(':other_allowances', $data['other_allowances'], PDO::PARAM_STR);
                        $query->bindParam(':working_days', $data['working_days'], PDO::PARAM_INT);
                        $query->bindParam(':present_days', $data['present_days'], PDO::PARAM_INT);
                        $query->bindParam(':absent_days', $data['absent_days'], PDO::PARAM_INT);
                        $query->bindParam(':late_days', $data['late_days'], PDO::PARAM_INT);
                        $query->bindParam(':leave_days', $data['leave_days'], PDO::PARAM_INT);
                        $query->bindParam(':paid_leave_days', $data['paid_leave_days'], PDO::PARAM_INT);
                        $query->bindParam(':unpaid_leave_days', $data['unpaid_leave_days'], PDO::PARAM_INT);
                        $query->bindParam(':absent_deduction', $data['absent_deduction'], PDO::PARAM_STR);
                        $query->bindParam(':late_deduction', $data['late_deduction'], PDO::PARAM_STR);
                        $query->bindParam(':unpaid_leave_deduction', $data['unpaid_leave_deduction'], PDO::PARAM_STR);
                        $query->bindParam(':other_deductions', $data['other_deductions'], PDO::PARAM_STR);
                        $query->bindParam(':total_deductions', $data['total_deductions'], PDO::PARAM_STR);
                        $query->bindParam(':gross_salary', $data['gross_salary'], PDO::PARAM_STR);
                        $query->bindParam(':net_salary', $data['net_salary'], PDO::PARAM_STR);
                        
                        if ($query->execute()) {
                            $saved_count++;
                        }
                    } catch (PDOException $e) {
                        // Skip on error, continue with next
                        continue;
                    }
                }
                
                if ($saved_count > 0) {
                    $_SESSION['success'] = "Payroll generated successfully for $saved_count employee(s)";
                    header('location: manage-payroll.php');
                    exit();
                } else {
                    $error = "Payroll already exists for selected employee(s) for this period";
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Fetch employees for dropdown
try {
    $sql = "SELECT e.id, e.EmpId, e.FirstName, e.LastName 
            FROM tblemployees e
            INNER JOIN tblsalary s ON e.id = s.empid
            WHERE e.Status = 1
            ORDER BY e.FirstName ASC";
    $query = $dbh->prepare($sql);
    $query->execute();
    $employeeList = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employeeList = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin | Generate Payroll</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
      min-height: 100vh;
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
      color: #71C9CE;
    }

    .page-title h3 {
      margin: 0;
      font-weight: 600;
      font-size: 26px;
      color: #71C9CE;
    }

    .card {
      border: none;
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
    }

    .form-label {
      font-weight: 500;
      color: #555;
      margin-bottom: 8px;
    }

    .form-control, .form-select {
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 10px 15px;
    }

    .form-control:focus, .form-select:focus {
      border-color: #71C9CE;
      box-shadow: 0 0 0 0.2rem rgba(113, 201, 206, 0.25);
    }

    .btn-primary {
      background: #71C9CE;
      border: none;
      padding: 12px 30px;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s;
    }

    .btn-primary:hover {
      background: #5fb3b8;
    }

    .btn-success {
      background: #28a745;
      border: none;
      padding: 12px 30px;
      border-radius: 8px;
      font-weight: 600;
    }

    .alert {
      border-radius: 8px;
      padding: 15px;
    }

    .table {
      font-size: 14px;
    }

    .table thead {
      background-color: #71C9CE;
      color: white;
    }

    .table thead th {
      border: none;
      padding: 12px;
      font-weight: 600;
      font-size: 13px;
    }

    .table tbody td {
      padding: 10px;
      vertical-align: middle;
    }

    .preview-title {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      border-left: 4px solid #71C9CE;
    }

    .stat-box {
      background: #e7f9fa;
      padding: 15px;
      border-radius: 8px;
      text-align: center;
      margin-bottom: 10px;
    }

    .stat-box h4 {
      margin: 0;
      color: #71C9CE;
      font-size: 24px;
    }

    .stat-box p {
      margin: 5px 0 0 0;
      font-size: 13px;
      color: #666;
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

<!-- Sidebar (reused from previous pages) -->
<div id="sidebar">
  <div class="sidebar-header">
    <img src="../assets/images/profile-image.png" alt="Admin">
    <p>Admin</p>
  </div>

  <div class="list-group">
    <a href="dashboard.php" class="list-group-item list-group-item-action">
      <span class="material-icons">dashboard</span> Dashboard
    </a>

    <a class="list-group-item list-group-item-action" data-bs-toggle="collapse" href="#deptMenu">
      <span class="material-icons">apartment</span> Department
      <span class="ms-auto">›</span>
    </a>
    <div class="collapse" id="deptMenu">
      <a href="adddepartment.php" class="list-group-item list-group-item-action">Add Department</a>
      <a href="managedepartments.php" class="list-group-item list-group-item-action">Manage Department</a>
    </div>

    <a class="list-group-item list-group-item-action" data-bs-toggle="collapse" href="#designationMenu">
      <span class="material-icons">badge</span> Designation
      <span class="ms-auto">›</span>
    </a>
    <div class="collapse" id="designationMenu">
      <a href="adddesignation.php" class="list-group-item list-group-item-action">Add Designation</a>
      <a href="managedesignation.php" class="list-group-item list-group-item-action">Manage Designation</a>
    </div>

    <a class="list-group-item list-group-item-action" data-bs-toggle="collapse" href="#leaveTypeMenu">
      <span class="material-icons">event_note</span> Leave Type
      <span class="ms-auto">›</span>
    </a>
    <div class="collapse" id="leaveTypeMenu">
      <a href="addleavetype.php" class="list-group-item list-group-item-action">Add Leave Type</a>
      <a href="manageleavetype.php" class="list-group-item list-group-item-action">Manage Leave Type</a>
    </div>

    <a class="list-group-item list-group-item-action" data-bs-toggle="collapse" href="#employeeMenu">
      <span class="material-icons">people</span> Employees
      <span class="ms-auto">›</span>
    </a>
    <div class="collapse" id="employeeMenu">
      <a href="addemployee.php" class="list-group-item list-group-item-action">Add Employee</a>
      <a href="manageemployee.php" class="list-group-item list-group-item-action">Manage Employee</a>
    </div>

    <a class="list-group-item list-group-item-action" data-bs-toggle="collapse" href="#leaveMgmtMenu">
      <span class="material-icons">assignment</span> Leave Management
      <span class="ms-auto">›</span>
    </a>
    <div class="collapse" id="leaveMgmtMenu">
      <a href="leaves.php" class="list-group-item list-group-item-action">All Leaves</a>
      <a href="pending-leavehistory.php" class="list-group-item list-group-item-action">Pending Leaves</a>
      <a href="approvedleave-history.php" class="list-group-item list-group-item-action">Approved Leaves</a>
      <a href="notapproved-leaves.php" class="list-group-item list-group-item-action">Not Approved Leaves</a>
    </div>

    <a class="list-group-item list-group-item-action" data-bs-toggle="collapse" href="#attendanceMenu">
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


    <a href="changepassword.php" class="list-group-item list-group-item-action">
      <span class="material-icons">lock</span> Change Password
    </a>
    <a href="logout.php" class="list-group-item list-group-item-action">
      <span class="material-icons">logout</span> Sign Out
    </a>
  </div>
</div>

<!-- Main Content -->
<div class="main-content" id="main-content">
  <div class="page-title">
    <span class="material-icons">calculate</span>
    <h3>Generate Monthly Payroll</h3>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <?php echo htmlspecialchars($error); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (!$preview): ?>
  <div class="card">
    <div class="card-body p-4">
      <form method="POST" action="">
        <div class="row mb-3">
          <div class="col-md-4">
            <label for="month" class="form-label">Select Month <span class="text-danger">*</span></label>
            <select class="form-select" id="month" name="month" required>
              <option value="">-- Select Month --</option>
              <?php for($m=1; $m<=12; $m++): ?>
                <option value="<?php echo $m; ?>" <?php echo (date('n') == $m) ? 'selected' : ''; ?>>
                  <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label for="year" class="form-label">Select Year <span class="text-danger">*</span></label>
            <select class="form-select" id="year" name="year" required>
              <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo (date('Y') == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label for="empid" class="form-label">Select Employee</label>
            <select class="form-select" id="empid" name="empid">
              <option value="all">All Employees</option>
              <?php foreach ($employeeList as $emp): ?>
                <option value="<?php echo $emp['id']; ?>">
                  <?php echo htmlspecialchars($emp['FirstName'] . ' ' . $emp['LastName'] . ' (' . $emp['EmpId'] . ')'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" name="action" value="preview" class="btn btn-primary">
            <span class="material-icons" style="vertical-align: middle; font-size: 18px;">visibility</span>
            Preview
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php else: ?>
  <!-- Preview Results -->
  <div class="preview-title">
    <h5 class="mb-0">
      <span class="material-icons" style="vertical-align: middle;">preview</span>
      Payroll Preview - <?php echo date('F Y', mktime(0, 0, 0, $_POST['month'], 1, $_POST['year'])); ?>
    </h5>
  </div>

  <div class="row mb-4">
    <div class="col-md-3">
      <div class="stat-box">
        <h4><?php echo count($payrollData); ?></h4>
        <p>Total Employees</p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-box">
        <h4>৳<?php echo number_format(array_sum(array_column($payrollData, 'gross_salary')), 2); ?></h4>
        <p>Total Gross Salary</p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-box">
        <h4>৳<?php echo number_format(array_sum(array_column($payrollData, 'total_deductions')), 2); ?></h4>
        <p>Total Deductions</p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-box">
        <h4>৳<?php echo number_format(array_sum(array_column($payrollData, 'net_salary')), 2); ?></h4>
        <p>Total Net Salary</p>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Employee</th>
              <th>Base Salary</th>
              <th>Allowances</th>
              <th>Gross</th>
              <th>Present</th>
              <th>Absent</th>
              <th>Late</th>
              <th>Leave</th>
              <th>Deductions</th>
              <th>Net Salary</th>
            </tr>
          </thead>
          <tbody>
            <?php $cnt = 1; foreach ($payrollData as $data): ?>
            <tr>
              <td><?php echo $cnt++; ?></td>
              <td>
                <strong><?php echo htmlspecialchars($data['name']); ?></strong><br>
                <small class="text-muted"><?php echo htmlspecialchars($data['emp_code']); ?></small>
              </td>
              <td>৳<?php echo number_format($data['base_salary'], 2); ?></td>
              <td>৳<?php echo number_format($data['total_allowances'], 2); ?></td>
              <td><strong>৳<?php echo number_format($data['gross_salary'], 2); ?></strong></td>
              <td><?php echo $data['present_days']; ?>/<?php echo $data['working_days']; ?></td>
              <td><?php echo $data['absent_days']; ?></td>
              <td><?php echo $data['late_days']; ?></td>
              <td><?php echo $data['leave_days']; ?></td>
              <td class="text-danger">৳<?php echo number_format($data['total_deductions'], 2); ?></td>
              <td><strong class="text-success">৳<?php echo number_format($data['net_salary'], 2); ?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <form method="POST" action="" class="mt-4">
    <input type="hidden" name="month" value="<?php echo $_POST['month']; ?>">
    <input type="hidden" name="year" value="<?php echo $_POST['year']; ?>">
    <input type="hidden" name="empid" value="<?php echo $_POST['empid']; ?>">
    <div class="d-flex gap-2">
      <button type="submit" name="action" value="generate" class="btn btn-success">
        <span class="material-icons" style="vertical-align: middle; font-size: 18px;">save</span>
        Confirm & Generate Payroll
      </button>
      <a href="generate-payroll.php" class="btn btn-primary">
        <span class="material-icons" style="vertical-align: middle; font-size: 18px;">arrow_back</span>
        Back
      </a>
    </div>
  </form>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('menu-toggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('main-content').classList.toggle('collapsed');
  });
</script>
</body>
</html>
