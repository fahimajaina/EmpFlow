<?php
session_start();
include('include/config.php');

// Check if employee is logged in
if (!isset($_SESSION['eid'])) {
    header('location: index.php');
    exit();
}

$empid = $_SESSION['eid'];
$success = '';
$error = '';

// Fetch attendance settings
$settingsSql = "SELECT work_start, work_end FROM tblattendancesettings ORDER BY id DESC LIMIT 1";
$settingsQuery = $dbh->prepare($settingsSql);
$settingsQuery->execute();
$attendanceSettings = $settingsQuery->fetch(PDO::FETCH_ASSOC);

// Set default times if no settings exist
$work_start = $attendanceSettings ? $attendanceSettings['work_start'] : '09:00:00';
$work_end = $attendanceSettings ? $attendanceSettings['work_end'] : '17:00:00';

// Fetch employee details
$sql = "SELECT FirstName, LastName, EmpId FROM tblemployees WHERE id = :empid";
$query = $dbh->prepare($sql);
$query->bindParam(':empid', $empid, PDO::PARAM_INT);
$query->execute();
$employee = $query->fetch(PDO::FETCH_ASSOC);

// Get today's date
$today = date('Y-m-d');

// Check if employee already has attendance record for today
$checkSql = "SELECT * FROM tblattendance WHERE empid = :empid AND attendance_date = :today";
$checkQuery = $dbh->prepare($checkSql);
$checkQuery->bindParam(':empid', $empid, PDO::PARAM_INT);
$checkQuery->bindParam(':today', $today, PDO::PARAM_STR);
$checkQuery->execute();
$todayAttendance = $checkQuery->fetch(PDO::FETCH_ASSOC);

// Handle check-in
if (isset($_POST['check_in'])) {
    if ($todayAttendance && $todayAttendance['check_in_time']) {
        $error = "You have already checked in today!";
    } else {
        try {
            // Get current time using DateTime for proper timezone handling
            $now = new DateTime();
            $check_in_time = $now->format('H:i:s');
            
            // Determine status based on work start time (comparing only hours and minutes)
            $check_in_hhmm = substr($check_in_time, 0, 5); // Get HH:MM
            $work_start_hhmm = substr($work_start, 0, 5); // Get HH:MM
            
            $status = 'Present';
            if ($check_in_hhmm > $work_start_hhmm) {
                $status = 'Late';
            }
            
            if ($todayAttendance) {
                // Update existing record
                $sql = "UPDATE tblattendance SET check_in_time = :check_in_time, status = :status WHERE id = :id";
                $query = $dbh->prepare($sql);
                $query->bindParam(':check_in_time', $check_in_time, PDO::PARAM_STR);
                $query->bindParam(':status', $status, PDO::PARAM_STR);
                $query->bindParam(':id', $todayAttendance['id'], PDO::PARAM_INT);
            } else {
                // Insert new record
                $sql = "INSERT INTO tblattendance (empid, attendance_date, check_in_time, status) 
                        VALUES (:empid, :today, :check_in_time, :status)";
                $query = $dbh->prepare($sql);
                $query->bindParam(':empid', $empid, PDO::PARAM_INT);
                $query->bindParam(':today', $today, PDO::PARAM_STR);
                $query->bindParam(':check_in_time', $check_in_time, PDO::PARAM_STR);
                $query->bindParam(':status', $status, PDO::PARAM_STR);
            }
            
            if ($query->execute()) {
                $success = "Checked in successfully at " . date('h:i A', strtotime($check_in_time));
                if ($status == 'Late') {
                    $success .= " (Marked as Late)";
                }
                // Refresh to get updated data
                header("Location: attendance.php");
                exit();
            }
        } catch (PDOException $e) {
            $error = "Error checking in: " . $e->getMessage();
        }
    }
}

// Handle check-out
if (isset($_POST['check_out'])) {
    if (!$todayAttendance || !$todayAttendance['check_in_time']) {
        $error = "You must check in first!";
    } elseif ($todayAttendance['check_out_time']) {
        $error = "You have already checked out today!";
    } else {
        try {
            // Get current time using DateTime for proper timezone handling
            $now = new DateTime();
            $check_out_time = $now->format('H:i:s');
            
            // Calculate work hours
            $check_in = new DateTime($todayAttendance['check_in_time']);
            $check_out = new DateTime($check_out_time);
            $interval = $check_in->diff($check_out);
            $work_hours = $interval->h + ($interval->i / 60);
            
            $sql = "UPDATE tblattendance 
                    SET check_out_time = :check_out_time, work_hours = :work_hours 
                    WHERE id = :id";
            $query = $dbh->prepare($sql);
            $query->bindParam(':check_out_time', $check_out_time, PDO::PARAM_STR);
            $query->bindParam(':work_hours', $work_hours, PDO::PARAM_STR);
            $query->bindParam(':id', $todayAttendance['id'], PDO::PARAM_INT);
            
            if ($query->execute()) {
                $success = "Checked out successfully at " . date('h:i A', strtotime($check_out_time));
                // Refresh to get updated data
                header("Location: attendance.php");
                exit();
            }
        } catch (PDOException $e) {
            $error = "Error checking out: " . $e->getMessage();
        }
    }
}

