<?php
/**
 * Contact Registry - Add, Select, Delete & View Contacts
 */

$page_title = 'Contact Registry';

define('SECURE_ACCESS', true);
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Contact.php';
require_once __DIR__ . '/../services/AuditLogger.php';

$db = Database::getConnection();

$actionMessage = '';
$actionSuccess = true;

// 1. Process Add Contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contact'])) {
    $phone = trim($_POST['phone_number']);
    $name = trim($_POST['contact_name']);
    $org = trim($_POST['organization']);
    $loc = trim($_POST['location']);

    $phone = preg_replace('/[^\d+]/', '', $phone);
    if (strpos($phone, '+') !== 0) {
        $phone = '+' . $phone;
    }

    if (strlen($phone) >= 11 && strlen($phone) <= 15 && !empty($name)) {
        $existing = Contact::getByPhoneNumber($phone);
        if ($existing) {
            $actionMessage = "A contact with phone number " . htmlspecialchars($phone) . " already exists.";
            $actionSuccess = false;
        } else {
            $stmt = $db->prepare("INSERT INTO contacts (phone_number, name, organization, location, total_messages, total_sos, last_message_at, created_at) 
                                  VALUES (:phone, :name, :org, :loc, 0, 0, NOW(), NOW())");
            $stmt->execute([
                ':phone' => $phone,
                ':name' => $name,
                ':org' => !empty($org) ? $org : null,
                ':loc' => !empty($loc) ? $loc : null
            ]);
            $newId = (int)$db->lastInsertId();
            AuditLogger::log('Operator', 'CONTACT_ADDED', 'CONTACT', $newId, "Added contact " . $name . " (" . $phone . ")");
            $actionMessage = "Contact \"" . htmlspecialchars($name) . "\" added successfully.";
            $actionSuccess = true;
        }
    } else {
        $actionMessage = "Invalid input. Please provide a valid phone number (with country code) and name.";
        $actionSuccess = false;
    }
}

// 2. Process Delete Single Contact
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $contact = Contact::getById($delId);
    if ($contact) {
        Contact::delete($delId);
        AuditLogger::log('Operator', 'CONTACT_DELETED', 'CONTACT', $delId, "Deleted contact " . $contact['name'] . " (" . $contact['phone_number'] . ")");
        $actionMessage = "Contact \"" . htmlspecialchars($contact['name']) . "\" deleted.";
        $actionSuccess = true;
    }
}

// 3. Process Bulk Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && !empty($_POST['selected_contacts'])) {
    $selectedIds = array_map('intval', $_POST['selected_contacts']);
    $count = count($selectedIds);
    Contact::deleteByIds($selectedIds);
    AuditLogger::log('Operator', 'CONTACTS_BULK_DELETED', 'CONTACT', null, "Bulk deleted " . $count . " contacts");
    $actionMessage = $count . " contact(s) deleted successfully.";
    $actionSuccess = true;
}

// Fetch all contacts
$contacts = Contact::getAll();
?>

