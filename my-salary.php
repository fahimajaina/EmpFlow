<?php
session_start();
include('include/config.php');

// Check if employee is logged in
if (!isset($_SESSION['eid'])) {
    header('location: index.php');
    exit();
}

$empid = $_SESSION['eid'];

// Fetch employee details
$sql = "SELECT FirstName, LastName, EmpId FROM tblemployees WHERE id = :empid";
$query = $dbh->prepare($sql);
$query->bindParam(':empid', $empid, PDO::PARAM_INT);
$query->execute();
$employee = $query->fetch(PDO::FETCH_ASSOC);

// Get salary information
try {
    $sql = "SELECT s.*, e.EmpId, e.FirstName, e.LastName, d.DepartmentName, des.DesignationName
            FROM tblsalary s
            INNER JOIN tblemployees e ON s.empid = e.id
            LEFT JOIN tbldepartments d ON e.Department = d.id
            LEFT JOIN tbldesignation des ON e.designationid = des.id
            WHERE s.empid = :empid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':empid', $empid, PDO::PARAM_INT);
    $query->execute();
    $salary = $query->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $salary = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EMPFLOW | My Salary</title>

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
      margin-bottom: 20px;
    }

    .info-row {
      display: flex;
      padding: 15px 0;
      border-bottom: 1px solid #f0f0f0;
    }

    .info-row:last-child {
      border-bottom: none;
    }

    .info-label {
      font-weight: 600;
      color: #555;
      width: 40%;
    }

    .info-value {
      color: #333;
      width: 60%;
    }

    .section-header {
      background: linear-gradient(135deg, #71C9CE 0%, #5fb3b8 100%);
      color: white;
      padding: 15px 25px;
      border-radius: 12px;
      margin-bottom: 20px;
      font-weight: 600;
      font-size: 18px;
    }

    .amount-card {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 12px;
      text-align: center;
      margin-bottom: 15px;
    }

    .amount-card h3 {
      margin: 0;
      font-size: 14px;
      color: #666;
      font-weight: 500;
      margin-bottom: 8px;
    }

    .amount-card h2 {
      margin: 0;
      font-size: 32px;
      color: #71C9CE;
      font-weight: 700;
    }

    .amount-card.gross {
      background: linear-gradient(135deg, #71C9CE 0%, #5fb3b8 100%);
    }

    .amount-card.gross h3,
    .amount-card.gross h2 {
      color: white;
    }

    .alert {
      border-radius: 12px;
      padding: 20px;
      text-align: center;
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
    <a href="my-salary.php" ><span class="material-icons">account_balance_wallet</span> My Salary</a>
    <a href="my-payslips.php"><span class="material-icons">receipt_long</span> My Payslips</a>

    <a href="logout.php"><span class="material-icons">logout</span> Sign Out</a>
  </div>
</div>

<!-- Main Content -->
<div class="main-content" id="main-content">
  <div class="page-title">
    <span class="material-icons">account_balance_wallet</span>
    <h3>My Salary Information</h3>
  </div>

  <?php if (!$salary): ?>
    <div class="alert alert-info">
      <span class="material-icons" style="font-size: 48px;">info</span>
      <h5 class="mt-3">No Salary Information Available</h5>
      <p>Your salary information has not been configured yet. Please contact HR department.</p>
    </div>
  <?php else: ?>
    
    <!-- Employee Details -->
    <div class="card">
      <div class="card-body p-4">
        <div class="section-header">
          <span class="material-icons" style="vertical-align: middle; font-size: 22px;">person</span>
          Employee Information
        </div>
        <div class="info-row">
          <div class="info-label">Employee ID:</div>
          <div class="info-value"><?php echo htmlspecialchars($salary['EmpId']); ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Name:</div>
          <div class="info-value"><?php echo htmlspecialchars($salary['FirstName'] . ' ' . $salary['LastName']); ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Department:</div>
          <div class="info-value"><?php echo htmlspecialchars($salary['DepartmentName']); ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Designation:</div>
          <div class="info-value"><?php echo htmlspecialchars($salary['DesignationName']); ?></div>
        </div>
      </div>
    </div>

    <!-- Salary Components -->
    <div class="card">
      <div class="card-body p-4">
        <div class="section-header">
          <span class="material-icons" style="vertical-align: middle; font-size: 22px;">payments</span>
          Salary Components
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <div class="amount-card">
              <h3>Base Salary</h3>
              <h2>৳<?php echo number_format($salary['base_salary'], 2); ?></h2>
            </div>
          </div>
          <div class="col-md-6">
            <div class="amount-card gross">
              <h3>Gross Salary</h3>
              <h2>৳<?php 
                $gross = floatval($salary['base_salary']) + 
                         floatval($salary['hra'] ?? 0) + 
                         floatval($salary['medical'] ?? 0) + 
                         floatval($salary['transport'] ?? 0) + 
                         floatval($salary['bonus'] ?? 0) + 
                         floatval($salary['other_allowances'] ?? 0);
                echo number_format($gross, 2);
              ?></h2>
            </div>
          </div>
        </div>

        <div class="info-row">
          <div class="info-label">House Rent Allowance (HRA):</div>
          <div class="info-value">৳<?php echo number_format($salary['hra'] ?? 0, 2); ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Medical Allowance:</div>
          <div class="info-value">৳<?php echo number_format($salary['medical'] ?? 0, 2); ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Transport Allowance:</div>
          <div class="info-value">৳<?php echo number_format($salary['transport'] ?? 0, 2); ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Bonus:</div>
          <div class="info-value">৳<?php echo number_format($salary['bonus'] ?? 0, 2); ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Other Allowances:</div>
          <div class="info-value">৳<?php echo number_format($salary['other_allowances'] ?? 0, 2); ?></div>
        </div>
      </div>
    </div>

    <div class="alert alert-success">
      <span class="material-icons" style="vertical-align: middle;">info</span>
      <strong>Note:</strong> Your actual monthly salary may vary based on attendance, leaves, and deductions. 
      View your monthly payslips for detailed breakdown.
    </div>

  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('menu-toggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('main-content').classList.toggle('collapsed');
  });
</script>
</body>
</html>
