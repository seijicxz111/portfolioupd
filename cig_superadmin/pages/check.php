<?php
/**
 * CIG Admin Dashboard - System Health Check & Diagnostic Tool
 * Run this to verify your PHP environment is set up correctly
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>CIG Admin - System Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        .check { padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #ccc; }
        .success { background-color: #d4edda; border-left-color: #28a745; color: #155724; }
        .error { background-color: #f8d7da; border-left-color: #dc3545; color: #721c24; }
        .warning { background-color: #fff3cd; border-left-color: #ffc107; color: #856404; }
        .info { background-color: #d1ecf1; border-left-color: #17a2b8; color: #0c5460; }
        .check-title { font-weight: bold; margin-bottom: 5px; }
        .check-detail { font-size: 0.9em; margin-top: 5px; }
        .code { background-color: #f8f9fa; padding: 2px 6px; font-family: monospace; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #007bff; color: white; }
        .next-steps { background-color: #e7f3ff; padding: 20px; border-radius: 4px; margin-top: 20px; }
        .next-steps h3 { margin-top: 0; color: #004085; }
        .next-steps ol { margin: 10px 0; }
        .next-steps li { margin: 8px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 CIG Admin Dashboard - System Diagnostics</h1>

        <?php
        $passed = 0;
        $failed = 0;

        // Check PHP Version
        echo '<div class="check success">';
        echo '<div class="check-title">✓ PHP Version</div>';
        echo '<div class="check-detail">Running PHP ' . phpversion() . '</div>';
        echo '</div>';
        $passed++;

        // Check Required Extensions
        $extensions = ['pdo', 'pdo_mysql', 'json', 'session'];
        foreach ($extensions as $ext) {
            if (extension_loaded($ext)) {
                echo '<div class="check success">';
                echo '<div class="check-title">✓ Extension: ' . strtoupper($ext) . '</div>';
                echo '<div class="check-detail">Installed and enabled</div>';
                echo '</div>';
                $passed++;
            } else {
                echo '<div class="check error">';
                echo '<div class="check-title">✗ Extension: ' . strtoupper($ext) . ' MISSING</div>';
                echo '<div class="check-detail">This extension is required but not installed</div>';
                echo '</div>';
                $failed++;
            }
        }

        // Check File Permissions
        $files = [
            'db/config.php' => 'Database Configuration',
            'db/connection.php' => 'Database Connection Class',
            'pages/login.php' => 'Login Page',
        ];

        foreach ($files as $file => $desc) {
            $full_path = __DIR__ . '/' . $file;
            if (file_exists($full_path)) {
                echo '<div class="check success">';
                echo '<div class="check-title">✓ File: ' . $file . '</div>';
                echo '<div class="check-detail">' . $desc . ' - Found</div>';
                echo '</div>';
                $passed++;
            } else {
                echo '<div class="check error">';
                echo '<div class="check-title">✗ File: ' . $file . ' NOT FOUND</div>';
                echo '<div class="check-detail">' . $desc . ' - Missing</div>';
                echo '</div>';
                $failed++;
            }
        }

        // Check Database Connection
        echo '<div class="check info">';
        echo '<div class="check-title">Database Configuration Status</div>';
        
        if (file_exists(__DIR__ . '/db/config.php')) {
            require_once 'db/config.php';
            echo '<div class="check-detail">';
            echo '<strong>Host:</strong> ' . DB_HOST . '<br>';
            echo '<strong>Database:</strong> ' . DB_NAME . '<br>';
            echo '<strong>User:</strong> ' . DB_USER . '<br>';
            echo '<strong>Port:</strong> ' . DB_PORT . '<br>';
            echo '</div>';
            
            // Try to connect to database
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';
                $conn = new PDO($dsn, DB_USER, DB_PASS);
                
                echo '<div class="check success">';
                echo '<div class="check-title">✓ MySQL Server Connection</div>';
                echo '<div class="check-detail">Successfully connected to MySQL server</div>';
                echo '</div>';
                $passed++;
                
                // Check if database exists
                try {
                    $conn->exec("USE " . DB_NAME);
                    echo '<div class="check success">';
                    echo '<div class="check-title">✓ Database Exists</div>';
                    echo '<div class="check-detail">Database "' . DB_NAME . '" found and accessible</div>';
                    echo '</div>';
                    $passed++;
                    
                    // Check tables
                    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                    $required_tables = ['users', 'submissions', 'organizations', 'reviews'];
                    $found_tables = array_intersect($required_tables, $tables);
                    
                    if (count($found_tables) === count($required_tables)) {
                        echo '<div class="check success">';
                        echo '<div class="check-title">✓ Database Tables</div>';
                        echo '<div class="check-detail">All required tables found</div>';
                        echo '</div>';
                        $passed++;
                    } else {
                        echo '<div class="check error">';
                        echo '<div class="check-title">✗ Database Tables Missing</div>';
                        echo '<div class="check-detail">Found: ' . implode(', ', $found_tables) . '<br>Missing: ' . implode(', ', array_diff($required_tables, $found_tables)) . '</div>';
                        echo '</div>';
                        $failed++;
                    }
                } catch (PDOException $e) {
                    echo '<div class="check error">';
                    echo '<div class="check-title">✗ Database "' . DB_NAME . '" Not Found</div>';
                    echo '<div class="check-detail">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    echo '</div>';
                    $failed++;
                }
            } catch (PDOException $e) {
                echo '<div class="check error">';
                echo '<div class="check-title">✗ MySQL Connection Failed</div>';
                echo '<div class="check-detail">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '</div>';
                $failed++;
            }
        }

        // Summary
        echo '<div style="margin-top: 30px; padding: 15px; border-radius: 4px; background-color: #e8e8e8;">';
        echo '<strong>Summary:</strong> ' . $passed . ' passed, ' . $failed . ' failed';
        echo '</div>';

        // Next Steps
        if ($failed > 0) {
            echo '<div class="next-steps">';
            echo '<h3>⚙️ Fix Required Issues</h3>';
            echo '<ol>';
            echo '<li><strong>Install Missing PHP Extensions:</strong>';
            echo '<ul>';
            echo '<li>Windows (with XAMPP): Extensions usually pre-installed</li>';
            echo '<li>Linux: <code>sudo apt-get install php-mysql</code></li>';
            echo '<li>macOS: <code>brew install php</code></li>';
            echo '</ul>';
            echo '</li>';
            echo '<li><strong>Create MySQL Database:</strong>';
            echo '<ul>';
            echo '<li>Open phpMyAdmin or MySQL CLI</li>';
            echo '<li>Run: <code>CREATE DATABASE cig_admin;</code></li>';
            echo '<li>Import SQL schema from <code>db/schema.sql</code></li>';
            echo '</ul>';
            echo '</li>';
            echo '<li><strong>Update Database Credentials:</strong>';
            echo '<ul>';
            echo '<li>Edit <code>db/config.php</code></li>';
            echo '<li>Update DB_USER and DB_PASS with your MySQL credentials</li>';
            echo '</ul>';
            echo '</li>';
            echo '</ol>';
            echo '</div>';
        } else {
            echo '<div class="next-steps">';
            echo '<h3>✅ System Ready!</h3>';
            echo '<p>Your system is configured correctly.</p>';
            echo '<p><strong>Next step:</strong> Visit <code><a href="pages/login.php">pages/login.php</a></code></p>';
            echo '<p><strong>Default credentials:</strong></p>';
            echo '<ul>';
            echo '<li>Email: <code>admin@cig.edu.ph</code></li>';
            echo '<li>Password: <code>admin123</code></li>';
            echo '</ul>';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>
