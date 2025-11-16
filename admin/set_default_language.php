<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../includes/config.php';

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_default'])) {
    $new_default_id = (int)$_POST['default_language_id'];
    
    if ($new_default_id > 0) {
        // First, remove default from all languages
        $conn->query("UPDATE languages SET is_default = 0");
        
        // Then set the selected language as default
        $update = $conn->query("UPDATE languages SET is_default = 1 WHERE id = {$new_default_id}");
        
        if ($update) {
            $lang_res = $conn->query("SELECT name FROM languages WHERE id = {$new_default_id} LIMIT 1");
            $lang_name = $lang_res->fetch_assoc()['name'];
            
            $message = "✅ Success! <strong>{$lang_name}</strong> is now the default language. Frontend will show this language by default.";
            $message_type = 'success';
        } else {
            $message = "❌ Error: Could not update default language.";
            $message_type = 'danger';
        }
    }
}

// Get current default language
$current_default = null;
$default_res = $conn->query("SELECT * FROM languages WHERE is_default = 1 LIMIT 1");
if ($default_res && $default_res->num_rows > 0) {
    $current_default = $default_res->fetch_assoc();
}

// Get all languages
$all_languages = [];
$langs_res = $conn->query("SELECT * FROM languages ORDER BY name ASC");
if ($langs_res && $langs_res->num_rows > 0) {
    while ($row = $langs_res->fetch_assoc()) {
        $all_languages[] = $row;
    }
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">🌍 Set Default Language</h4>
                </div>
                <div class="card-body">
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-info">
                        <strong>ℹ️ How This Works:</strong>
                        <ul class="mb-0">
                            <li>Select a language below and click "Save as Default"</li>
                            <li>Frontend will automatically load in that language by default</li>
                            <li>Users can still switch to other languages using the dropdown</li>
                            <li>This setting affects both TikTok and YouTube downloaders</li>
                        </ul>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">📍 Current Default Language</h5>
                                    <?php if ($current_default): ?>
                                        <div class="alert alert-success">
                                            <h3 class="mb-2">
                                                <?php 
                                                $flags = [
                                                    'en' => '🇬🇧', 'de' => '🇩🇪', 'fr' => '🇫🇷', 'it' => '🇮🇹', 'es' => '🇪🇸',
                                                    'ja' => '🇯🇵', 'ko' => '🇰🇷', 'pt' => '🇵🇹', 'ru' => '🇷🇺', 'ar' => '🇸🇦',
                                                    'hi' => '🇮🇳', 'tr' => '🇹🇷', 'vi' => '🇻🇳', 'th' => '🇹🇭', 'id' => '🇮🇩',
                                                    'ms' => '🇲🇾', 'zh-cn' => '🇨🇳', 'zh-tw' => '🇹🇼', 'bn' => '🇧🇩',
                                                    'my' => '🇲🇲', 'tl' => '🇵🇭'
                                                ];
                                                $flag = $flags[$current_default['code']] ?? '🌍';
                                                echo $flag . ' ' . htmlspecialchars($current_default['name']);
                                                ?>
                                            </h3>
                                            <p class="mb-0"><strong>Code:</strong> <?php echo htmlspecialchars($current_default['code']); ?></p>
                                            <p class="mb-0"><strong>Direction:</strong> <?php echo strtoupper($current_default['direction']); ?></p>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <h6>🧪 Test Current Default:</h6>
                                            <a href="/" target="_blank" class="btn btn-primary btn-sm me-2">
                                                <i class="bi bi-box-arrow-up-right"></i> Open TikTok Downloader
                                            </a>
                                            <a href="/yt1s/" target="_blank" class="btn btn-success btn-sm">
                                                <i class="bi bi-box-arrow-up-right"></i> Open YouTube Downloader
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <strong>⚠️ No default language set!</strong> Please select one below.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">✏️ Change Default Language</h5>
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label for="default_language_id" class="form-label">Select Language:</label>
                                            <select name="default_language_id" id="default_language_id" class="form-select form-select-lg" required>
                                                <option value="">-- Choose Language --</option>
                                                <?php foreach ($all_languages as $lang): ?>
                                                    <option value="<?php echo $lang['id']; ?>" 
                                                            <?php echo ($current_default && $current_default['id'] == $lang['id']) ? 'selected' : ''; ?>>
                                                        <?php 
                                                        $flag = $flags[$lang['code']] ?? '🌍';
                                                        echo $flag . ' ' . htmlspecialchars($lang['name']) . ' (' . $lang['code'] . ')';
                                                        if ($current_default && $current_default['id'] == $lang['id']) {
                                                            echo ' - ✓ Current Default';
                                                        }
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="d-grid">
                                            <button type="submit" name="save_default" class="btn btn-success btn-lg">
                                                <i class="bi bi-save"></i> 💾 Save as Default Language
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row">
                        <div class="col-md-12">
                            <h5>📋 All Available Languages</h5>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Flag</th>
                                            <th>Language</th>
                                            <th>Code</th>
                                            <th>Direction</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_languages as $lang): ?>
                                            <tr class="<?php echo $lang['is_default'] ? 'table-success' : ''; ?>">
                                                <td style="font-size: 24px;">
                                                    <?php echo $flags[$lang['code']] ?? '🌍'; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($lang['name']); ?></strong>
                                                    <?php if ($lang['is_default']): ?>
                                                        <span class="badge bg-success">DEFAULT</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><code><?php echo htmlspecialchars($lang['code']); ?></code></td>
                                                <td><?php echo strtoupper($lang['direction']); ?></td>
                                                <td>
                                                    <?php
                                                    // Check if has content
                                                    $has_tiktok = $conn->query("SELECT COUNT(*) as cnt FROM languages_home WHERE language_id = {$lang['id']}")->fetch_assoc()['cnt'] > 0;
                                                    $has_yt = $conn->query("SELECT COUNT(*) as cnt FROM yt_page_content WHERE language_id = {$lang['id']}")->fetch_assoc()['cnt'] > 0;
                                                    
                                                    if ($has_tiktok && $has_yt) {
                                                        echo '<span class="badge bg-success">✓ Complete</span>';
                                                    } elseif ($has_tiktok || $has_yt) {
                                                        echo '<span class="badge bg-warning">⚠ Partial</span>';
                                                    } else {
                                                        echo '<span class="badge bg-danger">✗ Missing</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if (!$lang['is_default']): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="default_language_id" value="<?php echo $lang['id']; ?>">
                                                            <button type="submit" name="save_default" class="btn btn-sm btn-primary" 
                                                                    onclick="return confirm('Set <?php echo htmlspecialchars($lang['name']); ?> as default language?')">
                                                                Set as Default
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">✓ Active</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-4">
                        <strong>⚠️ Important Notes:</strong>
                        <ul class="mb-0">
                            <li>Changing the default language takes effect <strong>immediately</strong></li>
                            <li>Existing users may still see their previously selected language (from cookies)</li>
                            <li>New visitors will see the default language you set here</li>
                            <li>Make sure the selected language has content (translations) before setting as default</li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

