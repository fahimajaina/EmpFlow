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
    $deduction_type = $_POST['deduction_type'];
    $late_count_as_absent = intval($_POST['late_count_as_absent']);
    $fixed_late_deduction = floatval($_POST['fixed_late_deduction']);

    try {
        $sql = "UPDATE tblpayrollsettings SET 
                deduction_type = :deduction_type,
                late_count_as_absent = :late_count_as_absent,
                fixed_late_deduction = :fixed_late_deduction,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = 1";
        
        $query = $dbh->prepare($sql);
        $query->bindParam(':deduction_type', $deduction_type, PDO::PARAM_STR);
        $query->bindParam(':late_count_as_absent', $late_count_as_absent, PDO::PARAM_INT);
        $query->bindParam(':fixed_late_deduction', $fixed_late_deduction, PDO::PARAM_STR);
        
        if ($query->execute()) {
            $success = "Payroll settings updated successfully";
        } else {
            $error = "Failed to update settings";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Fetch current settings
try {
    $sql = "SELECT * FROM tblpayrollsettings WHERE id = 1";
    $query = $dbh->prepare($sql);
    $query->execute();
    $settings = $query->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        // Insert default settings if not exists
        $sql = "INSERT INTO tblpayrollsettings (id, late_count_as_absent, fixed_late_deduction, deduction_type) 
                VALUES (1, 3, 0.00, 'count_as_absent')";
        $dbh->exec($sql);
        
        $query = $dbh->prepare("SELECT * FROM tblpayrollsettings WHERE id = 1");
        $query->execute();
        $settings = $query->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Error fetching settings: " . $e->getMessage();
    $settings = ['deduction_type' => 'count_as_absent', 'late_count_as_absent' => 3, 'fixed_late_deduction' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin | Payroll Settings</title>
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

    .info-box {
      background: #e7f9fa;
      border-left: 4px solid #71C9CE;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
    }

    .info-box .material-icons {
      color: #71C9CE;
      vertical-align: middle;
      margin-right: 5px;
    }

    .deduction-option {
      border: 2px solid #ddd;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 15px;
      transition: all 0.3s;
    }

    .deduction-option.active {
      border-color: #71C9CE;
      background: #f0fbfd;
    }

    .form-check-input:checked {
      background-color: #71C9CE;
      border-color: #71C9CE;
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
    <span class="material-icons">settings</span>
    <h3>Payroll Settings</h3>
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

  <div class="info-box">
    <span class="material-icons">info</span>
    <strong>Note:</strong> These settings will be used when generating monthly payroll. Late attendance deductions will be calculated based on the method you choose below.
  </div>

  <div class="card">
    <div class="card-body p-4">
      <form method="POST" action="" id="settingsForm">
        <div class="section-title">
          <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">schedule</span>
          Late Attendance Deduction Method
        </div>

        <div class="deduction-option <?php echo $settings['deduction_type'] == 'count_as_absent' ? 'active' : ''; ?>" id="option1">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="deduction_type" id="count_as_absent" 
                   value="count_as_absent" <?php echo $settings['deduction_type'] == 'count_as_absent' ? 'checked' : ''; ?>>
            <label class="form-check-label" for="count_as_absent">
              <strong>Count as Absent (Recommended)</strong>
            </label>
          </div>
          <p class="mb-2 mt-2 text-muted">Example: 3 late days = 1 absent day</p>
          <div class="mt-3" id="countInput" style="display: <?php echo $settings['deduction_type'] == 'count_as_absent' ? 'block' : 'none'; ?>;">
            <label for="late_count_as_absent" class="form-label">Number of late days equal to 1 absent:</label>
            <input type="number" class="form-control" id="late_count_as_absent" name="late_count_as_absent" 
                   value="<?php echo htmlspecialchars($settings['late_count_as_absent']); ?>" min="1" max="10" style="max-width: 200px;">
            <small class="text-muted">Enter a number between 1-10</small>
          </div>
        </div>

        <div class="deduction-option <?php echo $settings['deduction_type'] == 'fixed_amount' ? 'active' : ''; ?>" id="option2">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="deduction_type" id="fixed_amount" 
                   value="fixed_amount" <?php echo $settings['deduction_type'] == 'fixed_amount' ? 'checked' : ''; ?>>
            <label class="form-check-label" for="fixed_amount">
              <strong>Fixed Amount Per Late</strong>
            </label>
          </div>
          <p class="mb-2 mt-2 text-muted">Example: Deduct 100 BDT for each late arrival</p>
          <div class="mt-3" id="amountInput" style="display: <?php echo $settings['deduction_type'] == 'fixed_amount' ? 'block' : 'none'; ?>;">
            <label for="fixed_late_deduction" class="form-label">Fixed deduction amount (BDT):</label>
            <input type="number" step="0.01" class="form-control" id="fixed_late_deduction" name="fixed_late_deduction" 
                   value="<?php echo htmlspecialchars($settings['fixed_late_deduction']); ?>" min="0" style="max-width: 200px;">
            <small class="text-muted">Enter amount to deduct per late day</small>
          </div>
        </div>

        <div class="mt-4">
          <button type="submit" class="btn btn-primary">
            <span class="material-icons" style="vertical-align: middle; font-size: 18px;">save</span>
            Save Settings
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card mt-4">
    <div class="card-body p-4">
      <h5 class="mb-3">Deduction Calculation Examples</h5>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Scenario</th>
              <th>Late Days</th>
              <th>Method: Count as Absent (3 late = 1 absent)</th>
              <th>Method: Fixed Amount (100 BDT per late)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Employee A</td>
              <td>2 late days</td>
              <td>0 absent days = 0 BDT deduction</td>
              <td>2 × 100 = 200 BDT deduction</td>
            </tr>
            <tr>
              <td>Employee B</td>
              <td>3 late days</td>
              <td>1 absent day = (Base Salary / Working Days) × 1</td>
              <td>3 × 100 = 300 BDT deduction</td>
            </tr>
            <tr>
              <td>Employee C</td>
              <td>5 late days</td>
              <td>1 absent day = (Base Salary / Working Days) × 1</td>
              <td>5 × 100 = 500 BDT deduction</td>
            </tr>
            <tr>
              <td>Employee D</td>
              <td>6 late days</td>
              <td>2 absent days = (Base Salary / Working Days) × 2</td>
              <td>6 × 100 = 600 BDT deduction</td>
            </tr>
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

  // Handle radio button changes
  document.querySelectorAll('input[name="deduction_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
      // Update option boxes
      document.querySelectorAll('.deduction-option').forEach(box => {
        box.classList.remove('active');
      });
      
      if (this.value === 'count_as_absent') {
        document.getElementById('option1').classList.add('active');
        document.getElementById('countInput').style.display = 'block';
        document.getElementById('amountInput').style.display = 'none';
      } else {
        document.getElementById('option2').classList.add('active');
        document.getElementById('countInput').style.display = 'none';
        document.getElementById('amountInput').style.display = 'block';
      }
    });
  });
</script>
</body>
</html>
