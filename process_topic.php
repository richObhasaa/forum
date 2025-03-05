<?php
// forum/process_reply.php - Process forum reply submissions and actions
// Start output buffering
ob_start();

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include header after session is initialized
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to perform this action.";
    ob_end_clean(); // Clear buffered output
    header("Location: index.php");
    exit;
}

// Get the user ID from session
$user_id = $_SESSION['user_id'];

// Process POST submission for new reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $topic_id = isset($_POST['topic_id']) ? intval($_POST['topic_id']) : 0;
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    
    // Basic validation
    $errors = [];
    if (empty($topic_id)) {
        $errors[] = "Invalid topic";
    }
    if (empty($content)) {
        $errors[] = "Reply content is required";
    }
    
    // Check if the topic exists and is not closed
    if ($topic_id && isset($conn)) {
        $topic_check = $conn->prepare("SELECT status FROM forum_topics WHERE topic_id = ?");
        $topic_check->bind_param("i", $topic_id);
        $topic_check->execute();
        $result = $topic_check->get_result();
        
        if ($result->num_rows === 0) {
            $errors[] = "Topic does not exist";
        } else {
            $topic = $result->fetch_assoc();
            if ($topic['status'] === 'closed') {
                $errors[] = "This topic is closed and no longer accepts replies";
            }
        }
    }
    
    // Process file uploads if any
    $attachments = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        $upload_dir = '../uploads/forum/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        foreach ($_FILES['attachments']['name'] as $key => $name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['attachments']['tmp_name'][$key];
                $name = basename($name);
                $filename = uniqid() . '_' . $name;
                $destination = $upload_dir . $filename;
                
                // Check file size (5MB max)
                if ($_FILES['attachments']['size'][$key] > 5242880) {
                    $errors[] = "File $name exceeds maximum size (5MB)";
                    continue;
                }
                
                // Move the uploaded file
                if (move_uploaded_file($tmp_name, $destination)) {
                    $attachments[] = [
                        'filename' => $filename,
                        'original_filename' => $name,
                        'filesize' => $_FILES['attachments']['size'][$key],
                        'file_type' => $_FILES['attachments']['type'][$key]
                    ];
                } else {
                    $errors[] = "Failed to upload file: $name";
                }
            } else if ($_FILES['attachments']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                $errors[] = "Error uploading file: $name";
            }
        }
    }
    
    // If there are no errors, save the reply
    if (empty($errors)) {
        if (isset($conn)) {
            try {
                // Begin transaction
                $conn->begin_transaction();
                
                // Insert reply
                $insert_query = $conn->prepare("
                    INSERT INTO forum_replies 
                    (topic_id, user_id, content, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                
                $insert_query->bind_param("iis", $topic_id, $user_id, $content);
                
                if ($insert_query->execute()) {
                    $reply_id = $conn->insert_id;
                    
                    // Update the topic's last_reply info
                    $update_topic = $conn->prepare("
                        UPDATE forum_topics 
                        SET last_reply_at = NOW(), last_reply_user_id = ? 
                        WHERE topic_id = ?
                    ");
                    $update_topic->bind_param("ii", $user_id, $topic_id);
                    $update_topic->execute();
                    
                    // Insert attachments if any
                    if (!empty($attachments)) {
                        $attachment_query = $conn->prepare("
                            INSERT INTO forum_attachments 
                            (reply_id, user_id, filename, original_filename, filesize, file_type, uploaded_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        foreach ($attachments as $attachment) {
                            $attachment_query->bind_param(
                                "iissss", 
                                $reply_id, 
                                $user_id, 
                                $attachment['filename'], 
                                $attachment['original_filename'],
                                $attachment['filesize'],
                                $attachment['file_type']
                            );
                            
                            if (!$attachment_query->execute()) {
                                throw new Exception("Failed to save attachment");
                            }
                        }
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Set success message
                    $_SESSION['message'] = "Reply posted successfully!";
                    $_SESSION['message_type'] = "success";
                    
                    // Redirect back to the topic page
                    ob_end_clean(); // Clear output buffer
                    header("Location: topic.php?id=" . $topic_id . "#reply-" . $reply_id);
                    exit;
                } else {
                    throw new Exception("Failed to post reply");
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $errors[] = "Database error: " . $e->getMessage();
            }
        } else {
            // No database connection, simulate success for testing
            $_SESSION['message'] = "Reply posted successfully! (Test mode)";
            $_SESSION['message_type'] = "success";
            ob_end_clean(); // Clear output buffer
            header("Location: topic.php?id=" . $topic_id);
            exit;
        }
    }
    
    // If we got here, there were errors
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        ob_end_clean(); // Clear output buffer
        header("Location: topic.php?id=" . $topic_id);
        exit;
    }
}
// Process GET actions (delete, mark as solution, etc.)
else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $reply_id = intval($_GET['id']);
    
    // Validate reply ID
    if (empty($reply_id)) {
        $_SESSION['error'] = "Invalid reply ID";
        ob_end_clean(); // Clear output buffer
        header("Location: index.php");
        exit;
    }
    
    // Get reply and topic info
    if (isset($conn)) {
        $reply_query = $conn->prepare("
            SELECT r.*, t.topic_id, t.status 
            FROM forum_replies r
            JOIN forum_topics t ON r.topic_id = t.topic_id
            WHERE r.reply_id = ?
        ");
        $reply_query->bind_param("i", $reply_id);
        $reply_query->execute();
        $result = $reply_query->get_result();
        
        if ($result->num_rows === 0) {
            $_SESSION['error'] = "Reply not found";
            ob_end_clean(); // Clear output buffer
            header("Location: index.php");
            exit;
        }
        
        $reply = $result->fetch_assoc();
        $topic_id = $reply['topic_id'];
        
        // Check permissions based on action
        switch ($action) {
            case 'delete':
                // Only reply owner or admin can delete
                if ($reply['user_id'] != $user_id && (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin')) {
                    $_SESSION['error'] = "You don't have permission to delete this reply";
                    ob_end_clean(); // Clear output buffer
                    header("Location: topic.php?id=" . $topic_id);
                    exit;
                }
                
                // Delete attachments first
                $delete_attachments = $conn->prepare("DELETE FROM forum_attachments WHERE reply_id = ?");
                $delete_attachments->bind_param("i", $reply_id);
                $delete_attachments->execute();
                
                // Delete reply
                $delete_reply = $conn->prepare("DELETE FROM forum_replies WHERE reply_id = ?");
                $delete_reply->bind_param("i", $reply_id);
                
                if ($delete_reply->execute()) {
                    // Update the last reply info in the topic
                    $update_last_reply = $conn->query("
                        UPDATE forum_topics t
                        SET 
                            last_reply_at = (
                                SELECT MAX(created_at) 
                                FROM forum_replies 
                                WHERE topic_id = $topic_id
                            ),
                            last_reply_user_id = (
                                SELECT user_id 
                                FROM forum_replies 
                                WHERE topic_id = $topic_id 
                                ORDER BY created_at DESC 
                                LIMIT 1
                            )
                        WHERE t.topic_id = $topic_id
                    ");
                    
                    $_SESSION['message'] = "Reply deleted successfully";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['error'] = "Failed to delete reply";
                }
                
                ob_end_clean(); // Clear output buffer
                header("Location: topic.php?id=" . $topic_id);
                exit;
                break;
                
            case 'mark_solution':
                // Only instructors or admins can mark solutions
                if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'instructor' && $_SESSION['role'] != 'admin')) {
                    $_SESSION['error'] = "You don't have permission to mark solutions";
                    ob_end_clean(); // Clear output buffer
                    header("Location: topic.php?id=" . $topic_id);
                    exit;
                }
                
                // Clear any existing solutions for this topic
                $clear_solutions = $conn->prepare("UPDATE forum_replies SET is_solution = 0 WHERE topic_id = ?");
                $clear_solutions->bind_param("i", $topic_id);
                $clear_solutions->execute();
                
                // Mark this reply as the solution
                $mark_solution = $conn->prepare("UPDATE forum_replies SET is_solution = 1 WHERE reply_id = ?");
                $mark_solution->bind_param("i", $reply_id);
                
                if ($mark_solution->execute()) {
                    $_SESSION['message'] = "Reply marked as solution";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['error'] = "Failed to mark solution";
                }
                
                ob_end_clean(); // Clear output buffer
                header("Location: topic.php?id=" . $topic_id . "#reply-" . $reply_id);
                exit;
                break;
                
            default:
                $_SESSION['error'] = "Invalid action";
                ob_end_clean(); // Clear output buffer
                header("Location: topic.php?id=" . $topic_id);
                exit;
        }
    } else {
        // No database connection
        $_SESSION['error'] = "Database connection not available";
        ob_end_clean(); // Clear output buffer
        header("Location: index.php");
        exit;
    }
}
// Invalid request
else {
    ob_end_clean(); // Clear output buffer
    header("Location: index.php");
    exit;
}

// If we got here without a redirect, something went wrong
$_SESSION['error'] = "An unexpected error occurred";
ob_end_clean(); // Clear output buffer
header("Location: index.php");
exit;
?>