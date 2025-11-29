<?php
session_start();
include('includes/config.php');

if (isset($_POST['signin'])) {
  $uname = $_POST['username'];
  $password = $_POST['password']; // Do not hash

  $sql = "SELECT UserName, Password FROM admin WHERE UserName = :uname";
  $query = $dbh->prepare($sql);
  $query->bindParam(':uname', $uname, PDO::PARAM_STR);
  $query->execute();
  $result = $query->fetch(PDO::FETCH_OBJ);

  if ($result) {
      if (password_verify($password, $result->Password)) {
          $_SESSION['alogin'] = $uname;
          echo "<script type='text/javascript'> document.location = 'dashboard.php'; </script>";
      } else {
          echo "<script>alert('Invalid Password');</script>";
      }
  } else {
      echo "<script>alert('Invalid Username');</script>";
  }
}

?>





<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login | EMPFLOW</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts & Material Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #e0f7f7, #ffffff);
      margin: 0;
      padding-top: 60px;
    }

    .card {
      max-width: 500px;
      margin: 0 auto;
      padding: 40px 35px;
      border: none;
      border-radius: 20px;
      background: rgba(255, 255, 255, 0.95);
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
    }

    .card h4 {
      text-align: center;
      font-weight: 700;
      color: #48A6A7;
      margin-bottom: 25px;
    }

    .form-control {
      padding: 12px;
      border-radius: 10px;
    }

    .form-control:focus {
      border-color: #48A6A7;
      box-shadow: 0 0 0 0.15rem rgba(72, 166, 167, 0.25);
    }

    .btn-custom {
      background-color: #48A6A7;
      color: white;
      padding: 12px;
      font-weight: 500;
      border-radius: 8px;
      transition: background-color 0.3s ease, transform 0.2s ease;
    }

    .btn-custom:hover {
      background-color: #3a8d8d;
      transform: translateY(-1px);
    }

    .back-link {
      text-align: center;
      margin-top: 20px;
    }

    .back-link a {
      color: #555;
      font-weight: 500;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .back-link a:hover {
      color: #48A6A7;
    }

    .material-icons {
      vertical-align: middle;
    }
  </style>
</head>
<body>

  <!-- Login Form Card -->
  <div class="card mt-5">
    <h4><span class="material-icons">admin_panel_settings</span> Admin Login</h4>
    <form method="post" name="signin">
      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" class="form-control" id="username" name="username" required autocomplete="off">
      </div>
      <div class="mb-4">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required autocomplete="off">
      </div>
      <div class="d-grid">
        <button type="submit" name="signin" class="btn btn-custom">Sign In</button>
      </div>
    </form>

    <!-- Back Link -->
    <div class="back-link">
      <a href="../index.php">
        <span class="material-icons">arrow_back</span> Back
      </a>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
