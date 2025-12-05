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

// Function to generate unique employee ID
function generateEmpId($dbh) {
    $prefix = "EMP";
    $year = date("y");
    
    // Get the last employee ID from database
    $stmt = $dbh->query("SELECT MAX(CAST(SUBSTRING(EmpId, 6) AS UNSIGNED)) as max_num FROM tblemployees WHERE EmpId LIKE 'EMP{$year}%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $next_num = ($result['max_num'] ?? 0) + 1;
    return $prefix . $year . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

// Function to validate name
function validateName($name) {
    return preg_match("/^[a-zA-Z ]{3,50}$/", $name);
}

// Function to validate phone number
function validatePhone($phone) {
    return preg_match("/^[0-9]{11}$/", $phone);
}

// Function to validate password strength
function validatePassword($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return strlen($password) >= 8 && 
           preg_match("/[A-Z]/", $password) && 
           preg_match("/[a-z]/", $password) && 
           preg_match("/[0-9]/", $password);
}

// Handle form submission
if (isset($_POST['add'])) {
    try {
        // Get and sanitize form data
        $empId = generateEmpId($dbh);
        $fname = trim($_POST['firstName']);
        $lname = trim($_POST['lastName']);
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = trim($_POST['password']);
        $confirmpassword = trim($_POST['confirmpassword']);
        $gender = trim($_POST['gender']);
        $dob = $_POST['dob'];
        $department = trim($_POST['department']);
        $designation = !empty($_POST['designation']) ? trim($_POST['designation']) : null;
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $country = trim($_POST['country']);
        $mobileno = trim($_POST['mobileno']);

        // Validate first name and last name
        if (!validateName($fname)) {
            throw new Exception("First name should only contain letters and be between 3-50 characters");
        }
        if (!validateName($lname)) {
            throw new Exception("Last name should only contain letters and be between 3-50 characters");
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address");
        }

        // Check if email already exists
        $stmt = $dbh->prepare("SELECT COUNT(*) FROM tblemployees WHERE EmailId = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Email already exists");
        }

        // Validate phone number
        if (!validatePhone($mobileno)) {
            throw new Exception("Invalid phone number format. Must be 11 digits");
        }

        // Validate password
        if (!validatePassword($password)) {
            throw new Exception("Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number");
        }        // Check if passwords match
        if ($password !== $confirmpassword) {
            throw new Exception("Passwords do not match");
        }
        
        // Check for duplicate mobile number
        $stmt = $dbh->prepare("SELECT COUNT(*) FROM tblemployees WHERE Phonenumber = ?");
        $stmt->execute([$mobileno]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("This mobile number is already registered with another employee");
        }

        // Validate date of birth
        if (strtotime($dob) > strtotime('today')) {
            throw new Exception("Date of Birth cannot be in the future");
        }
        if (strtotime($dob) > strtotime('-18 years')) {
            throw new Exception("Employee must be at least 18 years old");
        }
        if (strtotime($dob) < strtotime('-100 years')) {
            throw new Exception("Please enter a valid Date of Birth");
        }

        // Address validation
        if (strlen($address) < 5) {
            throw new Exception("Address is too short. Minimum 5 characters required");
        }
        if (strlen($address) > 200) {
            throw new Exception("Address is too long. Maximum 200 characters allowed");
        }
        if (!preg_match("/^[a-zA-Z0-9\s,.\/-]+$/", $address)) {
            throw new Exception("Address contains invalid characters");
        }        // City validation (3-50 characters, letters only)
        if (!preg_match("/^[a-zA-Z ]{3,50}$/", $city)) {
            throw new Exception("City name must contain only letters and be between 3-50 characters");
        }
        // Country validation (3-50 characters, letters only)
        if (!preg_match("/^[a-zA-Z ]{3,50}$/", $country)) {
            throw new Exception("Country name must contain only letters and be between 3-50 characters");
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert employee data
        $sql = "INSERT INTO tblemployees (EmpId, FirstName, LastName, EmailId, Password, Gender, Dob, Department, designationid, Address, City, Country, Phonenumber) 
                VALUES (:empid, :fname, :lname, :email, :password, :gender, :dob, :department, :designation, :address, :city, :country, :mobile)";
        
        $query = $dbh->prepare($sql);
        $query->bindParam(':empid', $empId, PDO::PARAM_STR);
        $query->bindParam(':fname', $fname, PDO::PARAM_STR);
        $query->bindParam(':lname', $lname, PDO::PARAM_STR);
        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
        $query->bindParam(':gender', $gender, PDO::PARAM_STR);
        $query->bindParam(':dob', $dob, PDO::PARAM_STR);
        $query->bindParam(':department', $department, PDO::PARAM_INT);
        $query->bindParam(':designation', $designation, PDO::PARAM_INT);
        $query->bindParam(':address', $address, PDO::PARAM_STR);
        $query->bindParam(':city', $city, PDO::PARAM_STR);
        $query->bindParam(':country', $country, PDO::PARAM_STR);
        $query->bindParam(':mobile', $mobileno, PDO::PARAM_STR);

        $query->execute();
        
        if ($query->rowCount() > 0) {
            $_SESSION['success'] = "Employee added successfully. Employee ID: " . $empId;
            header("Location: manageemployee.php");
            exit();
        } else {
            throw new Exception("Something went wrong while adding employee");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch departments for dropdown
try {
    $stmt = $dbh->query("SELECT id, DepartmentName FROM tbldepartments ORDER BY DepartmentName");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching departments: " . $e->getMessage();
}

// Fetch designations for dropdown
try {
    $stmt = $dbh->query("SELECT d.id, d.DesignationName, dept.DepartmentName FROM tbldesignation d LEFT JOIN tbldepartments dept ON d.deptid = dept.id ORDER BY dept.DepartmentName, d.DesignationName");
    $designations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching designations: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Add Employee</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts & Material Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
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
      padding: 80px 30px 30px;
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

    .form-section {
      background: #ffffff;
      padding: 40px 30px;
      border-radius: 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    }

    .form-label {
      font-weight: 500;
      margin-bottom: 6px;
    }

    .form-control,
    .form-select {
      border-radius: 12px;
      border: 1px solid #d9d9d9;
      transition: border-color 0.3s, box-shadow 0.3s;
    }

    .form-control:focus,
    .form-select:focus {
      border-color: rgb(144, 215, 246);
      box-shadow: 0 0 0 0.2rem rgba(110, 220, 222, 0.25);
    }

    .btn-custom {
      background-color: rgb(80, 173, 214);
      color: white;
      border-radius: 12px;
      font-weight: 500;
      padding: 10px;
      transition: 0.3s ease;
    }

    .btn-custom:hover {
      background-color: rgb(66, 155, 193);
    }

  

    /* ✅ Custom Heading Color */
    .heading-colored {
      color: rgb(66, 155, 193);
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
<div class="main-content" id="main-content">
  <h4 class="mb-4 heading-colored"><span class="material-icons me-2">edit</span> Add employee</h4>

  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo $error; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo $success; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <form class="form-section" method="POST" onsubmit="return validateForm();">
    <h5 class="mb-4 heading-colored">Employee Info</h5>

    <div class="row g-4">
      <div class="col-md-6">
        <label for="empcode" class="form-label">Employee Code(Must be unique)</label>
        <input type="text" class="form-control" name="empcode" id="empcode" value="<?php echo generateEmpId($dbh); ?>" readonly required>
      </div>
      <div class="col-md-6">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email" value="" autocomplete="off" required>
      </div>
      <div class="col-md-6">
        <label for="firstName" class="form-label">First Name</label>
        <input type="text" class="form-control" id="firstName" name="firstName" value="" autocomplete="off" required>
      </div>
      <div class="col-md-6">
        <label for="lastName" class="form-label">Last Name</label>
        <input type="text" class="form-control" id="lastName" name="lastName" value="" autocomplete="off" required>
      </div>
      <div class="col-md-6">
        <label for="phone" class="form-label">Mobile Number</label>
        <input type="tel" class="form-control" id="phone" name="mobileno" value="" maxlength="11" autocomplete="off" required>
      </div>
      <div class="col-md-6">
        <label for="gender" class="form-label">Gender</label>
        <select class="form-select" id="gender" name="gender" required>
          <option>Male</option>
          <option>Female</option>
          <option>Other</option>
        </select>
      </div>
      <div class="col-md-6">
        <label for="dob" class="form-label">Date of Birth</label>
        <input type="date" class="form-control" id="birthdate" name="dob" value="" required>
      </div>
      <div class="col-md-6">
        <label for="department" class="form-label">Department</label>
        <select class="form-select" id="department" name="department" required>
          <?php foreach ($departments as $dept): ?>
            <option value="<?php echo $dept['id']; ?>"><?php echo $dept['DepartmentName']; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label for="designation" class="form-label">Designation</label>
        <select class="form-select" id="designation" name="designation">
          <option value="">Select Designation</option>
          <?php foreach ($designations as $desig): ?>
            <option value="<?php echo $desig['id']; ?>"><?php echo $desig['DesignationName'] . ' (' . $desig['DepartmentName'] . ')'; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label for="address" class="form-label">Address</label>
        <input type="text" class="form-control" id="address" name="address" value="" autocomplete="off" required>
      </div>
      <div class="col-md-6">
        <label for="city" class="form-label">City/Town</label>
        <input type="text" class="form-control" id="city" name="city" value="" autocomplete="off" required>
      </div>
      <div class="col-md-6">
        <label for="country" class="form-label">Country</label>
        <input type="text" class="form-control" id="country" name="country" value="" autocomplete="off" required>
      </div>
      <div class="col-md-6">
        <label for="password" class="form-label">Password</label>
        <div class="position-relative">
          <input type="password" class="form-control" id="password" name="password" value="" autocomplete="off" required>
          <span class="material-icons position-absolute top-50 end-0 translate-middle-y me-2 text-muted" 
                style="cursor: pointer;" onclick="togglePassword('password')">visibility_off</span>
        </div>
      </div>
      <div class="col-md-6">
        <label for="confirm" class="form-label">Confirm password</label>
        <div class="position-relative">
          <input type="password" class="form-control" id="confirm" name="confirmpassword" value="" autocomplete="off" required>
          <span class="material-icons position-absolute top-50 end-0 translate-middle-y me-2 text-muted" 
                style="cursor: pointer;" onclick="togglePassword('confirm')">visibility_off</span>
        </div>
      </div>
      <div class="col-12 mt-3">
        <button type="submit" name="add" id="add" class="btn btn-custom w-100">Add</button>
      </div>
    </div>
  </form>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function validateForm() {
    // Get form values
    const firstName = document.getElementById('firstName').value.trim();
    const lastName = document.getElementById('lastName').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm').value;
    const dob = document.getElementById('birthdate').value;

    // Name validation
    const nameRegex = /^[a-zA-Z ]{3,50}$/;
    if (!nameRegex.test(firstName)) {
        alert("First name should only contain letters and be between 3-50 characters");
        return false;
    }
    if (!nameRegex.test(lastName)) {
        alert("Last name should only contain letters and be between 3-50 characters");
        return false;
    }

    // Email validation
    const emailRegex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!email) {
        alert("Email address is required");
        return false;
    }
    if (!emailRegex.test(email)) {
        alert("Please enter a valid email address");
        return false;
    }

    // Phone validation
    const phoneRegex = /^[0-9]{11}$/;
    if (!phoneRegex.test(phone)) {
        alert("Invalid phone number format. Must be 11 digits");
        return false;
    }    // Password validation
    if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
        alert("Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number");
        return false;
    }

    // Password match
    if (password !== confirmPassword) {
        alert("Passwords do not match");
        return false;
    }

    // Address validation
    const address = document.getElementById('address').value.trim();
    if (address.length < 5) {
        alert("Address is too short. Minimum 5 characters required");
        return false;
    }
    if (address.length > 200) {
        alert("Address is too long. Maximum 200 characters allowed");
        return false;
    }
    if (!/^[a-zA-Z0-9\s,.\/-]+$/.test(address)) {
        alert("Address contains invalid characters");
        return false;
    }

    // City validation
    const city = document.getElementById('city').value.trim();
    if (!nameRegex.test(city)) {
        alert("City name must contain only letters and be between 3-50 characters");
        return false;
    }

    // Country validation
    const country = document.getElementById('country').value.trim();
    if (!nameRegex.test(country)) {
        alert("Country name must contain only letters and be between 3-50 characters");
        return false;
    }

    // Date of Birth validations
    const dobDate = new Date(dob);
    const today = new Date();
    
    // Check if date is in the future
    if (dobDate > today) {
        alert("Date of Birth cannot be in the future");
        return false;
    }

    // Calculate age
    let age = today.getFullYear() - dobDate.getFullYear();
    const monthDiff = today.getMonth() - dobDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
        age--;
    }

    // Check if at least 18 years old
    if (age < 18) {
        alert("Employee must be at least 18 years old");
        return false;
    }

    // Check if date is more than 100 years ago
    if (age > 100) {
        alert("Please enter a valid Date of Birth");
        return false;
    }

    return true;
}

// Toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.parentElement.querySelector('.material-icons');
    
    if (input.type === "password") {
        input.type = "text";
        icon.textContent = "visibility";
    } else {
        input.type = "password";
        icon.textContent = "visibility_off";
    }
}

// Toggle sidebar
document.getElementById('menu-toggle').addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('main-content').classList.toggle('collapsed');
});
</script>
</body>
</html>
