<?php
session_start();
include('include/config.php');

// Check if employee is logged in
if (!isset($_SESSION['eid']) || empty($_SESSION['eid'])) {
    header('location: index.php');
    exit();
}

$error = "";
$success = "";
$eid = $_SESSION['eid'];

// Fetch employee data with department name
try {
    // Fetch employee data along with department name in a single query
    $sql = "SELECT 
    e.*, 
    d.id AS DepartmentId, 
    d.DepartmentName AS DeptName,
    des.DesignationName
FROM 
    tblemployees e
LEFT JOIN 
    tbldepartments d 
ON 
    e.Department = d.id
LEFT JOIN 
    tbldesignation des 
ON 
    e.designationid = des.id
WHERE 
    e.id = :eid
";
    $query = $dbh->prepare($sql);
    $query->bindParam(':eid', $eid, PDO::PARAM_INT);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_OBJ);
    
    if (!$result) {
        header('location: logout.php');
        exit();
    }

    // Fetch all departments for the dropdown
    $sql = "SELECT id, DepartmentName FROM tbldepartments ORDER BY DepartmentName";
    $query = $dbh->prepare($sql);
    $query->execute();
    $departments = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all designations for the dropdown
    $sql = "SELECT d.id, d.DesignationName, dept.DepartmentName FROM tbldesignation d LEFT JOIN tbldepartments dept ON d.deptid = dept.id ORDER BY dept.DepartmentName, d.DesignationName";
    $query = $dbh->prepare($sql);
    $query->execute();
    $designations = $query->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($departments)) {
        $error = "No departments found. Please contact your administrator.";
    }
} catch (PDOException $e) {
    error_log("Department fetch error in myprofile.php: " . $e->getMessage());
    $departments = [];
    $error = "Unable to load departments. Please contact your administrator.";
}

