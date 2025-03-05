<?php
// forum/index.php - Updated to handle potential errors and display basic content regardless
require_once '../includes/header.php';

// Check for database connection
$db_connected = isset($conn) && $conn instanceof mysqli && !$conn->connect_error;

// Initialize categories array
$categories = [];

// Only try to fetch from database if connection exists
if ($db_connected) {
    try {
        // Check if forum_categories table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'forum_categories'");
        $categories_table_exists = $table_check && $table_check->num_rows > 0;
        
        if ($categories_table_exists) {
            // Fetch categories from database
            $result = $conn->query("SELECT category_id, category_name, category_description FROM forum_categories ORDER BY category_id");
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $categories[$row['category_id']] = [
                        'id' => $row['category_id'],
                        'name' => $row['category_name'],
                        'description' => $row['category_description'],
                        'topics' => []
                    ];
                }
                
                // Fetch topics for each category
                $topics_result = $conn->query("SELECT * FROM forum_topics ORDER BY created_at DESC LIMIT 20");
                if ($topics_result && $topics_result->num_rows > 0) {
                    while ($topic = $topics_result->fetch_assoc()) {
                        $category_id = $topic['category_id'];
                        if (isset($categories[$category_id])) {
                            $categories[$category_id]['topics'][] = $topic;
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        // If any error occurs, we'll fall back to sample data
    }
}

// If no categories found, use sample data
if (empty($categories)) {
    $categories = [
        1 => [
            'id' => 1,
            'name' => 'General Discussion',
            'description' => 'General topics related to e-learning and courses',
            'topics' => []
        ],
        2 => [
            'id' => 2,
            'name' => 'Technical Support',
            'description' => 'Get help with technical issues',
            'topics' => []
        ],
        3 => [
            'id' => 3,
            'name' => 'Course Feedback',
            'description' => 'Share your feedback about courses',
            'topics' => []
        ],
        4 => [
            'id' => 4,
            'name' => 'Study Groups',
            'description' => 'Find or create study groups for different courses',
            'topics' => []
        ]
    ];
}
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1>Forum</h1>
            <p class="mb-0">Welcome to the Mini E-Learning Forum! Feel free to discuss, ask questions, and share knowledge with fellow learners.</p>
        </div>
        <div>
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i>Create New Topic
            </a>
        </div>
    </div>
    
    <!-- Display success/error messages if any -->
    <?php if (function_exists('display_message')) display_message(); ?>

    <?php if (empty($categories)): ?>
        <div class="alert alert-info">No forum categories available at this time.</div>
    <?php else: ?>
        <?php foreach ($categories as $category): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="my-1"><?php echo htmlspecialchars($category['name']); ?></h4>
                    <p class="small mb-0"><?php echo htmlspecialchars($category['description']); ?></p>
                </div>
                
                <div class="card-body">
                    <?php if (empty($category['topics'])): ?>
                        <p class="text-center py-3">No topics yet. Be the first to create one!</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($category['topics'] as $topic): ?>
                                <a href="topic.php?id=<?php echo $topic['topic_id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($topic['title']); ?></h5>
                                        <small><?php echo date('M d, Y', strtotime($topic['created_at'])); ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>