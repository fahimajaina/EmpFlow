<?php
session_start();
require_once('includes/config.php');

if (!isset($_SESSION['alogin'])) {
    header('location:index.php');
    exit();
}

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

// Handle delete
if (isset($_GET['del'])) {
    try {
        $id = intval($_GET['del']);
        
        // Check if payroll has been generated for this employee
        $checkSql = "SELECT COUNT(*) FROM tblpayroll WHERE empid = (SELECT empid FROM tblsalary WHERE id = :id)";
        $checkQuery = $dbh->prepare($checkSql);
        $checkQuery->bindParam(':id', $id, PDO::PARAM_INT);
        $checkQuery->execute();
        
        if ($checkQuery->fetchColumn() > 0) {
            $error = "Cannot delete. Payroll records exist for this employee.";
        } else {
            $sql = "DELETE FROM tblsalary WHERE id = :id";
            $query = $dbh->prepare($sql);
            $query->bindParam(':id', $id, PDO::PARAM_INT);
            if ($query->execute()) {
                $success = "Salary record deleted successfully";
            } else {
                $error = "Failed to delete salary record";
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Fetch all salary records
try {
    $sql = "SELECT s.*, e.EmpId, e.FirstName, e.LastName, d.DepartmentName,
            (s.base_salary + COALESCE(s.hra, 0) + COALESCE(s.medical, 0) + 
             COALESCE(s.transport, 0) + COALESCE(s.bonus, 0) + COALESCE(s.other_allowances, 0)) as gross_salary,
            (COALESCE(s.hra, 0) + COALESCE(s.medical, 0) + COALESCE(s.transport, 0) + 
             COALESCE(s.bonus, 0) + COALESCE(s.other_allowances, 0)) as total_allowances
            FROM tblsalary s
            INNER JOIN tblemployees e ON s.empid = e.id
            LEFT JOIN tbldepartments d ON e.Department = d.id
            ORDER BY e.FirstName ASC";
    $query = $dbh->prepare($sql);
    $query->execute();
    $salaries = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching salary records: " . $e->getMessage();
    $salaries = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin | Manage Salary</title>
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
      justify-content: space-between;
    }

    .page-title .left {
      display: flex;
      align-items: center;
      gap: 10px;
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

    .table {
      margin-bottom: 0;
    }

    .table thead {
      background-color: #71C9CE;
      color: white;
    }

    .table thead th {
      border: none;
      padding: 15px;
      font-weight: 600;
    }

    .table tbody td {
      padding: 12px 15px;
      vertical-align: middle;
    }

    .btn-action {
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 500;
      text-decoration: none;
      display: inline-block;
      margin-right: 5px;
      transition: all 0.3s;
    }

    .btn-view {
      background: #17a2b8;
      color: white;
    }

    .btn-view:hover {
      background: #138496;
      color: white;
    }

    .btn-edit {
      background: #ffc107;
      color: #000;
    }

    .btn-edit:hover {
      background: #e0a800;
      color: #000;
    }

    .btn-delete {
      background: #dc3545;
      color: white;
    }

    .btn-delete:hover {
      background: #c82333;
      color: white;
    }

    .btn-primary {
      background: #71C9CE;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s;
      text-decoration: none;
      color: white;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .btn-primary:hover {
      background: #5fb3b8;
      color: white;
    }

    .alert {
      border-radius: 8px;
      padding: 15px;
    }

    .search-box {
      max-width: 300px;
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
    <div class="left">
      <span class="material-icons">list</span>
      <h3>Manage Salary</h3>
    </div>
    <a href="add-salary.php" class="btn-primary">
      <span class="material-icons" style="font-size: 18px;">add</span>
      Add New Salary
    </a>
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

  <div class="card">
    <div class="card-body p-4">
      <input type="text" id="searchInput" class="form-control search-box" placeholder="Search by name or employee ID...">
      
      <div class="table-responsive">
        <table class="table table-hover" id="salaryTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Employee</th>
              <th>Emp ID</th>
              <th>Department</th>
              <th>Base Salary</th>
              <th>Allowances</th>
              <th>Gross Salary</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($salaries)): ?>
              <tr>
                <td colspan="8" class="text-center">No salary records found</td>
              </tr>
            <?php else: ?>
              <?php $cnt = 1; foreach ($salaries as $salary): ?>
                <tr>
                  <td><?php echo $cnt++; ?></td>
                  <td><?php echo htmlspecialchars($salary['FirstName'] . ' ' . $salary['LastName']); ?></td>
                  <td><?php echo htmlspecialchars($salary['EmpId']); ?></td>
                  <td><?php echo htmlspecialchars($salary['DepartmentName']); ?></td>
                  <td>৳<?php echo number_format($salary['base_salary'], 2); ?></td>
                  <td>৳<?php echo number_format($salary['total_allowances'], 2); ?></td>
                  <td><strong>৳<?php echo number_format($salary['gross_salary'], 2); ?></strong></td>
                  <td>
                    <a href="edit-salary.php?id=<?php echo $salary['id']; ?>" class="btn-action btn-edit">Edit</a>
                    <a href="manage-salary.php?del=<?php echo $salary['id']; ?>" class="btn-action btn-delete" 
                       onclick="return confirm('Are you sure you want to delete this salary record?');">Delete</a>
                  </td>
                </tr>
              <?php endforeach; ?>
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

  // Search functionality
  document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const table = document.getElementById('salaryTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
      const cells = rows[i].getElementsByTagName('td');
      let found = false;

      for (let j = 0; j < cells.length; j++) {
        if (cells[j].textContent.toLowerCase().indexOf(searchValue) > -1) {
          found = true;
          break;
        }
      }

      rows[i].style.display = found ? '' : 'none';
    }
  });
</script>
</body>
</html>
