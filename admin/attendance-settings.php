<?php
// Start the session
session_start();

// Include database connection
require_once('includes/config.php');

// Check if admin is logged in
if (!isset($_SESSION['alogin'])) {
    header('location:index.php');
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Fetch current settings
try {
    $sql = "SELECT * FROM tblattendancesettings ORDER BY id DESC LIMIT 1";
    $query = $dbh->prepare($sql);
    $query->execute();
    $settings = $query->fetch(PDO::FETCH_ASSOC);
    
    // If no settings exist, create default
    if (!$settings) {
        $insertSql = "INSERT INTO tblattendancesettings (work_start, work_end) 
                      VALUES ('09:00:00', '17:00:00')";
        $dbh->exec($insertSql);
        
        // Fetch again
        $query->execute();
        $settings = $query->fetch(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    $error = "Error fetching settings: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $start_hour = $_POST['start_hour'];
    $start_minute = $_POST['start_minute'];
    $start_period = $_POST['start_period'];
    $end_hour = $_POST['end_hour'];
    $end_minute = $_POST['end_minute'];
    $end_period = $_POST['end_period'];
    
    // Convert 12-hour format to 24-hour format
    $start_hour_24 = $start_hour;
    if ($start_period == 'PM' && $start_hour != 12) {
        $start_hour_24 = $start_hour + 12;
    } elseif ($start_period == 'AM' && $start_hour == 12) {
        $start_hour_24 = 0;
    }
    
    $end_hour_24 = $end_hour;
    if ($end_period == 'PM' && $end_hour != 12) {
        $end_hour_24 = $end_hour + 12;
    } elseif ($end_period == 'AM' && $end_hour == 12) {
        $end_hour_24 = 0;
    }
    
    $work_start_time = sprintf("%02d:%02d:00", $start_hour_24, $start_minute);
    $work_end_time = sprintf("%02d:%02d:00", $end_hour_24, $end_minute);
    
    // Validation (check if values are set, not if they're empty - 0 is valid for minutes)
    if (!isset($start_hour) || !isset($start_minute) || !isset($end_hour) || !isset($end_minute) || 
        $start_hour === '' || $start_minute === '' || $end_hour === '' || $end_minute === '') {
        $error = "All fields are required";
    } elseif (strtotime($work_start_time) >= strtotime($work_end_time)) {
        $error = "Work end time must be after work start time";
    } else {
        try {
            if ($settings) {
                // Update existing settings
                $sql = "UPDATE tblattendancesettings 
                        SET work_start = :work_start_time,
                            work_end = :work_end_time
                        WHERE id = :id";
                $query = $dbh->prepare($sql);
                $query->bindParam(':id', $settings['id'], PDO::PARAM_INT);
            } else {
                // Insert new settings
                $sql = "INSERT INTO tblattendancesettings 
                        (work_start, work_end) 
                        VALUES (:work_start_time, :work_end_time)";
                $query = $dbh->prepare($sql);
            }
            
            $query->bindParam(':work_start_time', $work_start_time, PDO::PARAM_STR);
            $query->bindParam(':work_end_time', $work_end_time, PDO::PARAM_STR);
            
            if ($query->execute()) {
                $_SESSION['success'] = "Attendance settings updated successfully";
                header("Location: attendance-settings.php");
                exit();
            } else {
                $error = "Error updating settings";
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get success message from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin | Attendance Settings</title>
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
      margin-left: 260px;
      padding: 100px 30px 30px 30px;
      transition: margin-left 0.3s ease;
    }

    .main-content.collapsed {
      margin-left: 0;
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

    .page-header {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #344C64;
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 30px;
    }

    .settings-card {
      background: white;
      padding: 30px;
      border-radius: 16px;
      box-shadow: 0 0 12px rgba(0, 0, 0, 0.05);
      max-width: 800px;
    }

    .form-label {
      font-weight: 600;
      color: #555;
      margin-bottom: 8px;
      display: block;
    }

    .form-control,
    .form-select {
      border-radius: 8px;
      border: 1px solid #ddd;
      padding: 10px 12px;
      font-size: 14px;
      transition: all 0.3s;
    }

    .form-control:focus,
    .form-select:focus {
      outline: none;
      border-color: #71C9CE;
      box-shadow: 0 0 0 3px rgba(113, 201, 206, 0.1);
    }

    .btn-save {
      background: #71C9CE;
      color: #fff;
      border: none;
      padding: 12px 30px;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-save:hover {
      background: #5fb3b8;
    }

    .setting-group {
      margin-bottom: 25px;
    }

    .help-text {
      font-size: 13px;
      color: #777;
      margin-top: 5px;
    }

    .settings-section {
      border-left: 4px solid #71C9CE;
      padding-left: 20px;
      margin-bottom: 30px;
    }

    .settings-section h5 {
      color: #344C64;
      font-weight: 600;
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
    <img src="../assets/images/profile-image.png" alt="Profile">
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

    <!-- attendance -->
    <a class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="collapse" href="#attendanceMenu" role="button" aria-expanded="false" aria-controls="attendanceMenu">
      <span class="material-icons">access_time</span> Attendance
      <span class="ms-auto">›</span>
    </a>
    <div class="collapse" id="attendanceMenu">
      <a href="manage-attendance.php" class="list-group-item list-group-item-action">Manage Attendance</a>
      <a href="attendance-settings.php" class="list-group-item list-group-item-action">Attendance Settings</a>
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
  <div class="page-header">
    <span class="material-icons">settings</span>
    Attendance Settings
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($error); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($success); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="settings-card">
    <form method="POST" action="">
      <!-- Work Hours Section -->
      <div class="settings-section">
        <h5><span class="material-icons" style="vertical-align: middle; margin-right: 8px;">schedule</span>Work Hours</h5>
        
        <?php
        // Parse work start time for 12-hour format
        $start_time = isset($settings['work_start']) ? $settings['work_start'] : '09:00:00';
        $start_timestamp = strtotime($start_time);
        $start_hour_12 = date('h', $start_timestamp);
        $start_minute = date('i', $start_timestamp);
        $start_period = date('A', $start_timestamp);
        
        // Parse work end time for 12-hour format
        $end_time = isset($settings['work_end']) ? $settings['work_end'] : '17:00:00';
        $end_timestamp = strtotime($end_time);
        $end_hour_12 = date('h', $end_timestamp);
        $end_minute = date('i', $end_timestamp);
        $end_period = date('A', $end_timestamp);
        ?>
        
        <div class="row">
          <div class="col-md-6">
            <div class="setting-group">
              <label class="form-label">Work Start Time</label>
              <div class="row g-2">
                <div class="col-4">
                  <select class="form-select" name="start_hour" required>
                    <?php for($i = 1; $i <= 12; $i++): ?>
                      <option value="<?php echo $i; ?>" <?php echo ($i == intval($start_hour_12)) ? 'selected' : ''; ?>>
                        <?php echo sprintf('%02d', $i); ?>
                      </option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-4">
                  <select class="form-select" name="start_minute" required>
                    <?php for($i = 0; $i < 60; $i += 5): ?>
                      <option value="<?php echo $i; ?>" <?php echo ($i == intval($start_minute)) ? 'selected' : ''; ?>>
                        <?php echo sprintf('%02d', $i); ?>
                      </option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-4">
                  <select class="form-select" name="start_period" required>
                    <option value="AM" <?php echo ($start_period == 'AM') ? 'selected' : ''; ?>>AM</option>
                    <option value="PM" <?php echo ($start_period == 'PM') ? 'selected' : ''; ?>>PM</option>
                  </select>
                </div>
              </div>
              <div class="help-text">Official work start time</div>
            </div>
          </div>
          
          <div class="col-md-6">
            <div class="setting-group">
              <label class="form-label">Work End Time</label>
              <div class="row g-2">
                <div class="col-4">
                  <select class="form-select" name="end_hour" required>
                    <?php for($i = 1; $i <= 12; $i++): ?>
                      <option value="<?php echo $i; ?>" <?php echo ($i == intval($end_hour_12)) ? 'selected' : ''; ?>>
                        <?php echo sprintf('%02d', $i); ?>
                      </option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-4">
                  <select class="form-select" name="end_minute" required>
                    <?php for($i = 0; $i < 60; $i += 5): ?>
                      <option value="<?php echo $i; ?>" <?php echo ($i == intval($end_minute)) ? 'selected' : ''; ?>>
                        <?php echo sprintf('%02d', $i); ?>
                      </option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-4">
                  <select class="form-select" name="end_period" required>
                    <option value="AM" <?php echo ($end_period == 'AM') ? 'selected' : ''; ?>>AM</option>
                    <option value="PM" <?php echo ($end_period == 'PM') ? 'selected' : ''; ?>>PM</option>
                  </select>
                </div>
              </div>
              <div class="help-text">Official work end time</div>
            </div>
          </div>
        </div>
      </div>

      <div class="text-end">
        <button type="submit" name="update_settings" class="btn-save">
          <span class="material-icons" style="font-size: 18px;">save</span>
          Save Settings
        </button>
      </div>
    </form>
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
