<?php
// Start session for potential future use
session_start();

// Set page title and description
$page_title = "Rotary Club ";
$page_description = "A comprehensive platform for club administration and members to manage events, works and communication";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        header {
            background-color: #1e3a8a;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .logo span {
            color: #f59e0b;
        }
        
        .auth-buttons a {
            color: white;
            text-decoration: none;
            margin-left: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .auth-buttons a.register {
            background-color: #f59e0b;
        }
        
        .auth-buttons a.login {
            background-color: #10b981;
        }
        
        .auth-buttons a:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .hero {
            text-align: center;
            padding: 4rem 2rem;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
        }
        
        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto 2rem;
        }
        
        .how-it-works {
            padding: 3rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .how-it-works h2 {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 2rem;
            color: #1e3a8a;
        }
        
        .steps {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 1.5rem;
        }
        
        .step {
            flex: 1;
            min-width: 200px;
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .step:hover {
            transform: translateY(-5px);
        }
        
        .step-number {
            background-color: #1e3a8a;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-weight: bold;
        }
        
        .step h3 {
            margin-bottom: 0.5rem;
            color: #1e3a8a;
        }
        
        footer {
            text-align: center;
            padding: 2rem;
            background-color: #1e3a8a;
            color: white;
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .steps {
                flex-direction: column;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo"><?php echo htmlspecialchars($page_title); ?> <span>System</span></div>
        <div class="auth-buttons">
            <a href="registration.php" class="register">Register</a>
            <a href="login.php" class="login">Login</a>
        </div>
    </header>
    
    <section class="hero">
        <h1>Welcome to <?php echo htmlspecialchars($page_title); ?></h1>
        <p><?php echo htmlspecialchars($page_description); ?></p>
    </section>
    
    <section class="how-it-works">
        <h2>How It Works?</h2>
        <div class="steps">
            <?php
            // Define the steps array
            $steps = [
                "Register & Upload" => "Complete your registration and upload your achievement PDF document.",
                "Admin Review" => "Our admin team will carefully review your application and documents.",
                "Approval Decision" => "The admin will make a decision regarding your membership approval.",
                "Email Notification" => "You'll receive an email notification about the approval decision.",
                "Access System" => "Once approved, you'll gain full access to the club management system."
            ];
            
            // Display each step
            $step_number = 1;
            foreach ($steps as $title => $description) {
                echo '
                <div class="step">
                    <div class="step-number">' . $step_number . '</div>
                    <h3>' . htmlspecialchars($title) . '</h3>
                    <p>' . htmlspecialchars($description) . '</p>
                </div>';
                $step_number++;
            }
            ?>
        </div>
    </section>
    
    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($page_title); ?>. All rights reserved.</p>
    </footer>
</body>
</html>