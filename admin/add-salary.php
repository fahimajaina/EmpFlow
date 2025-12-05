<?php
session_start();
require_once('includes/config.php');

if (!isset($_SESSION['alogin'])) {
    header('location:index.php');
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $empid = intval($_POST['empid']);
    $base_salary = floatval($_POST['base_salary']);
    $hra = !empty($_POST['hra']) ? floatval($_POST['hra']) : NULL;
    $medical = !empty($_POST['medical']) ? floatval($_POST['medical']) : NULL;
    $transport = !empty($_POST['transport']) ? floatval($_POST['transport']) : NULL;
    $bonus = !empty($_POST['bonus']) ? floatval($_POST['bonus']) : NULL;
    $other_allowances = !empty($_POST['other_allowances']) ? floatval($_POST['other_allowances']) : NULL;

    try {
        // Check if salary already exists for this employee
        $checkSql = "SELECT id FROM tblsalary WHERE empid = :empid";
        $checkQuery = $dbh->prepare($checkSql);
        $checkQuery->bindParam(':empid', $empid, PDO::PARAM_INT);
        $checkQuery->execute();
        
        if ($checkQuery->rowCount() > 0) {
            $error = "Salary information already exists for this employee. Please use Edit option.";
        } else {
            // Insert new salary record
            $sql = "INSERT INTO tblsalary (empid, base_salary, hra, medical, transport, bonus, other_allowances) 
                    VALUES (:empid, :base_salary, :hra, :medical, :transport, :bonus, :other_allowances)";
            $query = $dbh->prepare($sql);
            $query->bindParam(':empid', $empid, PDO::PARAM_INT);
            $query->bindParam(':base_salary', $base_salary, PDO::PARAM_STR);
            $query->bindParam(':hra', $hra, PDO::PARAM_STR);
            $query->bindParam(':medical', $medical, PDO::PARAM_STR);
            $query->bindParam(':transport', $transport, PDO::PARAM_STR);
            $query->bindParam(':bonus', $bonus, PDO::PARAM_STR);
            $query->bindParam(':other_allowances', $other_allowances, PDO::PARAM_STR);
            
            if ($query->execute()) {
                $success = "Salary information added successfully";
            } else {
                $error = "Failed to add salary information";
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Fetch employees without salary info
try {
    $sql = "SELECT e.id, e.EmpId, e.FirstName, e.LastName, d.DepartmentName 
            FROM tblemployees e 
            LEFT JOIN tbldepartments d ON e.Department = d.id 
            LEFT JOIN tblsalary s ON e.id = s.empid 
            WHERE s.id IS NULL AND e.Status = 1
            ORDER BY e.FirstName ASC";
    $query = $dbh->prepare($sql);
    $query->execute();
    $employees = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching employees: " . $e->getMessage();
    $employees = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin | Add Employee Salary</title>
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

    .btn-secondary {
      background: #6c757d;
      border: none;
      padding: 12px 30px;
      border-radius: 8px;
      font-weight: 600;
    }

    .alert {
      border-radius: 8px;
      padding: 15px;
    }

    .section-title {
      font-size: 18px;
      font-weight: 600;
      color: #48A6A7;
      margin-top: 20px;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 2px solid #e0e0e0;
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
    <span class="material-icons">add_circle</span>
    <h3>Add Employee Salary</h3>
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
      <form method="POST" action="">
        <div class="row mb-3">
          <div class="col-md-12">
            <label for="empid" class="form-label">Select Employee <span class="text-danger">*</span></label>
            <select class="form-select" id="empid" name="empid" required>
              <option value="">-- Select Employee --</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?php echo $emp['id']; ?>">
                  <?php echo htmlspecialchars($emp['FirstName'] . ' ' . $emp['LastName'] . ' (' . $emp['EmpId'] . ') - ' . $emp['DepartmentName']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (empty($employees)): ?>
              <small class="text-muted">All active employees already have salary information assigned.</small>
            <?php endif; ?>
          </div>
        </div>

        <div class="section-title">
          <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">attach_money</span>
          Base Salary
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label for="base_salary" class="form-label">Base Salary (BDT) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" class="form-control" id="base_salary" name="base_salary" placeholder="e.g., 25000.00" required>
          </div>
        </div>

        <div class="section-title">
          <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">card_giftcard</span>
          Allowances (Optional)
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label for="hra" class="form-label">House Rent Allowance (HRA)</label>
            <input type="number" step="0.01" class="form-control" id="hra" name="hra" placeholder="e.g., 5000.00">
          </div>
          <div class="col-md-6">
            <label for="medical" class="form-label">Medical Allowance</label>
            <input type="number" step="0.01" class="form-control" id="medical" name="medical" placeholder="e.g., 2000.00">
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label for="transport" class="form-label">Transport Allowance</label>
            <input type="number" step="0.01" class="form-control" id="transport" name="transport" placeholder="e.g., 3000.00">
          </div>
          <div class="col-md-6">
            <label for="bonus" class="form-label">Bonus</label>
            <input type="number" step="0.01" class="form-control" id="bonus" name="bonus" placeholder="e.g., 5000.00">
          </div>
        </div>

        <div class="row mb-4">
          <div class="col-md-6">
            <label for="other_allowances" class="form-label">Other Allowances</label>
            <input type="number" step="0.01" class="form-control" id="other_allowances" name="other_allowances" placeholder="e.g., 1000.00">
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <span class="material-icons" style="vertical-align: middle; font-size: 18px;">save</span>
            Save Salary Information
          </button>
          <a href="manage-salary.php" class="btn btn-secondary">
            <span class="material-icons" style="vertical-align: middle; font-size: 18px;">cancel</span>
            Cancel
          </a>
        </div>
      </form>
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
