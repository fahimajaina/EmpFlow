<?php
session_start();
include('include/config.php');

// Check if employee is logged in
if (!isset($_SESSION['eid']) || empty($_SESSION['eid'])) {
    header('location: index.php');
    exit();
}

// Initialize variables
$error = '';
$success = '';
$eid = $_SESSION['eid'];

// Handle form submission
if (isset($_POST['change'])) {
    try {
        // Get and sanitize user input
        $currentPassword = trim($_POST['password']);
        $newPassword = trim($_POST['newpassword']);
        $confirmPassword = trim($_POST['confirmpassword']);

        // Validate password fields are not empty
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception("All fields are required");
        }

        // Validate new password requirements
        if (strlen($newPassword) < 8) {
            throw new Exception("Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number");
        }

        // Check for uppercase, lowercase and number
        if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            throw new Exception("Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number");
        }

        // Check if passwords match
        if ($newPassword !== $confirmPassword) {
            throw new Exception("New Password and Confirm Password do not match");
        }

        // Get employee's current password from database
        $sql = "SELECT Password FROM tblemployees WHERE id = :eid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':eid', $eid, PDO::PARAM_INT);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new Exception("Employee not found");
        }

        // Verify current password
        if (!password_verify($currentPassword, $result['Password'])) {
            throw new Exception("Current password is incorrect");
        }

        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password in database
        $sql = "UPDATE tblemployees SET Password = :password WHERE id = :eid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
        $query->bindParam(':eid', $eid, PDO::PARAM_INT);

        if ($query->execute()) {
            $success = "Password changed successfully";
        } else {
            throw new Exception("Error updating password");
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch employee data for sidebar
try {
    $sql = "SELECT FirstName, LastName, EmpId FROM tblemployees WHERE id = :eid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':eid', $eid, PDO::PARAM_INT);
    $query->execute();
    $employeeData = $query->fetch(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    error_log("Error fetching employee data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EMPFLOW | Change Password</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <!-- Material Icons -->
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
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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

    .main-content {
      margin-left: 240px;
      padding: 120px 30px 30px 30px;
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
        margin-left: 0;
      }

      .main-content.collapsed {
        margin-left: 240px;
      }
    }

    .card {
      border-radius: 14px;
      padding: 30px;
      border: none;
      background: #ffffff;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
    }

    .text-heading {
      color: #48A6A7;
      font-weight: 600;
      text-align: center;
      margin-bottom: 25px;
      font-size: 22px;
    }

    .form-group {
      position: relative;
    }

    .form-group input.form-control {
      border: 1px solid #ccc;
      border-radius: 10px;
      height: 50px;
      padding: 1.25rem 1rem 0.5rem 1rem;
      background: #f9f9f9;
      transition: all 0.3s ease;
    }

    .form-group input:focus {
      border-color: #48A6A7;
      box-shadow: 0 0 0 0.2rem rgba(72, 166, 167, 0.25);
      background: #fff;
    }

    .form-group label {
      position: absolute;
      top: 12px;
      left: 16px;
      color: #888;
      font-size: 14px;
      transition: all 0.2s ease;
      pointer-events: none;
    }

    .form-group input:focus + label,
    .form-group input:not(:placeholder-shown) + label {
      top: 6px;
      font-size: 11px;
      color: #48A6A7;
    }

    .custom-btn {
      background-color: #3b8d8e;
      border: none;
      border-radius: 12px;
      font-weight: 600;
      color: #fff;
      padding: 12px 0;
      width: 100%;
      transition: background-color 0.3s ease;
      font-size: 16px;
    }

    .custom-btn:hover {
      background-color: #327979;
    }

    .errorWrap {
      padding: 10px;
      margin-bottom: 20px;
      background: #fff3f3;
      border-left: 4px solid #d9534f;
    }

    .succWrap {
      padding: 10px;
      margin-bottom: 20px;
      background: #e7f9ed;
      border-left: 4px solid #5cb85c;
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
      <h6 class="mb-0" style="font-weight:600;"><?php echo htmlspecialchars($employeeData->FirstName . ' ' . $employeeData->LastName); ?></h6>
      <small class="text-muted"><?php echo htmlspecialchars($employeeData->EmpId); ?></small>
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
    <a href="my-salary.php" class="list-group-item list-group-item-action">
      <span class="material-icons">account_balance_wallet</span> My Salary
    </a>
    <a href="my-payslips.php" class="list-group-item list-group-item-action">
      <span class="material-icons">receipt_long</span> My Payslips
    </a>

    <a href="logout.php"><span class="material-icons">logout</span> Sign Out</a>
  </div>
</div>



<!-- Main Content -->
<div class="main-content" id="mainContent">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card shadow-sm">
          <h3 class="text-heading mb-4">Change Password</h3> 
          <?php if($error): ?>
          <div class="alert alert-danger alert-dismissible fade show mb-4">
            <?php echo htmlentities($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>

          <?php if($success): ?>
          <div class="alert alert-success alert-dismissible fade show mb-4">
            <?php echo htmlentities($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>

          <form method="POST" onsubmit="return valid();">
            <div class="form-group mb-4 position-relative">
              <input type="password" class="form-control" name="password" id="password" placeholder=" " required>
              <label for="password">Current Password</label>
            </div>

            <div class="form-group mb-4 position-relative">
              <input type="password" class="form-control" name="newpassword" id="newpassword" placeholder=" " required>
              <label for="newpassword">New Password</label>
            </div>

            <div class="form-group mb-4 position-relative">
              <input type="password" class="form-control" name="confirmpassword" id="confirmpassword" placeholder=" " required>
              <label for="confirmpassword">Confirm New Password</label>
            </div>

            <div class="form-group mb-0">
              <button type="submit" name="change" class="custom-btn">
                Change Password
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>  function valid() {
    const currentPassword = document.getElementById('password').value;
    const newPassword = document.getElementById('newpassword').value;
    const confirmPassword = document.getElementById('confirmpassword').value;

    // Just check if fields are filled and passwords match
    if (!currentPassword || !newPassword || !confirmPassword) {
      alert("All fields are required");
      return false;
    }

    if (newPassword !== confirmPassword) {
      alert("New Password and Confirm Password do not match");
      return false;
    }

    return true;
  }

  document.getElementById('menu-toggle').addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('mainContent').classList.toggle('collapsed');
  });
</script>

</body>
</html>
