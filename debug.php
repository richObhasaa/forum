<?php
// forum/debug.php - A simple debugging file

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debugging Information</h1>";
echo "<p>This page is working!</p>";

// Start session and display session data
echo "<h2>Session Information:</h2>";
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Database connection test
echo "<h2>Database Connection Test:</h2>";
try {
    require_once '../includes/header.php'; // Ensure this file sets up $conn properly
    
    if (isset($conn)) {
        echo "<p>Database connection variable exists!</p>";
        
        if ($conn->connect_error) {
            echo "<p>Database connection error: " . $conn->connect_error . "</p>";
        } else {
            echo "<p>Database connection successful!</p>";
            
            // Check if forum_categories table exists
            $query = "SHOW TABLES LIKE 'forum_categories'";
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0) {
                echo "<p>forum_categories table exists!</p>";
                
                // Fetch and display categories
                $query = "SELECT * FROM forum_categories";
                $result = $conn->query($query);
                if ($result) {
                    echo "<p>Categories found: " . $result->num_rows . "</p>";
                    while ($row = $result->fetch_assoc()) {
                        echo "- " . htmlspecialchars($row['category_name']) . "<br>";
                    }
                }
            } else {
                echo "<p>forum_categories table does not exist!</p>";
            }
            
            // Check if forum_topics table exists
            $query = "SHOW TABLES LIKE 'forum_topics'";
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0) {
                echo "<p>forum_topics table exists!</p>";
            } else {
                echo "<p>forum_topics table does not exist!</p>";
            }
        }
    } else {
        echo "<p>Database connection variable doesn't exist!</p>";
    }
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// PHP Information
echo "<h2>PHP Information:</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
?>
