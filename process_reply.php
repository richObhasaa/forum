<?php
// forum/process_topic.php - Process topic creation and actions
// Start output buffering at the very top
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

// Process POST submission for new topic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $course_id = isset($_POST['course_id']) && !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['message']) ? trim($_POST['message']) : ''; // Note field name is 'message' in the form
    
    // Basic validation
    $errors = [];
    if (empty($category_id)) {
        $errors[] = "Please select a category";
    }
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    if (empty($content)) {
        $errors[] = "Content is required";
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
    
    // If there are no errors, save the topic
    if (empty($errors)) {
        if (isset($conn)) {
            try {
                // Begin transaction
                $conn->begin_transaction();
                
                // Insert topic
                $insert_query = $conn->prepare("
                    INSERT INTO forum_topics 
                    (category_id, course_id, user_id, title, content, status, created_at, last_reply_at) 
                    VALUES (?, ?, ?, ?, ?, 'open', NOW(), NOW())
                ");
                
                $insert_query->bind_param("iiiss", $category_id, $course_id, $user_id, $title, $content);
                
                if ($insert_query->execute()) {
                    $topic_id = $conn->insert_id;
                    
                    // Insert attachments if any
                    if (!empty($attachments)) {
                        $attachment_query = $conn->prepare("
                            INSERT INTO forum_attachments 
                            (topic_id, user_id, filename, original_filename, filesize, file_type, uploaded_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        foreach ($attachments as $attachment) {
                            $attachment_query->bind_param(
                                "iissss", 
                                $topic_id, 
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
                    $_SESSION['message'] = "Topic created successfully!";
                    $_SESSION['message_type'] = "success";
                    
                    // Redirect to the new topic
                    ob_end_clean(); // Clear output buffer
                    header("Location: topic.php?id=" . $topic_id);
                    exit;
                } else {
                    throw new Exception("Failed to create topic");
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $errors[] = "Database error: " . $e->getMessage();
            }
        } else {
            // No database connection, simulate success
            $_SESSION['message'] = "Topic created successfully! (Test mode)";
            $_SESSION['message_type'] = "success";
            ob_end_clean(); // Clear output buffer
            header("Location: index.php");
            exit;
        }
    }
    
    // If we got here, there were errors
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        ob_end_clean(); // Clear output buffer
        header("Location: create.php");
        exit;
    }
}
// Process GET actions (close, open, delete, pin, unpin)
else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $topic_id = intval($_GET['id']);
    
    // Validate topic ID
    if (empty($topic_id)) {
        $_SESSION['error'] = "Invalid topic ID";
        ob_end_clean(); // Clear output buffer
        header("Location: index.php");
        exit;
    }
    
    // Get topic info
    if (isset($conn)) {
        $topic_query = $conn->prepare("SELECT * FROM forum_topics WHERE topic_id = ?");
        $topic_query->bind_param("i", $topic_id);
        $topic_query->execute();
        $result = $topic_query->get_result();
        
        if ($result->num_rows === 0) {
            $_SESSION['error'] = "Topic not found";
            ob_end_clean(); // Clear output buffer
            header("Location: index.php");
            exit;
        }
        
        $topic = $result->fetch_assoc();
        
        // Check permissions - only topic owner or admin can modify
        if ($topic['user_id'] != $user_id && (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin')) {
            $_SESSION['error'] = "You don't have permission to modify this topic";
            ob_end_clean(); // Clear output buffer
            header("Location: topic.php?id=" . $topic_id);
            exit;
        }
        
        // Process the action
        $success = false;
        switch ($action) {
            case 'close':
                $update = $conn->prepare("UPDATE forum_topics SET status = 'closed' WHERE topic_id = ?");
                $update->bind_param("i", $topic_id);
                $success = $update->execute();
                $message = "Topic closed successfully";
                break;
                
            case 'open':
                $update = $conn->prepare("UPDATE forum_topics SET status = 'open' WHERE topic_id = ?");
                $update->bind_param("i", $topic_id);
                $success = $update->execute();
                $message = "Topic reopened successfully";
                break;
                
            case 'delete':
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Delete attachments
                    $delete_attachments = $conn->prepare("DELETE FROM forum_attachments WHERE topic_id = ?");
                    $delete_attachments->bind_param("i", $topic_id);
                    $delete_attachments->execute();
                    
                    // Delete replies
                    $delete_replies = $conn->prepare("DELETE FROM forum_replies WHERE topic_id = ?");
                    $delete_replies->bind_param("i", $topic_id);
                    $delete_replies->execute();
                    
                    // Delete reactions if table exists
                    $table_check = $conn->query("SHOW TABLES LIKE 'forum_reactions'");
                    if ($table_check && $table_check->num_rows > 0) {
                        $delete_reactions = $conn->prepare("DELETE FROM forum_reactions WHERE topic_id = ?");
                        $delete_reactions->bind_param("i", $topic_id);
                        $delete_reactions->execute();
                    }
                    
                    // Delete topic
                    $delete_topic = $conn->prepare("DELETE FROM forum_topics WHERE topic_id = ?");
                    $delete_topic->bind_param("i", $topic_id);
                    $success = $delete_topic->execute();
                    
                    // Commit transaction
                    $conn->commit();
                    $message = "Topic deleted successfully";
                    
                    // Redirect to forum index
                    $_SESSION['message'] = $message;
                    $_SESSION['message_type'] = "success";
                    ob_end_clean(); // Clear output buffer
                    header("Location: index.php");
                    exit;
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $_SESSION['error'] = "Failed to delete topic: " . $e->getMessage();
                    ob_end_clean(); // Clear output buffer
                    header("Location: topic.php?id=" . $topic_id);
                    exit;
                }
                break;
                
            case 'pin':
                $update = $conn->prepare("UPDATE forum_topics SET status = 'pinned' WHERE topic_id = ?");
                $update->bind_param("i", $topic_id);
                $success = $update->execute();
                $message = "Topic pinned successfully";
                break;
                
            case 'unpin':
                $update = $conn->prepare("UPDATE forum_topics SET status = 'open' WHERE topic_id = ?");
                $update->bind_param("i", $topic_id);
                $success = $update->execute();
                $message = "Topic unpinned successfully";
                break;
                
            default:
                $_SESSION['error'] = "Invalid action";
                ob_end_clean(); // Clear output buffer
                header("Location: topic.php?id=" . $topic_id);
                exit;
        }
        
        // Set success message and redirect if not already redirected
        if ($success && $action != 'delete') {
            $_SESSION['message'] = $message;
            $_SESSION['message_type'] = "success";
            ob_end_clean(); // Clear output buffer
            header("Location: topic.php?id=" . $topic_id);
            exit;
        } else if (!$success && $action != 'delete') {
            $_SESSION['error'] = "Failed to perform action";
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