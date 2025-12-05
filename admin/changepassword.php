<?php
// Start the session
session_start();

// Include database connection
require_once('includes/config.php');

// Check if admin is logged in
if (!isset($_SESSION['alogin'])) {
    header('location: index.php');
    exit();
}

// Initialize variables
$error = '';
$success = '';

// Handle form submission
if (isset($_POST['change'])) {
    try {
        // Get and sanitize user input
        $currentPassword = trim($_POST['currentPassword']);
        $newPassword = trim($_POST['newPassword']);
        $confirmPassword = trim($_POST['confirmPassword']);
        $username = $_SESSION['alogin'];

        // Validate password fields are not empty
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception("All password fields are required");
        }        // Validate new password requirements
        if (strlen($newPassword) < 6) {
            throw new Exception("Password must be at least 6 characters long");
        }
        
        // Check for uppercase, lowercase and number
        if (!preg_match('/[A-Z]/', $newPassword)) {
            throw new Exception("Password must contain at least one uppercase letter");
        }
        if (!preg_match('/[a-z]/', $newPassword)) {
            throw new Exception("Password must contain at least one lowercase letter");
        }
        if (!preg_match('/[0-9]/', $newPassword)) {
            throw new Exception("Password must contain at least one number");
        }

        // Validate new password and confirm password match
        if ($newPassword !== $confirmPassword) {
            throw new Exception("New password and confirm password do not match");
        }

        // Get admin's current password from database
        $sql = "SELECT Password FROM admin WHERE UserName = :username";
        $query = $dbh->prepare($sql);
        $query->bindParam(':username', $username, PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        // Verify current password
        if (!password_verify($currentPassword, $result['Password'])) {
            throw new Exception("Current password is incorrect");
        }

        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password in database
        $sql = "UPDATE admin SET Password = :password WHERE UserName = :username";
        $query = $dbh->prepare($sql);
        $query->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
        $query->bindParam(':username', $username, PDO::PARAM_STR);

        if ($query->execute()) {
            $success = "Password changed successfully";
            
            // Clear form data
            $_POST = array();
        } else {
            throw new Exception("Error updating password");
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
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
      background-color: #eef9fa; /* Updated background */
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
      gap: 12px; /* space between icon and text */
      padding: 10px 15px;
      font-size: 15px;
      font-weight: 500;
      color: #333;
      border-radius: 8px;
      margin-bottom: 10px;
      transition: all 0.2s ease-in-out;
      border: none; /* Remove border */
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
      padding-left: 40px; /* indent submenu items */
      font-size: 14px;
    }

    #sidebar .collapse .list-group-item:hover {
      background-color: #f0fbfd;
      color: #344C64;
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
      color:rgb(66, 155, 193);
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
      border-color: rgb(144, 215, 246);
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
      color:rgb(144, 215, 246);
    }

    .custom-btn {
      background-color:rgb(80, 173, 214);
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
      background-color:rgb(66, 155, 193);
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
<div class="main-content" id="mainContent">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card shadow-sm">
          <h3 class="text-heading mb-4">Change Password</h3>

          <?php if (!empty($error)): ?>
          <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
              <?php echo htmlspecialchars($error); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>

          <?php if (!empty($success)): ?>
          <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
              <?php echo htmlspecialchars($success); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>

          <form method="POST" onsubmit="return valid();">
            <div class="form-group mb-4 position-relative">
              <input type="password" class="form-control" id="currentPassword" name="currentPassword" 
                     placeholder=" " required value="<?php echo isset($_POST['currentPassword']) ? htmlspecialchars($_POST['currentPassword']) : ''; ?>">
              <label for="currentPassword">Current Password</label>
            </div>

            <div class="form-group mb-4 position-relative">              <input type="password" class="form-control" id="newPassword" name="newPassword" 
                     placeholder=" " required pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}"
                     title="Must be at least 6 characters long and contain at least one uppercase letter, one lowercase letter, and one number"
                     value="<?php echo isset($_POST['newPassword']) ? htmlspecialchars($_POST['newPassword']) : ''; ?>">
              <label for="newPassword">New Password</label>              <small class="form-text text-danger mt-2">
                Password must be at least 6 characters long and contain at least one uppercase letter, one lowercase letter, and one number.
              </small>
            </div>

            <div class="form-group mb-4 position-relative">
              <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" 
                     placeholder=" " required value="<?php echo isset($_POST['confirmPassword']) ? htmlspecialchars($_POST['confirmPassword']) : ''; ?>">
              <label for="confirmPassword">Confirm New Password</label>
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
<script>
  function valid() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const currentPassword = document.getElementById('currentPassword').value;

    // Check if passwords are empty
    if (!currentPassword || !newPassword || !confirmPassword) {
      alert("All password fields are required");
      return false;
    }    // Check password requirements
    if (newPassword.length < 6) {
      alert("Password must be at least 6 characters long");
      return false;
    }

    // Check for uppercase letter
    if (!/[A-Z]/.test(newPassword)) {
      alert("Password must contain at least one uppercase letter");
      return false;
    }

    // Check for lowercase letter
    if (!/[a-z]/.test(newPassword)) {
      alert("Password must contain at least one lowercase letter");
      return false;
    }

    // Check for number
    if (!/[0-9]/.test(newPassword)) {
      alert("Password must contain at least one number");
      return false;
    }

    // Check if passwords match
    if (newPassword !== confirmPassword) {
      alert("New Password and Confirm Password do not match");
      return false;
    }

    return true;
  }

  // Sidebar toggle functionality
  document.getElementById('menu-toggle').addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('mainContent').classList.toggle('collapsed');
  });
</script>

</body>
</html>
