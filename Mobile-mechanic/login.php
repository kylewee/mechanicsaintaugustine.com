
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
    $email = $_POST['email'];
    $password = md5($_POST['password']);
    $ltype = $_POST['ltype'];

    if ($ltype == 'c') {
        // Customer login
        $result = mysqli_query($conn, "SELECT * FROM customer_reg WHERE cemail='$email' AND cpassword='$password'");
        $count = mysqli_num_rows($result);
        $data = mysqli_fetch_array($result);
        
        if ($count > 0) {
            $_SESSION["ltype"] = $ltype;
            $_SESSION["cemail"] = $data[3];
            $_SESSION["cname"] = $data[1];
            header("Location: cprofile.php");
            exit();
        } else {
            $error = "Invalid Customer Email/Password";
        }
    } elseif ($ltype == 'a') {
        // Admin login - note the column name is 'aemail' not 'email'
        $result = mysqli_query($conn, "SELECT * FROM admin WHERE aemail='$email' AND password='$password'");
        $count = mysqli_num_rows($result);
        $data = mysqli_fetch_array($result);
        
        if ($count > 0) {
            $_SESSION["ltype"] = $ltype;
            $_SESSION["cemail"] = $data[0];
            $_SESSION["cname"] = "admin";
            header("Location: admin.php");
            exit();
        } else {
            $error = "Invalid Admin Email/Password";
        }
    } else {
        // Mechanic login
        $result = mysqli_query($conn, "SELECT * FROM mechanic_reg WHERE memail='$email' AND mpassword='$password'");
        $count = mysqli_num_rows($result);
        $data = mysqli_fetch_array($result);
        
        if ($count > 0) {
            $_SESSION["ltype"] = $ltype;
            $_SESSION["memail"] = $data[4];
            $_SESSION["mname"] = $data[1];
            header("Location: mprofile.php");
            exit();
        } else {
            $error = "Invalid Mechanic Email/Password";
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