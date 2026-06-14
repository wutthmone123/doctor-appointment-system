<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// Login ဝင်ထားရင် dashboard ကို ပို့မယ်
if (is_logged_in()) {
    $user = current_user();
    redirect(role_home($user['role']));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Appointment System</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;800&display=swap" rel="stylesheet">
    
    <style>
        /* Base Styles */
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            /* ဒုတိယပုံကလို မီးခိုးနုရောင် နောက်ခံပြောင်းထားပါသည် */
            background: #f4f6f9; 
            min-height: 100vh;
            color: #0e1134; /* အစိမ်းရင့်ရောင် စာသား */
        }

        .landing {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 5%;
            box-sizing: border-box;
        }

        /* Header & Navigation */
        .landing-header {
            padding: 2rem 0;
        }

        .landing-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .landing-logo {
            font-size: 1.25rem;
            font-weight: 800;
            color: #1f0f42;
        }

        .landing-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        /* Navigation Buttons */
        .landing-link {
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.6rem 1.5rem;
            border-radius: 50px; /* ခလုတ်အဝိုင်းလေးတွေ */
            transition: all 0.3s ease;
        }

        /* Log in Button (Outline) */
        .landing-link {
            color: #12093d;
            border: 1.5px solid #0c123e;
            background: transparent;
        }

        .landing-link:hover {
            background: #190f46;
            color: #ffffff;
        }

        /* Register Button (Solid) */
        .landing-link--primary {
            background: #240c6c;
            color: #ffffff;
            border: 1.5px solid #110e46;
        }

        .landing-link--primary:hover {
            background: #0a1239;
            border-color: #0d183a;
            color: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Hero Section (Center Content) */
        .landing-hero {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 2rem 0;
        }

        .landing-title {
            font-size: 5rem;
            font-weight: 800;
            line-height: 1.05;
            color: #091d3a;
            margin-bottom: 1.5rem;
            letter-spacing: -0.03em;
        }

        .landing-subtitle {
            font-size: 1.15rem;
            color: #0c2241;
            max-width: 650px;
            line-height: 1.6;
            margin-bottom: 3rem;
        }

        /* Main CTA Button */
        .btn-primary {
            display: inline-block;
            background: #14104d;
            color: #ffffff;
            font-weight: 600;
            font-size: 1.1rem;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(91, 104, 223, 0.2), 0 2px 4px -1px rgba(15, 118, 110, 0.1);
        }

        .btn-primary:hover {
            background: #141351;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(14, 19, 61, 0.3), 0 4px 6px -2px rgba(91, 104, 223, 0.1);
        }

        /* Footer */
        .landing-footer {
            padding: 2rem 0;
            color: #64748b;
            font-size: 0.9rem;
        }

        /* Responsive Design for Mobile */
        @media (max-width: 768px) {
            .landing-title {
                font-size: 3.5rem;
            }
            .landing-subtitle {
                font-size: 1rem;
                padding: 0 1rem;
            }
            .landing-logo {
                font-size: 1rem;
            }
            .landing-link {
                padding: 0.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="landing">
        <header class="landing-header">
            <div class="landing-nav">
                <span class="landing-logo">Doctor Appointment System</span>
                <nav class="landing-links">
                    <a href="login.php" class="landing-link">Log in</a>
                    <a href="register.php" class="landing-link landing-link--primary">Register</a>
                </nav>
            </div>
        </header>

        <main class="landing-hero">
            <h1 class="landing-title">Book better<br>Heal faster</h1>
            
            <a href="login.php" class="btn-primary">Make appointment</a>
        </main>

        <footer class="landing-footer text-center" style="text-align: center;">
            <!-- <p class="landing-footer-text">Doctor Appointment System</p> -->
        </footer>
    </div>
</body>
</html>