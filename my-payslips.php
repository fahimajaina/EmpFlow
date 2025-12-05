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

// Filter parameters
$filterYear = isset($_GET['year']) ? intval($_GET['year']) : 0;

// Get payslips
try {
    $sql = "SELECT p.*, e.EmpId, e.FirstName, e.LastName
            FROM tblpayroll p
            INNER JOIN tblemployees e ON p.empid = e.id
            WHERE p.empid = :empid";
    
    if ($filterYear > 0) {
        $sql .= " AND p.year = $filterYear";
    }
    
    $sql .= " ORDER BY p.year DESC, p.month DESC";
    
    $query = $dbh->prepare($sql);
    $query->bindParam(':empid', $empid, PDO::PARAM_INT);
    $query->execute();
    $payslips = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $payslips = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EMPFLOW | My Payslips</title>

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
      margin-bottom: 20px;
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

    .alert {
      border-radius: 12px;
      padding: 20px;
      text-align: center;
    }

    .payslip-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 15px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      transition: all 0.3s;
    }

    .payslip-card:hover {
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
      transform: translateY(-2px);
    }

    .payslip-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      padding-bottom: 15px;
      border-bottom: 2px solid #f0f0f0;
    }

    .payslip-period {
      font-size: 20px;
      font-weight: 700;
      color: #71C9CE;
    }

    .payslip-date {
      font-size: 13px;
      color: #666;
    }

    .payslip-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 15px;
      margin-bottom: 15px;
    }

    .stat-item {
      text-align: center;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 8px;
    }

    .stat-label {
      font-size: 12px;
      color: #666;
      margin-bottom: 5px;
    }

    .stat-value {
      font-size: 18px;
      font-weight: 700;
      color: #333;
    }

    .stat-value.success {
      color: #28a745;
    }

    .stat-value.danger {
      color: #dc3545;
    }

    .payslip-footer {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }

    .filter-bar {
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
    <a href="my-salary.php"><span class="material-icons">account_balance_wallet</span> My Salary</a>
    <a href="my-payslips.php"><span class="material-icons">receipt_long</span> My Payslips</a>

    <a href="logout.php"><span class="material-icons">logout</span> Sign Out</a>
  </div>
</div>

<!-- Main Content -->
<div class="main-content" id="main-content">
  <div class="page-title">
    <span class="material-icons">receipt_long</span>
    <h3>My Payslips</h3>
  </div>

  <!-- Filter -->
  <div class="filter-bar">
    <form method="GET" action="">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label" style="font-weight: 500; font-size: 14px;">Filter by Year</label>
          <select class="form-select" name="year" onchange="this.form.submit()">
            <option value="0">All Years</option>
            <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
              <option value="<?php echo $y; ?>" <?php echo ($filterYear == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
    </form>
  </div>

  <?php if (count($payslips) == 0): ?>
    <div class="alert alert-info">
      <span class="material-icons" style="font-size: 48px;">receipt_long</span>
      <h5 class="mt-3">No Payslips Available</h5>
      <p>You don't have any payslips yet. Payslips will appear here once payroll is generated by HR.</p>
    </div>
  <?php else: ?>
    
    <?php foreach ($payslips as $payslip): ?>
    <div class="payslip-card">
      <div class="payslip-header">
        <div>
          <div class="payslip-period">
            <?php echo date('F Y', mktime(0, 0, 0, $payslip['month'], 1, $payslip['year'])); ?>
          </div>
          <div class="payslip-date">
            Generated on: <?php echo date('d M Y', strtotime($payslip['generated_at'])); ?>
          </div>
        </div>
      </div>

      <div class="payslip-stats">
        <div class="stat-item">
          <div class="stat-label">Gross Salary</div>
          <div class="stat-value">৳<?php echo number_format($payslip['gross_salary'], 2); ?></div>
        </div>
        <div class="stat-item">
          <div class="stat-label">Deductions</div>
          <div class="stat-value danger">৳<?php echo number_format($payslip['total_deductions'], 2); ?></div>
        </div>
        <div class="stat-item">
          <div class="stat-label">Net Salary</div>
          <div class="stat-value success">৳<?php echo number_format($payslip['net_salary'], 2); ?></div>
        </div>
      </div>

      <div class="payslip-stats">
        <div class="stat-item">
          <div class="stat-label">Present Days</div>
          <div class="stat-value"><?php echo $payslip['present_days']; ?>/<?php echo $payslip['working_days']; ?></div>
        </div>
        <div class="stat-item">
          <div class="stat-label">Absent Days</div>
          <div class="stat-value danger"><?php echo $payslip['absent_days']; ?></div>
        </div>
        <div class="stat-item">
          <div class="stat-label">Leave Days</div>
          <div class="stat-value"><?php echo $payslip['leave_days']; ?></div>
        </div>
      </div>

      <div class="payslip-footer">
        <a href="view-my-payslip.php?id=<?php echo $payslip['id']; ?>" class="btn btn-success">
          <span class="material-icons" style="vertical-align: middle; font-size: 16px;">visibility</span>
          View Payslip
        </a>
      </div>
    </div>
    <?php endforeach; ?>

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
