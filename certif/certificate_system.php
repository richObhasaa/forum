<?php
// certificate_system.php - Class for handling certificate operations - Specifies the file name and purpose

require_once 'includes/config.php'; // Includes the database configuration file from the includes directory

class Certificate { // Defines the Certificate class
    private $pdo; // Declares a private property to store the PDO database connection

    public function __construct() { // Defines the public constructor method
        global $pdo; // Accesses the global PDO variable if it exists
        $this->pdo = $pdo ?? new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS); // Sets $pdo property, using global if available or creating new connection
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Configures PDO to throw exceptions on errors
    } // Closes constructor method

    public function getUserCertificates($userId) { // Defines public method to get certificates for a user
        $sql = "SELECT c.*, co.title AS course_title 
                FROM certificates c 
                LEFT JOIN courses co ON c.course_id = co.course_id 
                WHERE c.user_id = ? AND c.status = 'active'"; // Defines SQL query to fetch active certificates with course titles
        $stmt = $this->pdo->prepare($sql); // Prepares the SQL statement using PDO
        $stmt->execute([$userId]); // Executes the prepared statement with user ID parameter
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Returns all results as an associative array
    } // Closes getUserCertificates method
} // Closes Certificate class
?>