<?php
session_start();
include('includes/config.php');

// Check if admin is logged in
if (!isset($_SESSION['alogin'])) {
    header('location: index.php');
    exit();
}

// Get employee ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('location: manageemployee.php');
    exit();
}

$empid = intval($_GET['id']);

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

// Fetch employee data first
try {
    $sql = "SELECT e.*, d.DepartmentName FROM tblemployees e 
            LEFT JOIN tbldepartments d ON e.Department = d.id 
            WHERE e.id=:empid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':empid', $empid, PDO::PARAM_INT);
    $query->execute();
    $employee = $query->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        header('location: manageemployee.php');
        exit();
    }

    // Fetch departments
    $sql = "SELECT id, DepartmentName FROM tbldepartments";
    $query = $dbh->prepare($sql);
    $query->execute();
    $departments = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database Error: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token';
    } else {
        // Validate input
        $firstName = trim($_POST['firstName']);
        $lastName = trim($_POST['lastName']);
        $mobileno = trim($_POST['mobileno']);
        $gender = trim($_POST['gender']);
        $dob = trim($_POST['dob']);
        $department = intval($_POST['department']);
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $country = trim($_POST['country']);        
        
        // Validation
        if (empty($firstName) || empty($lastName) || empty($mobileno) || empty($dob) || empty($address) || empty($city) || empty($country)) {
            $error = "All fields are required";
        } 
        // First name validation (3-50 characters, letters only)
        elseif (!preg_match("/^[a-zA-Z ]{3,50}$/", $firstName)) {
            $error = "First name should only contain letters and be between 3-50 characters";
        }
        // Last name validation (3-50 characters, letters only)
        elseif (!preg_match("/^[a-zA-Z ]{3,50}$/", $lastName)) {
            $error = "Last name should only contain letters and be between 3-50 characters";
        }
        // Phone validation (11 digits)
        elseif (!preg_match("/^[0-9]{11}$/", $mobileno)) {
            $error = "Invalid phone number format. Must be 11 digits";
        }
        // Check for duplicate mobile number
       
            $sql = "SELECT COUNT(*) FROM tblemployees WHERE Phonenumber = :mobileno AND id != :empid";
            $query = $dbh->prepare($sql);
            $query->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
            $query->bindParam(':empid', $empid, PDO::PARAM_INT);
            $query->execute();
            if ($query->fetchColumn() > 0) {
                $error = "This mobile number is already registered with another employee";
            }
        
        // Date of birth validation
        elseif (strtotime($dob) > strtotime('today')) {
            $error = "Date of Birth cannot be in the future";
        } elseif (strtotime($dob) > strtotime('-18 years')) {
            $error = "Employee must be at least 18 years old";
        } elseif (strtotime($dob) < strtotime('-100 years')) {
            $error = "Please enter a valid Date of Birth";
        }
        // Address validation
        elseif (strlen($address) < 5) {
            $error = "Address is too short. Minimum 5 characters required";
        } elseif (strlen($address) > 200) {
            $error = "Address is too long. Maximum 200 characters allowed";
        } elseif (!preg_match("/^[a-zA-Z0-9\s,.\/-]+$/", $address)) {
            $error = "Address contains invalid characters";
        }        // City validation (3-50 characters, letters only)
        elseif (!preg_match("/^[a-zA-Z ]{3,50}$/", $city)) {
            $error = "City name must contain only letters and be between 3-50 characters";
        }
        // Country validation (3-50 characters, letters only)
        elseif (!preg_match("/^[a-zA-Z ]{3,50}$/", $country)) {
            $error = "Country name must contain only letters and be between 3-50 characters";
        } else {
            try {
                // Update employee information
                    $sql = "UPDATE tblemployees SET FirstName=:firstName, LastName=:lastName, 
                            Phonenumber=:mobileno, Gender=:gender, Dob=:dob, Department=:department, 
                            Address=:address, City=:city, Country=:country 
                            WHERE id=:empid";
                    
                    $query = $dbh->prepare($sql);
                    $query->bindParam(':firstName', $firstName, PDO::PARAM_STR);
                    $query->bindParam(':lastName', $lastName, PDO::PARAM_STR);
                    $query->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
                    $query->bindParam(':gender', $gender, PDO::PARAM_STR);
                    $query->bindParam(':dob', $dob, PDO::PARAM_STR);
                    $query->bindParam(':department', $department, PDO::PARAM_INT);
                    $query->bindParam(':address', $address, PDO::PARAM_STR);
                    $query->bindParam(':city', $city, PDO::PARAM_STR);
                    $query->bindParam(':country', $country, PDO::PARAM_STR);
                    $query->bindParam(':empid', $empid, PDO::PARAM_INT);
                    if ($query->execute()) {
                        $_SESSION['success'] = 'Employee record updated successfully';
                        header('location: manageemployee.php');
                        exit();
                    } else {
                        $error = 'Something went wrong. Please try again';
                    }
                } catch (PDOException $e) {
                    $error = 'Database Error: ' . $e->getMessage();
                }
            }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Update Employee Profile</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts & Material Icons -->
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
      gap: 12px; /* space between icon and text */
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
      box-shadow: 0 0 0 0.2rem rgba(72, 166, 167, 0.25);
    }

    .btn-custom {
      background-color:rgb(80, 173, 214);
      color: white;
      border-radius: 12px;
      font-weight: 500;
      padding: 10px;
      transition: 0.3s ease;
    }

    .btn-custom:hover {
      background-color: rgb(66, 155, 193);
    }

  

    
    .heading-colored {
      color:rgb(66, 155, 193);
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
  <h4 class="mb-4 heading-colored"><span class="material-icons me-2">edit</span> Update Employee Info</h4>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger" role="alert">
      <?php echo htmlspecialchars($error); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success" role="alert">
      <?php echo htmlspecialchars($success); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <form class="form-section" method="POST" id="updateEmployeeForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <div class="row g-4">
      <div class="col-md-6">
        <label class="form-label">Employee Code</label>
        <div class="p-2 bg-light rounded border"><?php echo htmlspecialchars($employee['EmpId']); ?></div>
        <input type="hidden" name="empcode" value="<?php echo htmlspecialchars($employee['EmpId']); ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Email</label>
        <div class="p-2 bg-light rounded border"><?php echo htmlspecialchars($employee['EmailId']); ?></div>
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($employee['EmailId']); ?>">
      </div>
      <div class="col-md-6">
        <label for="firstName" class="form-label">First Name</label>
        <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($employee['FirstName']); ?>" required>
      </div>
      <div class="col-md-6">
        <label for="lastName" class="form-label">Last Name</label>
        <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($employee['LastName']); ?>" required>
      </div>
      <div class="col-md-6">
        <label for="phone" class="form-label">Mobile Number</label>
        <input type="tel" class="form-control" id="phone" name="mobileno" value="<?php echo htmlspecialchars($employee['Phonenumber']); ?>" maxlength="11" required>
      </div>
      <div class="col-md-6">
        <label for="gender" class="form-label">Gender</label>
        <select class="form-select" id="gender" name="gender" required>
          <option value="Male" <?php echo ($employee['Gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
          <option value="Female" <?php echo ($employee['Gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
          <option value="Other" <?php echo ($employee['Gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
        </select>
      </div>
      <div class="col-md-6">
        <label for="dob" class="form-label">Date of Birth</label>
        <input type="date" class="form-control" id="birthdate" name="dob" value="<?php echo htmlspecialchars($employee['Dob']); ?>" required>
      </div>
      <div class="col-md-6">
        <label for="department" class="form-label">Department</label>
        <select class="form-select" id="department" name="department" required>
          <?php foreach ($departments as $dept): ?>
            <option value="<?php echo htmlspecialchars($dept['id']); ?>" <?php echo ($employee['Department'] == $dept['id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($dept['DepartmentName']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label for="address" class="form-label">Address</label>
        <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($employee['Address']); ?>" required>
      </div>
      <div class="col-md-6">
        <label for="city" class="form-label">City/Town</label>
        <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($employee['City']); ?>" required>
      </div>
      <div class="col-md-6">
        <label for="country" class="form-label">Country</label>
        <input type="text" class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($employee['Country']); ?>" required>
      </div>
      <div class="col-12 mt-3">
        <button type="submit" name="update" id="update" class="btn btn-custom w-100">Update</button>
      </div>
    </div>
  </form>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Client-side validation -->
<script>
document.getElementById('updateEmployeeForm').addEventListener('submit', function(e) {
    // Get form values
    const firstName = document.getElementById('firstName').value.trim();
    const lastName = document.getElementById('lastName').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const address = document.getElementById('address').value.trim();
    const city = document.getElementById('city').value.trim();
    const country = document.getElementById('country').value.trim();
    const dob = document.getElementById('birthdate').value;
    
    // Name validation (3-50 characters, letters only)
    const nameRegex = /^[a-zA-Z ]{3,50}$/;
    if (!nameRegex.test(firstName)) {
        alert("First name should only contain letters and be between 3-50 characters");
        e.preventDefault();
        return false;
    }
    if (!nameRegex.test(lastName)) {
        alert("Last name should only contain letters and be between 3-50 characters");
        e.preventDefault();
        return false;
    }

    // Email is no longer an editable field, so we don't need to validate it here

    // Phone validation (11 digits)
    if (!/^[0-9]{11}$/.test(phone)) {
        alert("Invalid phone number format. Must be 11 digits");
        e.preventDefault();
        return false;
    }

    // Address validation
    if (address.length < 5) {
        alert("Address is too short. Minimum 5 characters required");
        e.preventDefault();
        return false;
    }
    if (address.length > 200) {
        alert("Address is too long. Maximum 200 characters allowed");
        e.preventDefault();
        return false;
    }    if (!/^[a-zA-Z0-9\s,.\/-]+$/.test(address)) {
        alert("Address contains invalid characters");
        e.preventDefault();
        return false;
    }    
    
    // City validation
    if (!nameRegex.test(city)) {
        alert("City name must contain only letters and be between 3-50 characters");
        e.preventDefault();
        return false;
    }

    // Country validation
    if (!nameRegex.test(country)) {
        alert("Country name must contain only letters and be between 3-50 characters");
        e.preventDefault();
        return false;
    }

    // Date of Birth validations
    const dobDate = new Date(dob);
    const today = new Date();
    
    // Check if date is in the future
    if (dobDate > today) {
        alert("Date of Birth cannot be in the future");
        e.preventDefault();
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
        e.preventDefault();
        return false;
    }

    // Check if date is more than 100 years ago
    if (age > 100) {
        alert("Please enter a valid Date of Birth");
        e.preventDefault();
        return false;
    }

    return true;
});

// Sidebar toggle
document.getElementById('menu-toggle').addEventListener('click', function () {
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('main-content').classList.toggle('collapsed');
});
</script>
</body>
</html>
