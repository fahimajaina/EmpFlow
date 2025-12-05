<?php
session_start();
require_once('includes/config.php');

if (!isset($_SESSION['alogin'])) {
    header('location:index.php');
    exit();
}

$error = '';
$success = '';

// Handle delete request
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $sql = "DELETE FROM tblpayroll WHERE id = :id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':id', $id, PDO::PARAM_INT);
        $query->execute();
        $_SESSION['success'] = "Payroll record deleted successfully";
        header('location: manage-payroll.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting payroll: " . $e->getMessage();
    }
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Filter parameters
$filterMonth = isset($_GET['month']) ? intval($_GET['month']) : 0;
$filterYear = isset($_GET['year']) ? intval($_GET['year']) : 0;
$filterEmpId = isset($_GET['empid']) ? intval($_GET['empid']) : 0;

// Build query with filters
$sql = "SELECT p.*, e.EmpId, e.FirstName, e.LastName, d.DepartmentName
        FROM tblpayroll p
        INNER JOIN tblemployees e ON p.empid = e.id
        LEFT JOIN tbldepartments d ON e.Department = d.id
        WHERE 1=1";

if ($filterMonth > 0) {
    $sql .= " AND p.month = $filterMonth";
}
if ($filterYear > 0) {
    $sql .= " AND p.year = $filterYear";
}
if ($filterEmpId > 0) {
    $sql .= " AND p.empid = $filterEmpId";
}

$sql .= " ORDER BY p.year DESC, p.month DESC, e.FirstName ASC";

try {
    $query = $dbh->prepare($sql);
    $query->execute();
    $payrolls = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $payrolls = [];
}

// Get employee list for filter
try {
    $empSql = "SELECT DISTINCT e.id, e.EmpId, e.FirstName, e.LastName
               FROM tblemployees e
               INNER JOIN tblpayroll p ON e.id = p.empid
               ORDER BY e.FirstName ASC";
    $empQuery = $dbh->prepare($empSql);
    $empQuery->execute();
    $employees = $empQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employees = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin | Manage Payroll</title>
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
      padding: 10px 20px;
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
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: 500;
      font-size: 14px;
    }

    .btn-danger {
      background: #dc3545;
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: 500;
      font-size: 14px;
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

    .action-btn {
      padding: 5px 10px;
      font-size: 12px;
      margin: 2px;
    }

    .badge {
      padding: 6px 12px;
      border-radius: 6px;
      font-weight: 500;
    }

    .filter-card {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 20px;
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
    <span class="material-icons">receipt_long</span>
    <h3>Manage Payroll Records</h3>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <?php echo htmlspecialchars($error); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <?php echo htmlspecialchars($success); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Filter Section -->
  <div class="filter-card">
    <form method="GET" action="">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label" style="font-weight: 500; font-size: 14px;">Month</label>
          <select class="form-select" name="month">
            <option value="0">All Months</option>
            <?php for($m=1; $m<=12; $m++): ?>
              <option value="<?php echo $m; ?>" <?php echo ($filterMonth == $m) ? 'selected' : ''; ?>>
                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" style="font-weight: 500; font-size: 14px;">Year</label>
          <select class="form-select" name="year">
            <option value="0">All Years</option>
            <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
              <option value="<?php echo $y; ?>" <?php echo ($filterYear == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" style="font-weight: 500; font-size: 14px;">Employee</label>
          <select class="form-select" name="empid">
            <option value="0">All Employees</option>
            <?php foreach ($employees as $emp): ?>
              <option value="<?php echo $emp['id']; ?>" <?php echo ($filterEmpId == $emp['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($emp['FirstName'] . ' ' . $emp['LastName'] . ' (' . $emp['EmpId'] . ')'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">
            <span class="material-icons" style="vertical-align: middle; font-size: 16px;">filter_alt</span> Filter
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- Payroll Table -->
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="payrollTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Period</th>
              <th>Employee</th>
              <th>Department</th>
              <th>Working Days</th>
              <th>Present</th>
              <th>Absent</th>
              <th>Late</th>
              <th>Leave</th>
              <th>Gross Salary</th>
              <th>Deductions</th>
              <th>Net Salary</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($payrolls) > 0): ?>
              <?php $cnt = 1; foreach ($payrolls as $payroll): ?>
              <tr>
                <td><?php echo $cnt++; ?></td>
                <td>
                  <span class="badge bg-info">
                    <?php echo date('F Y', mktime(0, 0, 0, $payroll['month'], 1, $payroll['year'])); ?>
                  </span>
                </td>
                <td>
                  <strong><?php echo htmlspecialchars($payroll['FirstName'] . ' ' . $payroll['LastName']); ?></strong><br>
                  <small class="text-muted"><?php echo htmlspecialchars($payroll['EmpId']); ?></small>
                </td>
                <td><?php echo htmlspecialchars($payroll['DepartmentName']); ?></td>
                <td><?php echo $payroll['working_days']; ?></td>
                <td><span class="badge bg-success"><?php echo $payroll['present_days']; ?></span></td>
                <td><span class="badge bg-danger"><?php echo $payroll['absent_days']; ?></span></td>
                <td><span class="badge bg-warning"><?php echo $payroll['late_days']; ?></span></td>
                <td><span class="badge bg-primary"><?php echo $payroll['leave_days']; ?></span></td>
                <td>৳<?php echo number_format($payroll['gross_salary'], 2); ?></td>
                <td class="text-danger">৳<?php echo number_format($payroll['total_deductions'], 2); ?></td>
                <td><strong class="text-success">৳<?php echo number_format($payroll['net_salary'], 2); ?></strong></td>
                <td>
                  <a href="view-payslip.php?id=<?php echo $payroll['id']; ?>" class="btn btn-success btn-sm action-btn" title="View Payslip">
                    <span class="material-icons" style="font-size: 14px; vertical-align: middle;">visibility</span>
                  </a>
                  <a href="?delete=<?php echo $payroll['id']; ?>" class="btn btn-danger btn-sm action-btn" 
                     onclick="return confirm('Are you sure you want to delete this payroll record?');" title="Delete">
                    <span class="material-icons" style="font-size: 14px; vertical-align: middle;">delete</span>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="13" class="text-center py-4">
                  <span class="material-icons" style="font-size: 48px; color: #ddd;">receipt_long</span>
                  <p class="mt-2 mb-0 text-muted">No payroll records found</p>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
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
