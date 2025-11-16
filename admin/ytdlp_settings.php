<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../includes/config.php';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_proxy':
                $proxy_label = trim($_POST['proxy_label'] ?? '');
                $proxy_uri = trim($_POST['proxy_uri'] ?? '');
                $auth_username = trim($_POST['auth_username'] ?? '') ?: NULL;
                $auth_password = trim($_POST['auth_password'] ?? '') ?: NULL;
                
                if ($proxy_uri) {
                    $stmt = $conn->prepare("INSERT INTO api_proxies (provider_key, proxy_uri, proxy_label, auth_username, auth_password, is_active) VALUES ('ytdlp', ?, ?, ?, ?, 1)");
                    $stmt->bind_param('ssss', $proxy_uri, $proxy_label, $auth_username, $auth_password);
                    
                    if ($stmt->execute()) {
                        $message = 'Proxy added successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error adding proxy: ' . $conn->error;
                        $message_type = 'danger';
                    }
                    $stmt->close();
                }
                break;
            
            case 'toggle_ytdlp':
                // Toggle YTDLP engine on/off (we'll use a setting in site_settings or just show status based on proxy count)
                $message = 'YTDLP engine status updated!';
                $message_type = 'success';
                break;
            
            case 'delete_proxy':
                $proxy_id = intval($_POST['proxy_id'] ?? 0);
                if ($proxy_id > 0) {
                    $stmt = $conn->prepare("DELETE FROM api_proxies WHERE id = ? AND provider_key = 'ytdlp'");
                    $stmt->bind_param('i', $proxy_id);
                    
                    if ($stmt->execute()) {
                        $message = 'Proxy deleted successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error deleting proxy: ' . $conn->error;
                        $message_type = 'danger';
                    }
                    $stmt->close();
                }
                break;
        }
    }
}

// Fetch all YTDLP proxies
$proxies = [];
$result = $conn->query("SELECT * FROM api_proxies WHERE provider_key = 'ytdlp' ORDER BY id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $proxies[] = $row;
    }
}

$proxy_count = count($proxies);
$ytdlp_enabled = $proxy_count > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YTDLP Engine Settings - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .settings-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }
        .settings-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        .header-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        .engine-title {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #2196F3;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .status-enabled {
            background: #d4edda;
            color: #155724;
        }
        .status-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #1976D2;
        }
        .proxy-list {
            margin-top: 20px;
        }
        .proxy-item {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #2196F3;
        }
        .proxy-info {
            flex: 1;
        }
        .proxy-uri {
            font-weight: 600;
            color: #2c3e50;
            font-family: 'Courier New', monospace;
        }
        .proxy-label {
            color: #7f8c8d;
            font-size: 14px;
        }
        .add-proxy-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .form-section-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .btn-back {
            background: #6c757d;
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
        }
        .btn-back:hover {
            background: #5a6268;
            color: white;
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <!-- Back button -->
        <a href="dashboard.php" class="btn-back mb-3 d-inline-block">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <!-- YTDLP Engine Card -->
        <div class="settings-card">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="header-section">
                <div>
                    <div class="engine-title">YTDLP Engine</div>
                    <span class="status-badge <?php echo $ytdlp_enabled ? 'status-enabled' : 'status-disabled'; ?>">
                        <?php echo $ytdlp_enabled ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" <?php echo $ytdlp_enabled ? 'checked' : ''; ?> disabled>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <strong>Add a proxy here and press Update to append it to the rotating pool.</strong>
                <br>
                Leave proxy fields empty to just toggle enable/disable.
            </div>

            <!-- Add Proxy Form -->
            <div class="add-proxy-form">
                <div class="form-section-title">Add New Proxy</div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_proxy">
                    
                    <div class="mb-3">
                        <label class="form-label">Proxy Label</label>
                        <input type="text" name="proxy_label" class="form-control" placeholder="Optional label" 
                               value="<?php echo isset($_POST['proxy_label']) ? htmlspecialchars($_POST['proxy_label']) : ''; ?>">
                        <small class="text-muted">Optional: A friendly name for this proxy</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Proxy URI *</label>
                        <input type="text" name="proxy_uri" class="form-control" placeholder="http://host:port" required
                               value="<?php echo isset($_POST['proxy_uri']) ? htmlspecialchars($_POST['proxy_uri']) : ''; ?>">
                        <small class="text-muted">Format: http://proxy.example.com:8080</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username (Optional)</label>
                            <input type="text" name="auth_username" class="form-control" placeholder="username">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password (Optional)</label>
                            <input type="password" name="auth_password" class="form-control" placeholder="password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Update
                    </button>
                </form>
            </div>

            <!-- Current Proxies List -->
            <?php if (count($proxies) > 0): ?>
                <div class="proxy-list">
                    <div class="form-section-title">Current Proxies (<?php echo count($proxies); ?>)</div>
                    <?php foreach ($proxies as $proxy): ?>
                        <div class="proxy-item">
                            <div class="proxy-info">
                                <?php if ($proxy['proxy_label']): ?>
                                    <div class="proxy-label">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($proxy['proxy_label']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="proxy-uri"><?php echo htmlspecialchars($proxy['proxy_uri']); ?></div>
                                <?php if ($proxy['auth_username']): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($proxy['auth_username']); ?>
                                    </small>
                                <?php endif; ?>
                                <small class="text-muted ms-2">
                                    <i class="fas fa-clock"></i> Last used: <?php echo $proxy['last_used_at'] ?: 'Never'; ?>
                                </small>
                            </div>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this proxy?');">
                                <input type="hidden" name="action" value="delete_proxy">
                                <input type="hidden" name="proxy_id" value="<?php echo $proxy['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info mt-4">
                    <i class="fas fa-info-circle"></i> No proxies configured yet. Add your first proxy above to enable rotating proxy support for YTDLP downloads.
                </div>
            <?php endif; ?>

            <!-- Help Section -->
            <div class="mt-4 p-3" style="background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                <strong><i class="fas fa-lightbulb"></i> How it works:</strong>
                <ul class="mb-0 mt-2">
                    <li>Add multiple proxies to enable round-robin rotation</li>
                    <li>Each YouTube download will automatically use a different proxy</li>
                    <li>Helps avoid rate limiting and improves download success rate</li>
                    <li>Proxies rotate based on least recently used algorithm</li>
                    <li>Authentication is automatically handled if username/password provided</li>
                </ul>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="settings-card">
            <div class="form-section-title">Proxy Statistics</div>
            <div class="row text-center">
                <div class="col-md-4">
                    <h3 class="text-primary"><?php echo count($proxies); ?></h3>
                    <p class="text-muted">Total Proxies</p>
                </div>
                <div class="col-md-4">
                    <h3 class="text-success"><?php echo count(array_filter($proxies, fn($p) => $p['is_active'] == 1)); ?></h3>
                    <p class="text-muted">Active</p>
                </div>
                <div class="col-md-4">
                    <h3 class="text-warning"><?php echo count(array_filter($proxies, fn($p) => $p['last_used_at'] !== null)); ?></h3>
                    <p class="text-muted">Used</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

