<?php
/**
 * Unified SMS Message Logs Archive
 */

$page_title = 'SMS Logs Archive';

// Include layouts and configurations
define('SECURE_ACCESS', true);
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/../models/SmsMessage.php';
require_once __DIR__ . '/../config/database.php';

// 1. Retrieve Filters
$direction = $_GET['direction'] ?? 'ALL';
$dbDirection = ($direction !== 'ALL') ? $direction : null;

// 2. Fetch records
$messages = SmsMessage::getAll(100, 0, $dbDirection);
?>

<!-- Filter Console -->
<section class="panel" style="margin-bottom: 24px; padding: 16px 24px;">
    <form method="GET" action="inbox.php" style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
        <span style="font-size: 12px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase;">🔍 Log Filters:</span>
        
        <div style="display:flex; flex-direction:column; gap:4px;">
            <label style="font-size: 10px; color: var(--text-muted); font-weight:700; text-transform:uppercase;">Message Direction</label>
            <select name="direction" class="form-control" onchange="this.form.submit()" style="padding: 6px 12px; font-size:12.5px; width: 160px; background-color: var(--bg-primary);">
                <option value="ALL" <?php echo $direction == 'ALL' ? 'selected' : ''; ?>>All Directions</option>
                <option value="incoming" <?php echo $direction == 'incoming' ? 'selected' : ''; ?>>Incoming (From victims)</option>
                <option value="outgoing" <?php echo $direction == 'outgoing' ? 'selected' : ''; ?>>Outgoing (Responses)</option>
            </select>
        </div>
        
        <?php if ($direction !== 'ALL'): ?>
            <a href="inbox.php" style="color: var(--accent-red); font-size:12px; font-weight:700; text-decoration:none; margin-top: 14px;">✕ CLEAR FILTER</a>
        <?php endif; ?>
    </form>
</section>

<!-- Message Table -->
<section class="panel">
    <div class="panel-title">
        <span>📋 Transactional Message Logs</span>
        <span style="font-size: 11px; color: var(--text-secondary);"><?php echo count($messages); ?> records matching</span>
    </div>
    
    <div class="custom-table-container">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width: 8%;">ID</th>
                    <th style="width: 12%;">Direction</th>
                    <th style="width: 20%;">From Number</th>
                    <th style="width: 20%;">To Number</th>
                    <th style="width: 28%;">Message Body</th>
                    <th style="width: 12%;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 50px;">No messages found in logs.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <tr>
                            <td style="font-feature-settings: 'tnum';">#<?php echo $msg['id']; ?></td>
                            <td>
                                <span style="font-weight: 700; font-size:11.5px; color: <?php echo $msg['direction'] == 'incoming' ? 'var(--accent-blue)' : 'var(--accent-green)'; ?>;">
                                    <?php echo strtoupper($msg['direction']); ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: var(--text-primary); font-feature-settings: 'tnum';">
                                    <?php echo htmlspecialchars($msg['from_number']); ?>
                                </strong>
                            </td>
                            <td>
                                <span style="color: var(--text-secondary); font-feature-settings: 'tnum';">
                                    <?php echo htmlspecialchars($msg['to_number']); ?>
                                </span>
                            </td>
                            <td style="white-space: normal; line-height: 1.5; word-break: break-word; color: var(--text-primary);">
                                <?php echo htmlspecialchars($msg['message_body']); ?>
                            </td>
                            <td>
                                <span class="status-tag <?php echo strtolower($msg['status']); ?>" style="font-size: 9px; padding: 2px 6px;">
                                    <?php echo htmlspecialchars($msg['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php
require_once __DIR__ . '/layout_footer.php';
?>
