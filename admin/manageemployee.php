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

// Handle employee status change (active/inactive)
if (isset($_POST['toggle_status']) && !empty($_POST['empId'])) {
    $empId = intval($_POST['empId']);
    try {
        // Check if employee has any pending leaves
        $checkSql = "SELECT COUNT(*) FROM tblleaves WHERE empid = :empId AND Status = 0";
        $checkStmt = $dbh->prepare($checkSql);
        $checkStmt->bindParam(':empId', $empId, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->fetchColumn() > 0) {
            $_SESSION['error'] = "Cannot deactivate employee. They have pending leave applications.";
        } else {
            // Toggle employee status
            $sql = "UPDATE tblemployees SET Status = NOT Status WHERE id = :empId";
            $stmt = $dbh->prepare($sql);
            $stmt->bindParam(':empId', $empId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Employee status updated successfully";
            } else {
                $_SESSION['error'] = "Error updating employee status";
            }
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to remove POST data
    header("Location: manageemployee.php");
    exit();
}

// Fetch all employees with their department names
try {
    $sql = "SELECT e.*, d.DepartmentName 
            FROM tblemployees e 
            LEFT JOIN tbldepartments d ON e.Department = d.id 
            ORDER BY e.id DESC";
    $query = $dbh->prepare($sql);
    $query->execute();
    $employees = $query->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error fetching employees: " . $e->getMessage();
}

// Get messages from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin | Manage Departments</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
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
      margin-left: 260px;
      padding: 100px 30px 30px 30px;
      transition: margin-left 0.3s ease;
    }

    .main-content.collapsed {
      margin-left: 0;
    }

    .card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 0 12px rgba(0, 0, 0, 0.05);
    }

    .card h4 {
      color: #344C64;
    }

    .search-input {
      border-radius: 50rem;
    }

    .table thead th {
      background-color: #e9f8fa;
      color: #344C64;
      font-weight: 600;
      border: none;
    }

    .table tbody tr:hover {
      background-color: #f0fbfd;
    }

    .table td, .table th {
      vertical-align: middle;
    }

    .btn-action {
      border-radius: 20px;
      padding: 6px 12px;
      font-size: 14px;
      font-weight: 500;
    }

    .btn-view {
      background-color: transparent;
      border: 1px solid #7AC6D2;
      color: #7AC6D2;
    }

    .btn-view:hover {
      background-color: #7AC6D2;
      color: white;
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
        padding-top: 100px;
      }
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
  <h2 class="fw-bold mb-4" style="color: #344C64;">
    <i class="material-icons me-2" style="color: #7AC6D2;">people</i> Manage Employees
  </h2>

  <div class="card p-4">
    <?php if($error): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <?php echo htmlentities($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if($success): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <?php echo htmlentities($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">Employees Info</h4>
      <input type="text" class="form-control w-25 search-input" id="searchInput" placeholder="Search employees...">
    </div>

    <div class="table-responsive">
      <table class="table align-middle text-center">
        <thead>
          <tr>
            <th>#</th>
            <th>Emp Id</th>
            <th>Full Name</th>
            <th>Department</th>
            <th>Status</th>
            <th>Reg Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!empty($employees)): 
                $cnt = 1;
                foreach($employees as $emp):
          ?>
          <tr>
            <td><?php echo htmlentities($cnt); ?></td>
            <td><?php echo htmlentities($emp['EmpId']); ?></td>
            <td><?php echo htmlentities($emp['FirstName'] . ' ' . $emp['LastName']); ?></td>
            <td><?php echo htmlentities($emp['DepartmentName']); ?></td>
            <td>
              <span class="badge <?php echo $emp['Status'] ? 'bg-success' : 'bg-danger'; ?>">
                <?php echo $emp['Status'] ? 'Active' : 'Inactive'; ?>
              </span>
            </td>
            <td><?php echo htmlentities(date('Y-m-d H:i', strtotime($emp['RegDate']))); ?></td>
            <td>
              <a href="editemployee.php?id=<?php echo htmlentities($emp['id']); ?>" class="btn btn-view btn-action me-1">Edit</a>
              <form method="POST" style="display: inline;">
                <input type="hidden" name="empId" value="<?php echo htmlentities($emp['id']); ?>">
                <button type="submit" name="toggle_status" 
                        class="btn btn-danger btn-action"
                        onclick="return confirm('Are you sure you want to <?php echo $emp['Status'] ? 'deactivate' : 'activate'; ?> this employee?');">
                  <?php echo $emp['Status'] ? 'Deactivate' : 'Activate'; ?>
                </button>
              </form>
            </td>
          </tr>
          <?php 
                $cnt++;
                endforeach;
              else: 
          ?>
          <tr>
            <td colspan="7" class="text-center">No employees found</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <p class="text-muted mt-3">
      Showing <?php echo !empty($employees) ? '1 to ' . count($employees) . ' of ' . count($employees) : '0'; ?> entries
    </p>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('menu-toggle').addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('mainContent').classList.toggle('collapsed');
  });

  document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('table tbody tr');
    
    tableRows.forEach(row => {
        const empId = row.cells[1]?.textContent || '';
        const fullName = row.cells[2]?.textContent || '';
        const department = row.cells[3]?.textContent || '';
        
        const matchesSearch = empId.toLowerCase().includes(searchTerm) ||
                            fullName.toLowerCase().includes(searchTerm) ||
                            department.toLowerCase().includes(searchTerm);
        
        row.style.display = matchesSearch ? '' : 'none';
    });
  });

  // Initialize tooltips
  /*var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
  });8?
</script>
</body>
</html>
