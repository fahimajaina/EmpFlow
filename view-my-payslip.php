<?php
session_start();
include('include/config.php');

// Check if employee is logged in
if (!isset($_SESSION['eid'])) {
    header('location: index.php');
    exit();
}

$empid = $_SESSION['eid'];
$error = '';
$payslip = null;

// Fetch employee details
$sql = "SELECT FirstName, LastName, EmpId FROM tblemployees WHERE id = :empid";
$query = $dbh->prepare($sql);
$query->bindParam(':empid', $empid, PDO::PARAM_INT);
$query->execute();
$employee = $query->fetch(PDO::FETCH_ASSOC);

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    try {
        $sql = "SELECT p.*, e.EmpId, e.FirstName, e.LastName, e.EmailId, e.Phonenumber,
                       d.DepartmentName, des.DesignationName
                FROM tblpayroll p
                INNER JOIN tblemployees e ON p.empid = e.id
                LEFT JOIN tbldepartments d ON e.Department = d.id
                LEFT JOIN tbldesignation des ON e.designationid = des.id
                WHERE p.id = :id AND p.empid = :empid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':id', $id, PDO::PARAM_INT);
        $query->bindParam(':empid', $empid, PDO::PARAM_INT);
        $query->execute();
        $payslip = $query->fetch(PDO::FETCH_ASSOC);
        
        if (!$payslip) {
            $error = "Payslip not found or access denied";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
} else {
    $error = "Invalid payslip ID";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Payslip - <?php echo $payslip ? date('F Y', mktime(0, 0, 0, $payslip['month'], 1, $payslip['year'])) : ''; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #eef9fa;
      color: #333;
    }

    .payslip-container {
      max-width: 900px;
      margin: 40px auto;
      background: white;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    }

    .payslip-header {
      text-align: center;
      border-bottom: 3px solid #71C9CE;
      padding-bottom: 20px;
      margin-bottom: 30px;
    }

    .payslip-header h1 {
      color: #71C9CE;
      font-weight: 700;
      font-size: 32px;
      margin: 0;
    }

    .payslip-header p {
      color: #666;
      margin: 5px 0;
      font-size: 14px;
    }

    .info-section {
      margin-bottom: 30px;
    }

    .info-row {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
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
      text-align: right;
    }

    .section-title {
      background: #71C9CE;
      color: white;
      padding: 12px 20px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 16px;
      margin-bottom: 15px;
      margin-top: 30px;
    }

    .salary-table {
      width: 100%;
      margin-bottom: 20px;
    }

    .salary-table td {
      padding: 10px 15px;
      border-bottom: 1px solid #f0f0f0;
    }

    .salary-table tr:last-child td {
      border-bottom: none;
    }

    .salary-label {
      font-weight: 500;
      color: #555;
    }

    .salary-amount {
      text-align: right;
      font-weight: 600;
      color: #333;
    }

    .total-row {
      background: #f8f9fa;
      font-size: 18px;
      font-weight: 700;
    }

    .total-row td {
      padding: 15px !important;
    }

    .net-salary-box {
      background: linear-gradient(135deg, #71C9CE 0%, #5fb3b8 100%);
      color: white;
      padding: 25px;
      border-radius: 12px;
      text-align: center;
      margin-top: 30px;
    }

    .net-salary-box h3 {
      margin: 0 0 10px 0;
      font-size: 18px;
      font-weight: 600;
      opacity: 0.9;
    }

    .net-salary-box h2 {
      margin: 0;
      font-size: 36px;
      font-weight: 700;
    }

    .action-buttons {
      text-align: center;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 2px solid #f0f0f0;
    }

    .btn-print {
      background: #71C9CE;
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 8px;
      font-weight: 600;
      margin: 0 10px;
      transition: all 0.3s;
    }

    .btn-print:hover {
      background: #5fb3b8;
      color: white;
    }

    .btn-back {
      background: #6c757d;
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 8px;
      font-weight: 600;
      margin: 0 10px;
      transition: all 0.3s;
    }

    .btn-back:hover {
      background: #5a6268;
      color: white;
    }

    @media print {
      body {
        background: white;
      }
      .action-buttons {
        display: none;
      }
      .payslip-container {
        box-shadow: none;
        margin: 0;
        padding: 20px;
      }
    }

    .attendance-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 15px;
      margin-bottom: 20px;
    }

    .attendance-card {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      text-align: center;
    }

    .attendance-card h4 {
      margin: 0;
      font-size: 28px;
      font-weight: 700;
      color: #71C9CE;
    }

    .attendance-card p {
      margin: 5px 0 0 0;
      font-size: 13px;
      color: #666;
    }
  </style>
</head>
<body>

<?php if ($error): ?>
  <div class="payslip-container">
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <div class="text-center">
      <a href="my-payslips.php" class="btn-back">Back to My Payslips</a>
    </div>
  </div>
<?php else: ?>

