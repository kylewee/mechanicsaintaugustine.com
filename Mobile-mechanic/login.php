
<?php
session_start();
include "connection.php";

// Check if user is already logged in
if (isset($_SESSION['cemail']) || isset($_SESSION['memail'])) {
    if ($_SESSION["ltype"] == 'c') {
        header("Location: cprofile.php");
        exit();
    } elseif ($_SESSION["ltype"] == 'a') {
        header("Location: admin.php");
        exit();
    } else {
        header("Location: mprofile.php");
        exit();
    }
}

$error = "";

if (isset($_POST['submit'])) {
    // Sanitize inputs
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $ltype = $_POST['ltype'];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        if ($ltype == 'c') {
            // Customer login - FIXED: Using prepared statement to prevent SQL injection
            $stmt = $conn->prepare("SELECT cid, cname, cgender, cemail, cpassword FROM customer_reg WHERE cemail = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $data = $result->fetch_assoc();
                // Support both legacy MD5 and new password_hash formats
                $password_valid = false;
                if (strlen($data['cpassword']) === 32) {
                    // Legacy MD5 hash
                    $password_valid = (md5($password) === $data['cpassword']);
                } else {
                    // Modern password_hash
                    $password_valid = password_verify($password, $data['cpassword']);
                }

                if ($password_valid) {
                    $_SESSION["ltype"] = $ltype;
                    $_SESSION["cemail"] = $data['cemail'];
                    $_SESSION["cname"] = $data['cname'];
                    header("Location: cprofile.php");
                    exit();
                } else {
                    $error = "Invalid Customer Email/Password";
                }
            } else {
                $error = "Invalid Customer Email/Password";
            }
            $stmt->close();

        } elseif ($ltype == 'a') {
            // Admin login - FIXED: Using prepared statement
            $stmt = $conn->prepare("SELECT aemail, password FROM admin WHERE aemail = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $data = $result->fetch_assoc();
                // Support both legacy MD5 and new password_hash formats
                $password_valid = false;
                if (strlen($data['password']) === 32) {
                    // Legacy MD5 hash
                    $password_valid = (md5($password) === $data['password']);
                } else {
                    // Modern password_hash
                    $password_valid = password_verify($password, $data['password']);
                }

                if ($password_valid) {
                    $_SESSION["ltype"] = $ltype;
                    $_SESSION["cemail"] = $data['aemail'];
                    $_SESSION["cname"] = "admin";
                    header("Location: admin.php");
                    exit();
                } else {
                    $error = "Invalid Admin Email/Password";
                }
            } else {
                $error = "Invalid Admin Email/Password";
            }
            $stmt->close();

        } else {
            // Mechanic login - FIXED: Using prepared statement
            $stmt = $conn->prepare("SELECT mid, mname, mgender, mphone, memail, mpassword FROM mechanic_reg WHERE memail = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $data = $result->fetch_assoc();
                // Support both legacy MD5 and new password_hash formats
                $password_valid = false;
                if (strlen($data['mpassword']) === 32) {
                    // Legacy MD5 hash
                    $password_valid = (md5($password) === $data['mpassword']);
                } else {
                    // Modern password_hash
                    $password_valid = password_verify($password, $data['mpassword']);
                }

                if ($password_valid) {
                    $_SESSION["ltype"] = $ltype;
                    $_SESSION["memail"] = $data['memail'];
                    $_SESSION["mname"] = $data['mname'];
                    header("Location: mprofile.php");
                    exit();
                } else {
                    $error = "Invalid Mechanic Email/Password";
                }
            } else {
                $error = "Invalid Mechanic Email/Password";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Mobile Mechanic Login</title>
    <link href="CSS/bootstrap.min.css" rel="stylesheet">
</head>
<body background="images/car4.jpg">
    <br><br><br><br>
    
    <?php if($error): ?>
        <div class="container">
            <div class="alert alert-danger text-center">
                <?php echo $error; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <form action="login.php" method="post">
        <font color="yellow">
            <div class="container">
                <div class="form-group mx-sm-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="Email" required>
                </div>
                <div class="form-group mx-lg-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
                <div class="form-group mx-sm-3" required>
                    <label><input type="radio" name="ltype" value="c" required>&nbsp;Customer</label><br>
                    <label><input type="radio" name="ltype" value="m" required>&nbsp;Mechanic</label><br>
                    <label><input type="radio" name="ltype" value="a" required>&nbsp;Admin</label>
                </div>
                <div class="form-group mx-sm-3">
                    <input type="submit" name="submit" value="Login" class="btn btn-success"><br><br>
                    <a href="register.php" class="text-warning">Register as Customer</a><br>
                    <a href="mregister.php" class="text-warning">Register as Mechanic</a>
                </div>
                
                <!-- Test credentials for easy testing -->
                <div class="form-group mx-sm-3">
                    <small class="text-warning">
                        <strong>Test Admin Login:</strong><br>
                        Email: admin@gmail.com<br>
                        Password: admin<br>
                        Select: Admin
                    </small>
                </div>
            </div>
        </font>
    </form>
</body>
</html>