<?php if (!empty($actionMessage)): ?>
    <div style="background: <?php echo $actionSuccess ? 'rgba(57, 211, 83, 0.08)' : 'rgba(255, 153, 0, 0.08)'; ?>; 
                border: 1px solid <?php echo $actionSuccess ? 'var(--accent-green)' : 'var(--accent-orange)'; ?>; 
                padding: 12px 18px; border-radius: 6px; margin-bottom: 24px; font-size: 14.5px; font-weight: 500;">
        🔔 <?php echo $actionMessage; ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px; align-items: start;">

    <!-- Add Contact Form -->
    <div class="panel">
        <div class="panel-title">
            <span>➕ Add New Contact</span>
            <span style="font-size: 11px; color: var(--text-secondary);">Register to contact registry</span>
        </div>

        <form method="POST" action="contacts.php">
            <input type="hidden" name="add_contact" value="1" />

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="contact_name" class="form-control" placeholder="e.g. Ravi Kumar" required />
            </div>

            <div class="form-group">
                <label>Phone Number (with Country Code)</label>
                <input type="text" name="phone_number" class="form-control" placeholder="e.g. +919876543210" required />
                <span style="font-size:11px; color:var(--text-muted); margin-top:4px; display:block;">Prefix with '+' and country code.</span>
            </div>

            <div class="form-group">
                <label>Organization (optional)</label>
                <input type="text" name="organization" class="form-control" placeholder="e.g. NDRF Unit 7" />
            </div>

            <div class="form-group">
                <label>Location / Area (optional)</label>
                <input type="text" name="location" class="form-control" placeholder="e.g. Chennai, Tamil Nadu" />
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 16px; width: 100%;">💾 SAVE CONTACT</button>
        </form>
    </div>

    <!-- Contact List with Select & Delete -->
    <div class="panel">
        <div class="panel-title">
            <span>👥 Contact Registry</span>
            <span style="font-size: 11px; color: var(--text-secondary);"><?php echo count($contacts); ?> contacts registered</span>
        </div>

        <form method="POST" action="contacts.php" id="contacts-form">
            <input type="hidden" name="bulk_delete" value="1" />

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px;">
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer; color:var(--text-secondary);">
                    <input type="checkbox" id="select-all" style="accent-color: var(--accent-blue);" /> Select All
                </label>
                <button type="submit" class="btn-primary" style="background: rgba(255,59,48,0.15); border:1px solid var(--accent-red); color:var(--accent-red); padding:8px 16px; font-size:12px; cursor:pointer;" onclick="return confirm('Delete selected contacts?')">
                    🗑️ DELETE SELECTED
                </button>
            </div>

            <div class="custom-table-container">
                <table class="custom-table" style="font-size: 12.5px;">
                    <thead>
                        <tr>
                            <th style="width:40px;"></th>
                            <th style="width:25%;">Name</th>
                            <th style="width:20%;">Phone Number</th>
                            <th style="width:20%;">Organization</th>
                            <th style="width:15%;">Location</th>
                            <th style="width:20%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; color:var(--text-muted); padding:50px;">No contacts registered yet. Use the form to add one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contacts as $c): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_contacts[]" value="<?php echo $c['id']; ?>" class="contact-checkbox" style="accent-color: var(--accent-blue);" />
                                    </td>
                                    <td>
                                        <strong style="color:var(--text-primary); font-size:13.5px;"><?php echo htmlspecialchars($c['name']); ?></strong>
                                    </td>
                                    <td style="font-feature-settings: 'tnum';">
                                        <?php echo htmlspecialchars($c['phone_number']); ?>
                                    </td>
                                    <td style="color:var(--text-secondary);">
                                        <?php echo !empty($c['organization']) ? htmlspecialchars($c['organization']) : '<span style="color:var(--text-muted);">-</span>'; ?>
                                    </td>
                                    <td style="color:var(--text-secondary);">
                                        <?php echo !empty($c['location']) ? htmlspecialchars($c['location']) : '<span style="color:var(--text-muted);">-</span>'; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:14px;">
                                            <a href="send_message.php?to=<?php echo urlencode($c['phone_number']); ?>" style="color:var(--accent-green); text-decoration:none; font-weight:700; font-size:11.5px;">📤 SMS</a>
                                            <a href="contacts.php?delete_id=<?php echo $c['id']; ?>" style="color:var(--accent-red); text-decoration:none; font-weight:700; font-size:11.5px;" onclick="return confirm('Delete this contact?')">🗑️ Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<!-- Active Conversation Threads (below) -->
<div class="panel" style="margin-top: 24px;">
    <div class="panel-title">
        <span>💬 Active Sender Threads & Channels</span>
        <span style="font-size: 11px; color: var(--text-secondary);">Conversations from incoming SMS</span>
    </div>
    <div class="custom-table-container">
        <table class="custom-table" style="font-size: 12.5px;">
            <thead>
                <tr>
                    <th style="width: 15%;">Channel ID</th>
                    <th style="width: 30%;">Sender Phone</th>
                    <th style="width: 20%;">Emergency Cases</th>
                    <th style="width: 20%;">Last Event</th>
                    <th style="width: 15%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmtThreads = $db->query("SELECT c.*, 
                    (SELECT COUNT(*) FROM sos_requests WHERE conversation_id = c.id) as total_sos,
                    (SELECT id FROM sos_requests WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_sos_id
                FROM conversations c ORDER BY c.last_message_at DESC");
                $threads = $stmtThreads->fetchAll();
                ?>
                <?php if (empty($threads)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; color:var(--text-muted); padding:40px;">No active conversations.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($threads as $t): ?>
                        <tr>
                            <td style="font-feature-settings: 'tnum';">#CONV-<?php echo $t['id']; ?></td>
                            <td>
                                <strong style="color:var(--text-primary); font-feature-settings:'tnum'; font-size:13.5px;">
                                    <?php echo htmlspecialchars($t['sender_phone']); ?>
                                </strong>
                            </td>
                            <td style="font-weight:600; color:<?php echo $t['total_sos'] > 0 ? 'var(--accent-red)' : 'var(--text-secondary)'; ?>;">
                                🚨 <?php echo $t['total_sos']; ?> alerts
                            </td>
                            <td style="color:var(--text-secondary); font-feature-settings:'tnum';">
                                <?php echo $t['last_message_at'] ? date('Y-m-d H:i', strtotime($t['last_message_at'])) : 'N/A'; ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:12px;">
                                    <?php if ($t['last_sos_id']): ?>
                                        <a href="sos.php?id=<?php echo $t['last_sos_id']; ?>" style="color:var(--accent-blue); text-decoration:none; font-weight:700; font-size:11.5px;">💬 VIEW</a>
                                    <?php endif; ?>
                                    <a href="send_message.php?to=<?php echo urlencode($t['sender_phone']); ?>" style="color:var(--accent-green); text-decoration:none; font-weight:700; font-size:11.5px;">📤 SMS</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('select-all').addEventListener('change', function() {
    var checkboxes = document.querySelectorAll('.contact-checkbox');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = this.checked;
    }
});
</script>

<?php
require_once __DIR__ . '/layout_footer.php';
?>