// Fetch attendance statistics
$statsSql = "SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
                SUM(work_hours) as total_hours
             FROM tblattendance 
             WHERE empid = :empid";
$statsQuery = $dbh->prepare($statsSql);
$statsQuery->bindParam(':empid', $empid, PDO::PARAM_INT);
$statsQuery->execute();
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

// Fetch attendance history (last 30 days)
$historySql = "SELECT * FROM tblattendance 
               WHERE empid = :empid 
               ORDER BY attendance_date DESC 
               LIMIT 30";
$historyQuery = $dbh->prepare($historySql);
$historyQuery->bindParam(':empid', $empid, PDO::PARAM_INT);
$historyQuery->execute();
$attendanceHistory = $historyQuery->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EMPFLOW | Attendance</title>

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
      padding: 90px 40px 40px 40px;
      transition: margin-left 0.3s ease;
      background: linear-gradient(135deg, #f9fefe, #f0fdfd);
      min-height: 100vh;
    }

    .main-content.collapsed {
      margin-left: 0;
    }

    .page-title {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #2c7a7b;
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 30px;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .card {
      border: none;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.07);
      border-radius: 20px;
      background: #ffffff;
      transition: box-shadow 0.2s ease;
    }

    .card:hover {
      box-shadow: 0 12px 35px rgba(0, 0, 0, 0.08);
    }

    .attendance-card {
      background: linear-gradient(135deg, #48A6A7 0%, #3c8e8f 100%);
      color: white;
      padding: 30px;
      margin-bottom: 30px;
    }

    .time-display {
      font-size: 48px;
      font-weight: 700;
      margin: 20px 0;
    }

    .date-display {
      font-size: 18px;
      opacity: 0.9;
    }

    .btn-attendance {
      padding: 15px 40px;
      font-size: 18px;
      font-weight: 600;
      border-radius: 12px;
      border: 2px solid white;
      background: transparent;
      color: white;
      transition: all 0.3s ease;
    }

    .btn-attendance:hover {
      background: white;
      color: #48A6A7;
    }

    .btn-attendance:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 14px;
    }

    .status-badge.checked-in {
      background-color: #d1fae5;
      color: #065f46;
    }

    .status-badge.checked-out {
      background-color: #fee2e2;
      color: #991b1b;
    }

    .status-badge.not-checked-in {
      background-color: #fef3c7;
      color: #92400e;
    }

    .table thead {
      background-color: #e0f7f7;
      font-weight: 600;
    }

    .table tbody tr:hover {
      background-color: #f1fefe;
      transition: background 0.2s ease;
    }

    .search-wrapper {
      position: relative;
      max-width: 300px;
      margin-bottom: 16px;
    }

    .search-wrapper .material-icons {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #aaa;
    }

    .search-box {
      padding-left: 40px;
      border-radius: 12px;
      border: 1px solid #ccc;
      transition: border-color 0.3s, box-shadow 0.3s;
      background-color: #fdfdfd;
    }

    .search-box:focus {
      border-color: #48A6A7;
      box-shadow: 0 0 0 0.2rem rgba(72, 166, 167, 0.25);
    }

    .stat-card {
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      text-align: center;
    }

    .stat-card .stat-icon {
      font-size: 40px;
      color: #48A6A7;
      margin-bottom: 10px;
    }

    .stat-card .stat-number {
      font-size: 32px;
      font-weight: 700;
      color: #333;
      margin-bottom: 5px;
    }

    .stat-card .stat-label {
      font-size: 14px;
      color: #666;
      font-weight: 500;
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

    <a href="logout.php"><span class="material-icons">logout</span> Sign Out</a>
  </div>
</div>


<!-- Main Content -->
<div class="main-content" id="main-content">
  <div class="page-title">
    <span class="material-icons">access_time</span> Attendance Management
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

  <!-- Clock In/Out Card -->
  <div class="card attendance-card">
    <div class="row align-items-center">
      <div class="col-md-6 text-center text-md-start">
        <h3 class="mb-3">Today's Attendance</h3>
        <div class="date-display" id="currentDate">Loading...</div>
        <div class="time-display" id="currentTime">00:00:00</div>
        <div class="mt-3 mb-3">
          <small style="opacity: 0.9;">
            <span class="material-icons" style="font-size: 16px; vertical-align: middle;">schedule</span>
            Office Hours: <?php echo date('h:i A', strtotime($work_start)); ?> - <?php echo date('h:i A', strtotime($work_end)); ?>
          </small>
        </div>
        <div class="mt-3">
          <?php if ($todayAttendance && $todayAttendance['check_in_time']): ?>
            <?php if ($todayAttendance['check_out_time']): ?>
              <span class="status-badge checked-out" id="statusBadge">
                <span class="material-icons" style="font-size: 18px;">logout</span>
                Checked Out at <?php echo date('h:i A', strtotime($todayAttendance['check_out_time'])); ?>
              </span>
            <?php else: ?>
              <span class="status-badge checked-in" id="statusBadge">
                <span class="material-icons" style="font-size: 18px;">check_circle</span>
                Checked In at <?php echo date('h:i A', strtotime($todayAttendance['check_in_time'])); ?>
              </span>
            <?php endif; ?>
          <?php else: ?>
            <span class="status-badge not-checked-in" id="statusBadge">
              <span class="material-icons" style="font-size: 18px;">schedule</span>
              Not Checked In
            </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-md-6 text-center mt-4 mt-md-0">
        <form method="POST" style="display: inline;">
          <button type="submit" name="check_in" class="btn btn-attendance" id="checkInBtn" 
            <?php echo ($todayAttendance && $todayAttendance['check_in_time']) ? 'disabled' : ''; ?>>
            <span class="material-icons me-2" style="vertical-align: middle;">login</span>
            Check In
          </button>
        </form>
        <form method="POST" style="display: inline;">
          <button type="submit" name="check_out" class="btn btn-attendance ms-3" id="checkOutBtn" 
            <?php echo (!$todayAttendance || !$todayAttendance['check_in_time'] || $todayAttendance['check_out_time']) ? 'disabled' : ''; ?>>
            <span class="material-icons me-2" style="vertical-align: middle;">logout</span>
            Check Out
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Statistics -->
  <div class="row g-4 mb-4">
    <div class="col-md-3">
      <div class="stat-card">
        <div class="stat-icon">
          <span class="material-icons">event_available</span>
        </div>
        <div class="stat-number"><?php echo $stats['present_count'] ? $stats['present_count'] : 0; ?></div>
        <div class="stat-label">Days Present</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card">
        <div class="stat-icon">
          <span class="material-icons">event_busy</span>
        </div>
        <div class="stat-number"><?php echo $stats['absent_count'] ? $stats['absent_count'] : 0; ?></div>
        <div class="stat-label">Days Absent</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card">
        <div class="stat-icon">
          <span class="material-icons">schedule</span>
        </div>
        <div class="stat-number"><?php echo $stats['late_count'] ? $stats['late_count'] : 0; ?></div>
        <div class="stat-label">Late Arrivals</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card">
        <div class="stat-icon">
          <span class="material-icons">access_time</span>
        </div>
        <div class="stat-number"><?php echo $stats['total_hours'] ? number_format($stats['total_hours'], 0) : 0; ?></div>
        <div class="stat-label">Total Hours</div>
      </div>
    </div>
  </div>

  <!-- Attendance History -->
  <div class="card p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
      <h5 class="fw-bold mb-3" style="color: #333;">Attendance History</h5>

      <!-- Search Input -->
      <div class="search-wrapper">
        <span class="material-icons">search</span>
        <input type="text" id="searchInput" class="form-control search-box" placeholder="Search...">
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle" id="attendanceTable">
        <thead>
          <tr class="text-secondary">
            <th>#</th>
            <th>Date</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Work Hours</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          if (count($attendanceHistory) > 0) {
              $cnt = 1;
              foreach($attendanceHistory as $record) {
                  // Determine badge class
                  $badgeClass = 'bg-success';
                  if($record['status'] == 'Late') {
                      $badgeClass = 'bg-warning text-dark';
                  } elseif($record['status'] == 'Absent') {
                      $badgeClass = 'bg-danger';
                  }
                  
                  // Format work hours
                  $workHoursDisplay = '-';
                  if($record['work_hours']) {
                      $hours = floor($record['work_hours']);
                      $minutes = round(($record['work_hours'] - $hours) * 60);
                      $workHoursDisplay = $hours . 'h ' . $minutes . 'm';
                  }
          ?>
          <tr>
            <td><?php echo $cnt; ?></td>
            <td><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
            <td><?php echo $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '-'; ?></td>
            <td><?php echo $record['check_out_time'] ? date('h:i A', strtotime($record['check_out_time'])) : '-'; ?></td>
            <td><?php echo $workHoursDisplay; ?></td>
            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($record['status']); ?></span></td>
          </tr>
          <?php 
                  $cnt++;
              }
          } else {
          ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-4">
              <span class="material-icons" style="font-size: 48px; opacity: 0.3;">event_busy</span>
              <p class="mt-2">No attendance records found</p>
            </td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Scripts -->
<script>
  // Sidebar Toggle
  const toggleBtn = document.getElementById('menu-toggle');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('main-content');

  toggleBtn?.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('collapsed');
  });

  // Update Clock
  function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { hour12: false });
    const dateString = now.toLocaleDateString('en-US', { 
      weekday: 'long', 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    });
    
    document.getElementById('currentTime').textContent = timeString;
    document.getElementById('currentDate').textContent = dateString;
  }

  // Update clock every second
  updateClock();
  setInterval(updateClock, 1000);

  // Search Filter
  const searchInput = document.getElementById('searchInput');
  const table = document.getElementById('attendanceTable');
  
  searchInput.addEventListener('keyup', function () {
    const filter = searchInput.value.toLowerCase();
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(filter) ? '' : 'none';
    });
  });
</script>

</body>
</html>
