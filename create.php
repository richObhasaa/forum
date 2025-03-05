<?php
// Mengimpor header halaman
require_once '../includes/header.php';

// Memeriksa apakah pengguna sudah login
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

if (!$is_logged_in) { // Jika tidak login, tampilkan pesan error dan tombol login
    echo '<div class="container my-4"><div class="alert alert-danger">You must be logged in to create a topic.</div></div>';
    echo '<div class="container my-4"><a href="/auth/login.php" class="btn btn-primary">Log In</a></div>';
    
    // Mengimpor footer sebelum keluar
    require_once '../includes/footer.php';
    exit; // Menghentikan eksekusi skrip
}

// Mendefinisikan kategori default jika database tidak tersedia
$default_categories = [
    ['category_id' => 1, 'category_name' => 'General Discussion'],
    ['category_id' => 2, 'category_name' => 'Technical Support'],
    ['category_id' => 3, 'category_name' => 'Course Feedback'],
    ['category_id' => 4, 'category_name' => 'Study Groups']
];

// Inisialisasi array untuk menyimpan kategori dari database
$categories = [];

// Mengecek apakah koneksi database tersedia
$db_connected = isset($conn) && !$conn->connect_error;

if ($db_connected) { // Jika koneksi database tersedia
    try {
        // Mengambil daftar kategori dari database
        $result = $conn->query("SELECT category_id, category_name FROM forum_categories ORDER BY category_name");
        
        // Jika ada hasil, masukkan ke dalam array kategori
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
        }
    } catch (Exception $e) {
        // Jika terjadi error, gunakan kategori default
    }
}

// Jika kategori dari database kosong, gunakan kategori default
if (empty($categories)) {
    $categories = $default_categories;
}

// Inisialisasi array untuk menyimpan daftar kursus
$courses = [];

if ($db_connected) { // Jika koneksi database tersedia
    try {
        // Mengambil daftar kursus yang berstatus "published"
        $result = $conn->query("SELECT course_id, title FROM courses WHERE status = 'published' ORDER BY title");

        // Jika ada hasil, masukkan ke dalam array courses
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $courses[] = $row;
            }
        }
    } catch (Exception $e) {
        // Jika terjadi error, daftar kursus tetap kosong (opsional)
    }
}
?>

<!-- Mulai tampilan HTML -->
<div class="container my-4">
    <div class="card">
        <!-- Header Formulir -->
        <div class="card-header bg-primary text-white">
            <h1 class="h3 mb-0">Create New Topic</h1>
        </div>

        <div class="card-body">
            <?php if (function_exists('display_message')) display_message(); ?> <!-- Menampilkan pesan jika ada -->

            <!-- Form untuk membuat topik -->
            <form action="process_topic.php" method="POST" enctype="multipart/form-data">
                
                <!-- Pilihan Kategori -->
                <div class="mb-3">
                    <label for="category_id" class="form-label">Select Category <span class="text-danger">*</span></label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">Choose a category...</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Pilihan Kursus (Opsional) -->
                <?php if (!empty($courses)): ?>
                <div class="mb-3">
                    <label for="course_id" class="form-label">Related Course (Optional)</label>
                    <select class="form-select" id="course_id" name="course_id">
                        <option value="">Not related to a specific course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['course_id']; ?>">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">If your topic is related to a specific course, select it here.</div>
                </div>
                <?php endif; ?>

                <!-- Input Judul Topik -->
                <div class="mb-3">
                    <label for="title" class="form-label">Topic Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" required>
                    <div class="form-text">Be specific and descriptive</div>
                </div>

                <!-- Input Isi Topik -->
                <div class="mb-3">
                    <label for="message" class="form-label">Content <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                </div>

                <!-- Upload Lampiran -->
                <div class="mb-3">
                    <label for="attachments" class="form-label">Attachments (Optional)</label>
                    <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                    <div class="form-text">You can upload images, documents or other files (Max 5MB each)</div>
                </div>

                <!-- Tombol Submit dan Batal -->
                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary">Create Topic</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
                
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> <!-- Mengimpor footer -->
