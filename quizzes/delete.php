<?php
// instructor/quizzes/delete.php - Specifies the file location and purpose
require_once '../../includes/header.php'; // Includes the header file from two directories up
requireRole('instructor'); // Restricts access to users with instructor role only

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['quiz_id'])) { // Checks if request is POST and quiz_id is set
    $quiz_id = (int)$_POST['quiz_id']; // Converts quiz_id from POST data to integer
    $instructor_id = $_SESSION['user_id']; // Retrieves instructor's ID from session

    // Verify quiz belongs to instructor's course - Comment indicating purpose of verification
    $verify_query = "SELECT q.quiz_id 
                    FROM quizzes q
                    JOIN courses c ON q.course_id = c.course_id
                    WHERE q.quiz_id = ? AND c.instructor_id = ?"; // SQL to verify quiz ownership through course
    
    $stmt = $conn->prepare($verify_query); // Prepares the verification query
    $stmt->bind_param("ii", $quiz_id, $instructor_id); // Binds quiz_id and instructor_id as integers
    $stmt->execute(); // Executes the verification query
    
    if ($stmt->get_result()->num_rows === 1) { // Checks if exactly one quiz was found (valid ownership)
        // Start transaction - Comment indicating beginning of database transaction
        $conn->begin_transaction(); // Begins a database transaction
        
        try { // Starts a try block for error handling
            // Delete quiz attempts - Comment indicating deletion of quiz attempts
            $stmt = $conn->prepare("DELETE FROM quiz_attempts WHERE quiz_id = ?"); // Prepares query to delete quiz attempts
            $stmt->bind_param("i", $quiz_id); // Binds quiz_id as integer
            $stmt->execute(); // Executes the quiz attempts deletion

            // Delete quiz options - Comment indicating deletion of quiz options
            $stmt = $conn->prepare("
                DELETE qo FROM quiz_options qo
                INNER JOIN quiz_questions qq ON qo.question_id = qq.question_id
                WHERE qq.quiz_id = ?
            "); // Prepares query to delete options linked to quiz questions
            $stmt->bind_param("i", $quiz_id); // Binds quiz_id as integer
            $stmt->execute(); // Executes the quiz options deletion

            // Delete quiz questions - Comment indicating deletion of quiz questions
            $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?"); // Prepares query to delete quiz questions
            $stmt->bind_param("i", $quiz_id); // Binds quiz_id as integer
            $stmt->execute(); // Executes the quiz questions deletion

            // Finally, delete the quiz - Comment indicating final quiz deletion
            $stmt = $conn->prepare("DELETE FROM quizzes WHERE quiz_id = ?"); // Prepares query to delete the quiz
            $stmt->bind_param("i", $quiz_id); // Binds quiz_id as integer
            $stmt->execute(); // Executes the quiz deletion

            // Commit transaction - Comment indicating transaction completion
            $conn->commit(); // Commits all changes to the database
            $_SESSION['success'] = "Quiz has been successfully deleted"; // Sets success message in session
            
        } catch (Exception $e) { // Catches any exceptions during deletion process
            // Roll back if any error occurs - Comment indicating error handling
            $conn->rollback(); // Rolls back all changes if an error occurs
            $_SESSION['error'] = "Error deleting quiz: " . $e->getMessage(); // Sets error message with exception details
        } // Closes catch block
    } else { // Executes if quiz verification fails
        $_SESSION['error'] = "Unauthorized access or quiz not found"; // Sets error message for invalid quiz
    } // Closes verification check
} // Closes POST and quiz_id check

// Redirect back - Comment indicating redirection
header("Location: index.php"); // Redirects to index page
exit; // Terminates script execution
?>