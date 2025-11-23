<?php

include "connection.php";
if (isset($_POST['submit'])) {
  // Sanitize and validate inputs
  $mname = trim($_POST['mname']);
  $gender = $_POST['gender'];
  $mphone = preg_replace('/[^0-9+\-\s()]/', '', $_POST['mphone']);
  $memail = filter_var($_POST['memail'], FILTER_SANITIZE_EMAIL);
  $password = $_POST['mpass'];
  $mpassword = $_POST['mconfirmpass'];
  $shopaddress = trim($_POST['maddress']);
  $maadhar = preg_replace('/[^0-9]/', '', $_POST['maadhar']);
  $mpan = strtoupper(trim($_POST['mpan']));
  $dlicence = trim($_POST['dlicence']);

  // Validate email
  if (!filter_var($memail, FILTER_VALIDATE_EMAIL)) {
    ?>
    <script type="text/javascript">
      alert("Invalid email format");
      window.location="register.php";
    </script>
    <?php
  } elseif ($password !== $mpassword) {
    // Check if passwords match
    ?>
    <script type="text/javascript">
      alert("Passwords do not match");
      window.location="register.php";
    </script>
    <?php
  } elseif (strlen($password) < 8) {
    // Minimum password length
    ?>
    <script type="text/javascript">
      alert("Password must be at least 8 characters long");
      window.location="register.php";
    </script>
    <?php
  } else {
    // FIXED: Use prepared statement to prevent SQL injection
    // FIXED: Use password_hash instead of MD5 for secure password hashing
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO mechanic_reg (mname, mgender, mphone, memail, mpassword, shopaddress, maadhar, mpan, dlicence) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssss", $mname, $gender, $mphone, $memail, $hashed_password, $shopaddress, $maadhar, $mpan, $dlicence);

    if ($stmt->execute()) {
      ?>
      <script type="text/javascript">
        window.location="index.php";
        alert("Registration Successful");
      </script>
      <?php
    } else {
      // Check for duplicate email
      if ($conn->errno === 1062) {
        ?>
        <script type="text/javascript">
          alert("Email already registered");
          window.location="register.php";
        </script>
        <?php
      } else {
        ?>
        <script type="text/javascript">
          alert("Registration failed. Please try again.");
          window.location="register.php";
        </script>
        <?php
      }
    }
    $stmt->close();
  }
}
?>