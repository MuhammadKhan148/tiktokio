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
            case 'add':
                $provider_key = trim($_POST['provider_key'] ?? 'ytdlp');
                $proxy_uri = trim($_POST['proxy_uri'] ?? '');
                $auth_username = trim($_POST['auth_username'] ?? '') ?: NULL;
                $auth_password = trim($_POST['auth_password'] ?? '') ?: NULL;
                $notes = trim($_POST['notes'] ?? '') ?: NULL;
                
                if ($proxy_uri) {
                    $stmt = $conn->prepare("INSERT INTO api_proxies (provider_key, proxy_uri, auth_username, auth_password, notes) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param('sssss', $provider_key, $proxy_uri, $auth_username, $auth_password, $notes);
                    
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
            
            case 'toggle':
                $proxy_id = intval($_POST['proxy_id'] ?? 0);
                if ($proxy_id > 0) {
                    $stmt = $conn->prepare("UPDATE api_proxies SET is_active = NOT is_active WHERE id = ?");
                    $stmt->bind_param('i', $proxy_id);
                    
                    if ($stmt->execute()) {
                        $message = 'Proxy status updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error updating proxy: ' . $conn->error;
                        $message_type = 'danger';
                    }
                    $stmt->close();
                }
                break;
            
            case 'delete':
                $proxy_id = intval($_POST['proxy_id'] ?? 0);
                if ($proxy_id > 0) {
                    $stmt = $conn->prepare("DELETE FROM api_proxies WHERE id = ?");
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
            
            case 'reset_times':
                $provider_key = $_POST['provider_key'] ?? 'ytdlp';
                $stmt = $conn->prepare("UPDATE api_proxies SET last_used_at = NULL WHERE provider_key = ?");
                $stmt->bind_param('s', $provider_key);
                
                if ($stmt->execute()) {
                    $message = 'All proxy usage times reset successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error resetting times: ' . $conn->error;
                    $message_type = 'danger';
                }
                $stmt->close();
                break;
        }
    }
}

// Fetch all proxies
$proxies = [];
$result = $conn->query("SELECT * FROM api_proxies ORDER BY provider_key, id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $proxies[] = $row;
    }
}

// Calculate statistics
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0
];

foreach ($proxies as $proxy) {
    $stats['total']++;
    if ($proxy['is_active'] == 1) {
        $stats['active']++;
    } else {
        $stats['inactive']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxy Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container-custom {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
        }
        .proxy-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: box-shadow 0.3s;
        }
        .proxy-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .proxy-card.inactive {
            opacity: 0.6;
            background: #f8f9fa;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container-custom">
        <div class="header">
            <h1><i class="fas fa-network-wired"></i> YTDLP Proxy Management</h1>
            <p>Manage rotating proxies for YouTube downloads</p>
            <a href="dashboard.php" class="btn btn-light btn-sm">← Back to Dashboard</a>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['total']; ?></div>
                        <div>Total Proxies</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['active']; ?></div>
                        <div>Active Proxies</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['inactive']; ?></div>
                        <div>Inactive Proxies</div>
                    </div>
                </div>
            </div>

            <!-- Add New Proxy Form -->
            <div class="card mb-4" style="border: 2px dashed #667eea;">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-plus"></i> Add New Proxy</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Proxy URL *</label>
                                <input type="text" name="proxy_uri" class="form-control" placeholder="http://proxy.example.com:8080" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Provider</label>
                                <select name="provider_key" class="form-control">
                                    <option value="ytdlp" selected>YTDLP</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username (optional)</label>
                                <input type="text" name="auth_username" class="form-control" placeholder="proxy_user">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password (optional)</label>
                                <input type="password" name="auth_password" class="form-control" placeholder="proxy_password">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Notes (optional)</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="Add notes about this proxy..."></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Proxy</button>
                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#resetModal">
                            <i class="fas fa-redo"></i> Reset All Usage Times
                        </button>
                    </form>
                </div>
            </div>

            <!-- Proxy List -->
            <h5><i class="fas fa-list"></i> Proxy List</h5>
            <?php if (empty($proxies)): ?>
                <div class="alert alert-info">No proxies added yet. Add your first proxy above!</div>
            <?php else: ?>
                <?php foreach ($proxies as $proxy): ?>
                    <div class="proxy-card <?php echo $proxy['is_active'] == 0 ? 'inactive' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <strong>ID: <?php echo $proxy['id']; ?></strong>
                                <span class="status-badge <?php echo $proxy['is_active'] == 1 ? 'status-active' : 'status-inactive'; ?> ms-2">
                                    <?php echo $proxy['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            <div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="proxy_id" value="<?php echo $proxy['id']; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $proxy['is_active'] == 1 ? 'btn-secondary' : 'btn-success'; ?>">
                                        <?php echo $proxy['is_active'] == 1 ? 'Disable' : 'Enable'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this proxy?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="proxy_id" value="<?php echo $proxy['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">Proxy URL:</small><br>
                                <strong><?php echo htmlspecialchars($proxy['proxy_uri']); ?></strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Username:</small><br>
                                <strong><?php echo $proxy['auth_username'] ? htmlspecialchars($proxy['auth_username']) : 'No auth'; ?></strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Provider:</small><br>
                                <strong><?php echo strtoupper($proxy['provider_key']); ?></strong>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <small class="text-muted">Last Used:</small><br>
                                <strong><?php echo $proxy['last_used_at'] ?: 'Never'; ?></strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Created:</small><br>
                                <strong><?php echo $proxy['created_at']; ?></strong>
                            </div>
                        </div>
                        <?php if ($proxy['notes']): ?>
                            <div class="mt-2">
                                <small class="text-muted">Notes:</small><br>
                                <em><?php echo htmlspecialchars($proxy['notes']); ?></em>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reset Times Modal -->
    <div class="modal fade" id="resetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reset All Proxy Usage Times</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>This will reset the <code>last_used_at</code> field for all proxies to NULL.</p>
                    <p><strong>Use this for testing proxy rotation.</strong></p>
                    <p>After reset, the rotation will start fresh from the first proxy.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="reset_times">
                        <input type="hidden" name="provider_key" value="ytdlp">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Reset Times</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