// Handle form submission
if (isset($_POST['update'])) {
    // Get form data
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $mobileno = trim($_POST['mobileno']);
    $gender = $_POST['gender'];
    $dob = trim($_POST['dob']);
    $department = $_POST['department'];
    $designation = !empty($_POST['designation']) ? $_POST['designation'] : null;
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $country = trim($_POST['country']);

    // Validation
    if (empty($firstName) || empty($lastName) || empty($mobileno) || empty($dob) || empty($address) || empty($city) || empty($country)) {
        $error = "All fields are required";
    } elseif (!preg_match("/^[a-zA-Z ]{3,50}$/", $firstName)) {
        $error = "First name should only contain letters and be between 3-50 characters";
    } elseif (!preg_match("/^[a-zA-Z ]{3,50}$/", $lastName)) {
        $error = "Last name should only contain letters and be between 3-50 characters";
    } elseif (!preg_match("/^[0-9]{11}$/", $mobileno)) {
        $error = "Mobile number must be 11 digits";
    } 
    // Check for duplicate mobile number only if phone number was changed
   
        $sql = "SELECT COUNT(*) FROM tblemployees WHERE Phonenumber = :mobileno AND id != :eid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
        $query->bindParam(':eid', $eid, PDO::PARAM_INT);
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
    }    // City validation
    elseif (!preg_match("/^[a-zA-Z ]{3,50}$/", $city)) {
        $error = "City name must contain only letters and be between 3-50 characters";
    }
    // Country validation
    elseif (!preg_match("/^[a-zA-Z ]{3,50}$/", $country)) {
        $error = "Country name must contain only letters and be between 3-50 characters";
    } 
    
    // Only proceed with update if no error was found
    else {
        try {
            $sql = "UPDATE tblemployees SET 
                    FirstName = :firstName,
                    LastName = :lastName,
                    Phonenumber = :mobileno,
                    Gender = :gender,
                    Dob = :dob,
                    Department = :department,
                    designationid = :designation,
                    Address = :address,
                    City = :city,
                    Country = :country
                    WHERE id = :eid";
            
            $query = $dbh->prepare($sql);
            $query->bindParam(':firstName', $firstName, PDO::PARAM_STR);
            $query->bindParam(':lastName', $lastName, PDO::PARAM_STR);
            $query->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
            $query->bindParam(':gender', $gender, PDO::PARAM_STR);
            $query->bindParam(':dob', $dob, PDO::PARAM_STR);
            $query->bindParam(':department', $department, PDO::PARAM_STR);
            $query->bindParam(':designation', $designation, PDO::PARAM_INT);
            $query->bindParam(':address', $address, PDO::PARAM_STR);
            $query->bindParam(':city', $city, PDO::PARAM_STR);
            $query->bindParam(':country', $country, PDO::PARAM_STR);
            $query->bindParam(':eid', $eid, PDO::PARAM_INT);
            
            $query->execute();
            $_SESSION['success'] = "Profile updated successfully";
            header("Location: myprofile.php");
            exit();
        } catch (PDOException $e) {
            $error = "Error updating profile. Please try again.";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Update Employee Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

        #sidebar a,
        #sidebar button.sidebar-btn {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            border: none;
            background: none;
            transition: background 0.3s ease;
        }

        #sidebar a:hover,
        #sidebar button.sidebar-btn:hover {
            background-color: #e6fafa;
            color: #000;
        }

        #sidebar .material-icons {
            margin-right: 10px;
            font-size: 20px;
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
            border-color: #48A6A7;
            box-shadow: 0 0 0 0.2rem rgba(72, 166, 167, 0.25);
        }

        .btn-custom {
            background-color: #48A6A7;
            color: white;
            border-radius: 12px;
            font-weight: 500;
            padding: 10px;
            transition: 0.3s ease;
        }

        .btn-custom:hover {
            background-color: #3c8e8f;
        }

        .heading-colored {
            color: #2c7a7b;
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container-fluid">
            <button class="hamburger" id="menu-toggle"><span class="material-icons">menu</span></button>
            <a class="navbar-brand ms-2" href="#">EMPLEAVEHUB</a>
        </div>
    </nav>

    <!-- Sidebar -->
<div id="sidebar">
  <div class="sidebar-content">
    <div class="text-center py-4">
      <img src="assets/images/profile-image.png" class="rounded-circle mb-2" width="80" alt="Profile Image">
      <h6 class="mb-0" style="font-weight:600;"><?php echo htmlspecialchars($result->FirstName . ' ' . $result->LastName); ?></h6>
      <small class="text-muted"><?php echo htmlspecialchars($result->EmpId); ?></small>
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
    <div class="main-content" id="main-content">
        <h4 class="mb-4 heading-colored"><span class="material-icons me-2">edit</span> Update Employee Info</h4>

        <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlentities($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlentities($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <form class="form-section" method="POST">
            <div class="row g-4">
                <div class="col-md-6">
                    <label for="empcode" class="form-label">Employee Code</label>
                    <input type="text" class="form-control" name="empcode" id="empcode" 
                        value="<?php echo htmlentities($result->EmpId); ?>" autocomplete="off" readonly required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                        value="<?php echo htmlentities($result->EmailId); ?>" readonly autocomplete="off" required>
                </div>
                <div class="col-md-6">
                    <label for="firstName" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="firstName" name="firstName" 
                        value="<?php echo htmlentities($result->FirstName); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="lastName" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="lastName" name="lastName" 
                        value="<?php echo htmlentities($result->LastName); ?>" autocomplete="off" required>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Mobile Number</label>
                    <input type="tel" class="form-control" id="phone" name="mobileno" 
                        value="<?php echo htmlentities($result->Phonenumber); ?>" maxlength="11" autocomplete="off" required>
                </div>
                <div class="col-md-6">
                    <label for="gender" class="form-label">Gender</label>
                    <select class="form-select" id="gender" name="gender" required>
                        <option <?php echo ($result->Gender == 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option <?php echo ($result->Gender == 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option <?php echo ($result->Gender == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="dob" class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" id="birthdate" name="dob" 
                        value="<?php echo htmlentities($result->Dob); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="department" class="form-label">Department</label>                    <select class="form-select" id="department" name="department" required>
                        <?php if (!empty($departments)): ?>
                            <?php foreach($departments as $dept): ?>
                                <option value="<?php echo htmlentities($dept['id']); ?>" 
                                    <?php echo ($result->Department == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlentities($dept['DepartmentName']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="designation" class="form-label">Designation</label>
                    <select class="form-select" id="designation" name="designation">
                        <option value="">Select Designation</option>
                        <?php if (!empty($designations)): ?>
                            <?php foreach($designations as $desig): ?>
                                <option value="<?php echo htmlentities($desig['id']); ?>" 
                                    <?php echo ($result->designationid == $desig['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlentities($desig['DesignationName'] . ' (' . $desig['DepartmentName'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="address" class="form-label">Address</label>
                    <input type="text" class="form-control" id="address" name="address" 
                        value="<?php echo htmlentities($result->Address); ?>" autocomplete="off" required>
                </div>
                <div class="col-md-6">
                    <label for="city" class="form-label">City/Town</label>
                    <input type="text" class="form-control" id="city" name="city" 
                        value="<?php echo htmlentities($result->City); ?>" autocomplete="off" required>
                </div>
                <div class="col-md-6">
                    <label for="country" class="form-label">Country</label>
                    <input type="text" class="form-control" id="country" name="country" 
                        value="<?php echo htmlentities($result->Country); ?>" autocomplete="off" required>
                </div>
                <div class="col-12 mt-3">
                    <button type="submit" name="update" id="update" class="btn btn-custom w-100">Update</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('menu-toggle').addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('main-content').classList.toggle('collapsed');
        });
    </script>
</body>
</html>