<div class="payslip-container">
  <!-- Header -->
  <div class="payslip-header">
    <h1>PAYSLIP</h1>
    <p>Pay Period: <?php echo date('F Y', mktime(0, 0, 0, $payslip['month'], 1, $payslip['year'])); ?></p>
    <p>Generated on: <?php echo date('d M Y', strtotime($payslip['generated_at'])); ?></p>
  </div>

  <!-- Employee Information -->
  <div class="info-section">
    <div class="info-row">
      <div class="info-label">Employee ID:</div>
      <div class="info-value"><?php echo htmlspecialchars($payslip['EmpId']); ?></div>
    </div>
    <div class="info-row">
      <div class="info-label">Employee Name:</div>
      <div class="info-value"><?php echo htmlspecialchars($payslip['FirstName'] . ' ' . $payslip['LastName']); ?></div>
    </div>
    <div class="info-row">
      <div class="info-label">Department:</div>
      <div class="info-value"><?php echo htmlspecialchars($payslip['DepartmentName']); ?></div>
    </div>
    <div class="info-row">
      <div class="info-label">Designation:</div>
      <div class="info-value"><?php echo htmlspecialchars($payslip['DesignationName']); ?></div>
    </div>
    <div class="info-row">
      <div class="info-label">Email:</div>
      <div class="info-value"><?php echo htmlspecialchars($payslip['EmailId']); ?></div>
    </div>
  </div>

  <!-- Attendance Summary -->
  <div class="section-title">
    <span class="material-icons" style="vertical-align: middle; font-size: 20px;">event_available</span>
    Attendance Summary
  </div>
  <div class="attendance-grid">
    <div class="attendance-card">
      <h4><?php echo $payslip['working_days']; ?></h4>
      <p>Working Days</p>
    </div>
    <div class="attendance-card">
      <h4><?php echo $payslip['present_days']; ?></h4>
      <p>Present Days</p>
    </div>
    <div class="attendance-card">
      <h4><?php echo $payslip['absent_days']; ?></h4>
      <p>Absent Days</p>
    </div>
    <div class="attendance-card">
      <h4><?php echo $payslip['late_days']; ?></h4>
      <p>Late Days</p>
    </div>
  </div>

  <div class="attendance-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="attendance-card">
      <h4><?php echo $payslip['leave_days']; ?></h4>
      <p>Total Leave Days</p>
    </div>
    <div class="attendance-card">
      <h4><?php echo $payslip['paid_leave_days']; ?></h4>
      <p>Paid Leave Days</p>
    </div>
    <div class="attendance-card">
      <h4><?php echo $payslip['unpaid_leave_days']; ?></h4>
      <p>Unpaid Leave Days</p>
    </div>
  </div>

  <!-- Earnings -->
  <div class="section-title">
    <span class="material-icons" style="vertical-align: middle; font-size: 20px;">add_circle</span>
    Earnings
  </div>
  <table class="salary-table">
    <tr>
      <td class="salary-label">Base Salary</td>
      <td class="salary-amount">৳<?php echo number_format($payslip['base_salary'], 2); ?></td>
    </tr>
    <?php if ($payslip['hra'] > 0): ?>
    <tr>
      <td class="salary-label">House Rent Allowance (HRA)</td>
      <td class="salary-amount">৳<?php echo number_format($payslip['hra'], 2); ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($payslip['medical'] > 0): ?>
    <tr>
      <td class="salary-label">Medical Allowance</td>
      <td class="salary-amount">৳<?php echo number_format($payslip['medical'], 2); ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($payslip['transport'] > 0): ?>
    <tr>
      <td class="salary-label">Transport Allowance</td>
      <td class="salary-amount">৳<?php echo number_format($payslip['transport'], 2); ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($payslip['bonus'] > 0): ?>
    <tr>
      <td class="salary-label">Bonus</td>
      <td class="salary-amount">৳<?php echo number_format($payslip['bonus'], 2); ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($payslip['other_allowances'] > 0): ?>
    <tr>
      <td class="salary-label">Other Allowances</td>
      <td class="salary-amount">৳<?php echo number_format($payslip['other_allowances'], 2); ?></td>
    </tr>
    <?php endif; ?>
    <tr class="total-row">
      <td>Gross Salary</td>
      <td class="text-end">৳<?php echo number_format($payslip['gross_salary'], 2); ?></td>
    </tr>
  </table>

  <!-- Deductions -->
  <div class="section-title">
    <span class="material-icons" style="vertical-align: middle; font-size: 20px;">remove_circle</span>
    Deductions
  </div>
  <table class="salary-table">
    <?php if ($payslip['absent_deduction'] > 0): ?>
    <tr>
      <td class="salary-label">Absent Days Deduction (<?php echo $payslip['absent_days']; ?> days)</td>
      <td class="salary-amount text-danger">৳<?php echo number_format($payslip['absent_deduction'], 2); ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($payslip['late_deduction'] > 0): ?>
    <tr>
      <td class="salary-label">Late Attendance Deduction (<?php echo $payslip['late_days']; ?> times)</td>
      <td class="salary-amount text-danger">৳<?php echo number_format($payslip['late_deduction'], 2); ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($payslip['unpaid_leave_deduction'] > 0): ?>
    <tr>
      <td class="salary-label">Unpaid Leave Deduction (<?php echo $payslip['unpaid_leave_days']; ?> days)</td>
      <td class="salary-amount text-danger">৳<?php echo number_format($payslip['unpaid_leave_deduction'], 2); ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($payslip['other_deductions'] > 0): ?>
    <tr>
      <td class="salary-label">Other Deductions</td>
      <td class="salary-amount text-danger">৳<?php echo number_format($payslip['other_deductions'], 2); ?></td>
    </tr>
    <?php endif; ?>
    <tr class="total-row">
      <td>Total Deductions</td>
      <td class="text-end text-danger">৳<?php echo number_format($payslip['total_deductions'], 2); ?></td>
    </tr>
  </table>

  <!-- Net Salary -->
  <div class="net-salary-box">
    <h3>NET SALARY</h3>
    <h2>৳<?php echo number_format($payslip['net_salary'], 2); ?></h2>
  </div>

  <!-- Action Buttons -->
  <div class="action-buttons">
    <button onclick="window.print()" class="btn-print">
      <span class="material-icons" style="vertical-align: middle; font-size: 18px;">print</span>
      Print Payslip
    </button>
    <a href="my-payslips.php" class="btn-back">
      <span class="material-icons" style="vertical-align: middle; font-size: 18px;">arrow_back</span>
      Back to My Payslips
    </a>
  </div>
</div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
