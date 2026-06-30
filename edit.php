<?php
require_once 'db.php';
require_login();

// 1. Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    
    // Non-admins shouldn't be able to edit subject/date or upload files.
    // They can only forward and comment on existing records.
    $is_admin = (($_SESSION['role'] ?? 'user') === 'admin');
    
    // If it's a new record and not admin, block it.
    if (empty($id) && !$is_admin) {
        die("Only administrators can create new files.");
    }
    
    $subject = $_POST['subject'] ?? '';
    $date = $_POST['date'] ?? date('Y-m-d');
    $forward_to = empty($id) ? '' : ($_POST['forward-to'] ?? '');
    $comment = $_POST['comment'] ?? '';

    for ($i=1; $i<=10; $i++) { ${'doc'.$i.'_path'} = ''; }

    if (!empty($id)) {
        $stmt_existing = $pdo->prepare("SELECT * FROM `data` WHERE id = ?");
        $stmt_existing->execute([$id]);
        $existing_data = $stmt_existing->fetch(PDO::FETCH_ASSOC);
        if ($existing_data) {
            for ($i=1; $i<=10; $i++) {
                ${'doc'.$i.'_path'} = $existing_data['doc'.$i] ?? '';
            }
        }
    }

    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $uploaded_files = [];
    for ($i=1; $i<=10; $i++) {
        $doc_key = 'doc'.$i;
        if (empty(${'doc'.$i.'_path'}) && !empty($_FILES[$doc_key]['name'])) {
            $filename = basename($_FILES[$doc_key]['name']);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'txt', 'csv'];
            if (!in_array($ext, $allowed_exts)) {
                die("<div style='padding: 20px; font-family: sans-serif; color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; max-width: 600px; margin: 40px auto; text-align: center;'><h2>Security Block</h2><p>Error: Only PDF, Word, Excel, Images, Text, and CSV files are permitted for security reasons.</p><a href='javascript:history.back()' style='display: inline-block; margin-top: 15px; padding: 10px 20px; background: #0056b3; color: white; text-decoration: none; border-radius: 4px;'>Go Back</a></div>");
            }
            
            $new_filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            ${'doc'.$i.'_path'} = $upload_dir . $new_filename;
            move_uploaded_file($_FILES[$doc_key]['tmp_name'], ${'doc'.$i.'_path'});
            $uploaded_files[] = ['name' => $filename, 'path' => ${'doc'.$i.'_path'}];
        }
    }
    $file_addition_note = "";
    if (!empty($uploaded_files)) {
        $notes = [];
        foreach($uploaded_files as $f) {
            $notes[] = "[FILE: " . $f['name'] . "|" . $f['path'] . "]";
        }
        $file_addition_note = "\n\n" . implode("\n", $notes);
    }

    if (!empty($id)) {
        if (!$is_admin && !empty($existing_data)) {
            // Restore original subject and date to prevent tampering
            $subject = $existing_data['subject'] ?? $subject;
            $date = $existing_data['date'] ?? $date;
        }

        $sql = "UPDATE `data` SET 
                `subject` = ?, `date` = ?, `forward-to` = ?,
                `doc1`=?, `doc2`=?, `doc3`=?, `doc4`=?, `doc5`=?, `doc6`=?, `doc7`=?, `doc8`=?, `doc9`=?, `doc10`=? 
                WHERE `id` = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$subject, $date, $forward_to, $doc1_path, $doc2_path, $doc3_path, $doc4_path, $doc5_path, $doc6_path, $doc7_path, $doc8_path, $doc9_path, $doc10_path, $id]);

        // Insert into log table
        $log_sql = "INSERT INTO `log` (`record_id`, `name`, `forwarded_from`, `forwarded_to`, `forwarded_date`, `comment`) VALUES (?, ?, ?, ?, ?, ?)";
        $log_stmt = $pdo->prepare($log_sql);
        $final_comment = trim($comment . $file_addition_note);
        $log_stmt->execute([$id, $subject, $_SESSION['user_id'], $forward_to, date('Y-m-d H:i:s'), $final_comment]);
    } else {
        $sql = "INSERT INTO `data` (`subject`, `date`, `forward-to`, `doc1`, `doc2`, `doc3`, `doc4`, `doc5`, `doc6`, `doc7`, `doc8`, `doc9`, `doc10`, `created_at`, `created_by`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $created_at = $_POST['created_at'] ?? date('Y-m-d');
        $stmt->execute([$subject, $date, $forward_to, $doc1_path, $doc2_path, $doc3_path, $doc4_path, $doc5_path, $doc6_path, $doc7_path, $doc8_path, $doc9_path, $doc10_path, $created_at, $_SESSION['user_id']]);
        $new_id = $pdo->lastInsertId();
        
        $log_sql = "INSERT INTO `log` (`record_id`, `name`, `forwarded_from`, `forwarded_to`, `forwarded_date`, `comment`) VALUES (?, ?, ?, ?, ?, ?)";
        $log_stmt = $pdo->prepare($log_sql);
        $initial_comment = trim('Initial file created. ' . $comment . $file_addition_note);
        $log_stmt->execute([$new_id, $subject, $_SESSION['user_id'], $forward_to, date('Y-m-d H:i:s'), $initial_comment]);
    }
    
    header("Location: dashboard.php");
    exit;
}

// 2. Handle GET
$id = $_GET['id'] ?? '';
$data = [];
$logs = [];

if (!empty($id)) {
    $stmt = $pdo->prepare("SELECT * FROM `data` WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data) {
        $log_stmt = $pdo->prepare("SELECT * FROM `log` WHERE `record_id` = ? ORDER BY `Sno.` ASC");
        $log_stmt->execute([$id]);
        $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        die("File not found.");
    }
}

// Fetch users for dropdown
$stmt_users = $pdo->query("SELECT `user-id`, `Level` FROM `land-table` ORDER BY `Level`, `user-id`");
$all_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

function val($key, $default='') {
    global $data;
    return isset($data[$key]) ? htmlspecialchars($data[$key]) : htmlspecialchars($default);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit File | File Management</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="background:#f9f9f9;">
    <?php render_nav(); ?>
    <div class="file-container" style="max-width: 1400px; margin: 20px auto;">
        <div style="margin-bottom: 20px;">
            <a href="dashboard.php" class="btn" style="background:#6c757d; text-decoration:none; display:inline-block; padding:8px 16px;">&larr; Back to Dashboard</a>
        </div>
        <h2><?= !empty($id) ? "Review File #".htmlspecialchars($id) : "Create New File" ?></h2>
        
        <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-start;">
            <div style="flex: 1; min-width: 300px; background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #cbd5e0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= val('id') ?>">
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Subject</label>
                            <textarea name="subject" required rows="3" style="resize:vertical; <?= (($_SESSION['role'] ?? 'user') !== 'admin') ? 'background:#e9ecef; cursor:not-allowed;' : '' ?>" <?= (($_SESSION['role'] ?? 'user') !== 'admin') ? 'readonly' : '' ?>><?= val('subject') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="date" value="<?= val('date', date('Y-m-d')) ?>" required <?= (($_SESSION['role'] ?? 'user') !== 'admin') ? 'readonly style="background:#e9ecef; cursor:not-allowed;"' : '' ?>>
                        </div>
                        
                        <div id="dynamic-docs-container" style="grid-column: 1 / -1; background: #eef2f5; padding: 15px; border-radius: 6px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <h4 style="margin: 0; color: #333;">Documents</h4>
                                <button type="button" onclick="printMainDocuments()" style="background: #28a745; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">Print Documents</button>
                            </div>
                            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <?php 
                            $last_filled = 0;
                            for($k=1; $k<=10; $k++) {
                                if (val('doc'.$k)) $last_filled = $k;
                            }
                            ?>
                            <?php for($i=1; $i<=10; $i++): ?>
                            <?php 
                            $doc_val = val('doc'.$i); 
                            $is_hidden = ($i > $last_filled + 1) ? 'display:none !important;' : '';
                            ?>
                            <div class="form-group doc-group" id="doc<?= $i ?>-group" style="margin-bottom:10px; <?= $is_hidden ?>">
                                <label style="font-weight:600; display:block; margin-bottom:5px;">Document <?= $i ?> 
                                    <?php if($doc_val): ?>
                                        <a href="<?= $doc_val ?>" target="_blank" style="color: #28a745; font-size: 12px; text-decoration: none;">(View Uploaded)</a>
                                    <?php endif; ?>
                                </label>
                                <?php if(!$doc_val): ?>
                                    <?php if(($_SESSION['role'] ?? 'user') === 'admin'): ?>
                                        <input type="file" name="doc<?= $i ?>" id="doc<?= $i ?>" onchange="showNextDoc(<?= $i ?>)" style="width:100%;">
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-size: 14px;">No document uploaded</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #6c757d; font-size: 14px;">Already uploaded</span>
                                    <?php if(($_SESSION['role'] ?? 'user') === 'admin'): ?>
                                        <br><input type="file" name="doc<?= $i ?>" id="doc<?= $i ?>" onchange="showNextDoc(<?= $i ?>)" style="width:100%; margin-top:5px;" title="Replace current document">
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php endfor; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($id)): ?>
                        <div class="form-group">
                            <label>Forward To</label>
                            <select name="forward-to" id="forward-to">
                                <?php $current_fwd = val('forward-to'); ?>
                                <option value="" disabled <?= empty($current_fwd) ? 'selected' : '' ?>>Select User to Forward</option>
                                <?php foreach ($all_users as $u): ?>
                                    <?php if ($u['user-id'] === $_SESSION['user_id']) continue; ?>
                                    <option value="<?= htmlspecialchars($u['user-id']) ?>" <?= $current_fwd == $u['user-id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['user-id']) ?> (<?= htmlspecialchars($u['Level']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group full-width">
                            <label> Add Note: </label>
                            <textarea name="comment" rows="2" required></textarea>
                        </div>
                        
                        <div class="form-group full-width" style="margin-top: 15px;">
                            <button type="submit" class="btn full-width" style="padding: 12px; font-size: 16px; font-weight: bold;"><?= !empty($id) ? "Update & Forward Data" : "Create New Data" ?></button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- History section -->
            <div id="history-section" style="flex: 1; min-width: 350px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #cbd5e0; position: sticky; top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0056b3; padding-bottom: 10px; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: #0056b3;">File History</h3>
                    <?php if (!empty($id) && !empty($logs)): ?>
                    <div style="display: flex; gap: 5px;">
                        <button type="button" onclick="printHistory('all')" style="background: #0056b3; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">Print All</button>
                        <button type="button" onclick="printHistory('comments')" style="background: #17a2b8; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">Comments Only</button>
                        <button type="button" onclick="printHistory('documents')" style="background: #28a745; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">TOC</button>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="max-height: 70vh; overflow-y: auto;">
                    <?php if (empty($id)): ?>
                        <p style="color:#666;">Creating a new file. No history yet.</p>
                    <?php elseif (empty($logs)): ?>
                        <p style="color:#666;">No previous history found.</p>
                    <?php else: ?>
                        <ul style="padding-left: 0; list-style-type: none; margin-bottom:0; color:#555; font-size:14px;">
                        <?php foreach($logs as $index => $log): ?>
                            <?php
                            $commentText = !empty($log['comment']) ? (string)$log['comment'] : '';
                            $maxLength = 600;
                            $hasMore = strlen($commentText) > $maxLength;
                            
                            $formatComment = function($text) use ($data) {
                                $html = nl2br(htmlspecialchars($text));
                                $html = preg_replace_callback('/\[Added file\(s\):\s*(.*?)\]/', function($m) use ($data) {
                                    $files = explode(',', $m[1]);
                                    $out = '';
                                    foreach($files as $f) {
                                        $f = trim($f);
                                        $path = 'uploads/' . $f;
                                        // Attempt to find the real path in the data record
                                        for ($i = 1; $i <= 10; $i++) {
                                            $doc_path = $data['doc'.$i] ?? '';
                                            if (!empty($doc_path) && str_ends_with($doc_path, $f)) {
                                                $path = $doc_path;
                                                break;
                                            }
                                        }
                                        $out .= '<div class="history-doc-file" style="margin-top:6px; display:inline-block; margin-right:5px;"><a class="doc-link" href="'.htmlspecialchars($path).'" target="_blank" style="display:inline-flex; align-items:center; background:#f8fafc; border:1px solid #cbd5e1; color:#0f172a; padding:6px 10px; border-radius:6px; text-decoration:none; font-size:12px; font-weight:600; box-shadow:0 1px 2px rgba(0,0,0,0.05); transition:background 0.2s;">📎 ' . htmlspecialchars($f) . '</a></div>';
                                    }
                                    return $out;
                                }, $html);
                                $html = preg_replace('/\[FILE:\s*(.*?)\|(.*?)\]/', '<div class="history-doc-file" style="margin-top:6px; display:inline-block; margin-right:5px;"><a class="doc-link" href="$2" target="_blank" style="display:inline-flex; align-items:center; background:#f8fafc; border:1px solid #cbd5e1; color:#0f172a; padding:6px 10px; border-radius:6px; text-decoration:none; font-size:12px; font-weight:600; box-shadow:0 1px 2px rgba(0,0,0,0.05); transition:background 0.2s;">📎 $1</a></div>', $html);
                                return '<div class="history-comment-container">' . $html . '</div>';
                            };
                            
                            $displayComment = $formatComment($commentText);
                            $shortComment = $hasMore ? $formatComment(substr($commentText, 0, $maxLength)) . '...' : $displayComment;
                            $uniqueId = 'comment-' . $index;
                            ?>
                            <li style="margin-bottom: 15px; padding: 15px; background: #fff; border-left: 4px solid #17a2b8; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                <div style="font-size: 12px; color: #888; margin-bottom: 5px;"><?= htmlspecialchars($log['forwarded_date'] ?? '') ?></div>
                                <strong><?= htmlspecialchars($log['forwarded_from'] ?? 'System') ?></strong> &rarr; <strong><?= htmlspecialchars($log['forwarded_to'] ?? 'Unknown') ?></strong><br>
                                <div style="margin-top: 8px; color:#333;">
                                    <?php if ($hasMore): ?>
                                        <div id="<?= $uniqueId ?>-short">
                                            <?= $shortComment ?>
                                            <a href="javascript:void(0);" onclick="document.getElementById('<?= $uniqueId ?>-short').style.display='none'; document.getElementById('<?= $uniqueId ?>-full').style.display='block';" style="color: #0056b3; text-decoration: underline; font-size: 12px; margin-left: 5px;">Show more</a>
                                        </div>
                                        <div id="<?= $uniqueId ?>-full" style="display: none;">
                                            <?= $displayComment ?>
                                            <a href="javascript:void(0);" onclick="document.getElementById('<?= $uniqueId ?>-full').style.display='none'; document.getElementById('<?= $uniqueId ?>-short').style.display='block';" style="color: #0056b3; text-decoration: underline; font-size: 12px; margin-left: 5px;">Show less</a>
                                        </div>
                                    <?php else: ?>
                                        <?= $displayComment ?>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>
</body>
<script>
function showNextDoc(currentDocIndex) {
    var nextDocIndex = currentDocIndex + 1;
    if (nextDocIndex <= 10) {
        var nextGroup = document.getElementById('doc' + nextDocIndex + '-group');
        if (nextGroup) {
            nextGroup.style.setProperty('display', 'flex', 'important');
        }
    }
}

function printHistory(mode = 'all') {
    var printWindow = window.open('', '_blank', 'width=800,height=600');
    
    // Create a temporary container to manipulate the DOM
    var tempDiv = document.createElement('div');
    tempDiv.innerHTML = document.getElementById('history-section').innerHTML;
    
    // Remove the max-height and overflow from the scrollable container so it expands fully in print
    var scrollableDivs = tempDiv.querySelectorAll('div');
    scrollableDivs.forEach(function(div) {
        if (div.style.maxHeight || div.style.overflowY) {
            div.style.maxHeight = 'none';
            div.style.overflowY = 'visible';
            div.style.overflow = 'visible';
        }
    });
    
    // Process document links
    var links = tempDiv.querySelectorAll('a.doc-link');
    var fetchesCount = 0;
    var fetchesCompleted = 0;

    links.forEach(function(link) {
        var href = link.getAttribute('href');
        if (href) {
            var lowerHref = href.toLowerCase();
            var parentDiv = link.parentNode;
            var container = (parentDiv && parentDiv.parentNode) ? parentDiv.parentNode : null;
            var insertTarget = parentDiv || link;

            if (lowerHref.endsWith('.jpg') || lowerHref.endsWith('.jpeg') || lowerHref.endsWith('.png') || lowerHref.endsWith('.gif') || lowerHref.endsWith('.webp')) {
                var img = document.createElement('img');
                img.src = href;
                img.style.maxWidth = '100%';
                img.style.maxHeight = '600px';
                img.style.display = 'block';
                img.style.marginTop = '10px';
                img.style.marginBottom = '10px';
                img.style.border = '1px solid #ccc';
                img.style.borderRadius = '4px';
                
                if (container) container.insertBefore(img, insertTarget.nextSibling);
            } else if (lowerHref.endsWith('.pdf')) {
                var iframe = document.createElement('iframe');
                iframe.src = href;
                iframe.style.width = '100%';
                iframe.style.height = '600px';
                iframe.style.border = '1px solid #ccc';
                iframe.style.marginTop = '10px';
                iframe.style.marginBottom = '10px';
                
                if (container) container.insertBefore(iframe, insertTarget.nextSibling);
            } else if (lowerHref.endsWith('.txt') || lowerHref.endsWith('.csv')) {
                var pre = document.createElement('pre');
                var preId = 'pre-' + Math.random().toString(36).substr(2, 9);
                pre.id = preId;
                pre.style.width = '100%';
                pre.style.maxHeight = '600px';
                pre.style.overflow = 'auto';
                pre.style.whiteSpace = 'pre-wrap';
                pre.style.wordWrap = 'break-word';
                pre.style.background = '#f8fafc';
                pre.style.border = '1px solid #cbd5e1';
                pre.style.padding = '10px';
                pre.style.marginTop = '10px';
                pre.style.marginBottom = '10px';
                pre.style.borderRadius = '4px';
                pre.style.fontSize = '13px';
                pre.textContent = 'Loading file contents...';
                
                if (container) container.insertBefore(pre, insertTarget.nextSibling);

                fetchesCount++;
                fetch(href)
                    .then(response => response.text())
                    .then(text => { 
                        var targetPre = printWindow.document.getElementById(preId);
                        if (targetPre) targetPre.textContent = text;
                    })
                    .catch(e => { 
                        var targetPre = printWindow.document.getElementById(preId);
                        if (targetPre) targetPre.textContent = 'Error loading file: ' + e; 
                    })
                    .finally(() => {
                        fetchesCompleted++;
                        checkAndPrint();
                    });
            }
        }
    });

    if (mode === 'comments') {
        var docInfos = tempDiv.querySelectorAll('.history-doc-info');
        docInfos.forEach(function(el) { el.style.display = 'none'; });
        
        var docFiles = tempDiv.querySelectorAll('.history-doc-file');
        docFiles.forEach(function(el) { el.style.display = 'none'; });
        
        var imgs = tempDiv.querySelectorAll('img, iframe, pre');
        imgs.forEach(function(el) { el.style.display = 'none'; });
    } else if (mode === 'documents') {
        var containers = tempDiv.querySelectorAll('.history-comment-container');
        containers.forEach(function(container) {
            Array.from(container.childNodes).forEach(function(node) {
                if (node.nodeType === 3) {
                    node.textContent = '';
                } else if (node.nodeType === 1 && node.tagName.toLowerCase() === 'br') {
                    node.style.display = 'none';
                }
                if (node.nodeType === 1 && node.tagName.toLowerCase() === 'a' && !node.classList.contains('doc-link')) {
                    node.style.display = 'none';
                }
            });
        });
        
        var listItems = tempDiv.querySelectorAll('li');
        listItems.forEach(function(li) {
            var hasDocs = li.querySelector('.history-doc-file') !== null || li.querySelector('.history-doc-info') !== null;
            if (!hasDocs) {
                li.style.display = 'none';
            }
        });
    }
    
    var shortComments = tempDiv.querySelectorAll('[id$="-short"]');
    shortComments.forEach(function(el) { el.style.display = 'none'; });
    
    var fullComments = tempDiv.querySelectorAll('[id$="-full"]');
    fullComments.forEach(function(el) { el.style.display = 'block'; });

    var historyContent = tempDiv.innerHTML;
    
    printWindow.document.open();
    printWindow.document.write('<html><head><title>Print History</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: sans-serif; padding: 20px; color: #333; }');
    printWindow.document.write('button { display: none !important; }');
    printWindow.document.write('a { text-decoration: none; color: #0056b3; }');
    printWindow.document.write('li { margin-bottom: 15px; padding: 15px; border-left: 4px solid #17a2b8; list-style-type: none; border-bottom: 1px solid #eee; page-break-inside: auto; }');
    printWindow.document.write('img, iframe, pre { page-break-inside: avoid; }');
    printWindow.document.write('ul { padding-left: 0; }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(historyContent);
    printWindow.document.write('</body></html>');
    
    printWindow.document.close();
    printWindow.focus();
    
    function checkAndPrint() {
        if (fetchesCompleted >= fetchesCount) {
            setTimeout(function() {
                printWindow.print();
                printWindow.close();
            }, 800);
        }
    }
    
    // If there were no text files to fetch, just trigger print directly
    if (fetchesCount === 0) {
        checkAndPrint();
    }
}

function printMainDocuments() {
    var printWindow = window.open('', '_blank', 'width=800,height=600');
    
    var tempDiv = document.createElement('div');
    tempDiv.innerHTML = document.getElementById('dynamic-docs-container').innerHTML;
    
    var links = tempDiv.querySelectorAll('a');
    var fetchesCount = 0;
    var fetchesCompleted = 0;

    links.forEach(function(link) {
        var href = link.getAttribute('href');
        if (href && link.textContent.includes('View Uploaded')) {
            var lowerHref = href.toLowerCase();
            var container = link.parentNode.parentNode;
            var insertTarget = link.parentNode;

            if (lowerHref.endsWith('.jpg') || lowerHref.endsWith('.jpeg') || lowerHref.endsWith('.png') || lowerHref.endsWith('.gif') || lowerHref.endsWith('.webp')) {
                var img = document.createElement('img');
                img.src = href;
                img.style.maxWidth = '100%';
                img.style.maxHeight = '600px';
                img.style.display = 'block';
                img.style.marginTop = '10px';
                img.style.marginBottom = '10px';
                img.style.border = '1px solid #ccc';
                img.style.borderRadius = '4px';
                
                if (container) container.insertBefore(img, insertTarget.nextSibling);
            } else if (lowerHref.endsWith('.pdf')) {
                var iframe = document.createElement('iframe');
                iframe.src = href;
                iframe.style.width = '100%';
                iframe.style.height = '600px';
                iframe.style.border = '1px solid #ccc';
                iframe.style.marginTop = '10px';
                iframe.style.marginBottom = '10px';
                
                if (container) container.insertBefore(iframe, insertTarget.nextSibling);
            } else if (lowerHref.endsWith('.txt') || lowerHref.endsWith('.csv')) {
                var pre = document.createElement('pre');
                var preId = 'pre-' + Math.random().toString(36).substr(2, 9);
                pre.id = preId;
                pre.style.width = '100%';
                pre.style.maxHeight = '600px';
                pre.style.overflow = 'auto';
                pre.style.whiteSpace = 'pre-wrap';
                pre.style.wordWrap = 'break-word';
                pre.style.background = '#f8fafc';
                pre.style.border = '1px solid #cbd5e1';
                pre.style.padding = '10px';
                pre.style.marginTop = '10px';
                pre.style.marginBottom = '10px';
                pre.style.borderRadius = '4px';
                pre.style.fontSize = '13px';
                pre.textContent = 'Loading file contents...';
                
                if (container) container.insertBefore(pre, insertTarget.nextSibling);

                fetchesCount++;
                fetch(href)
                    .then(response => response.text())
                    .then(text => { 
                        var targetPre = printWindow.document.getElementById(preId);
                        if (targetPre) targetPre.textContent = text;
                    })
                    .catch(e => { 
                        var targetPre = printWindow.document.getElementById(preId);
                        if (targetPre) targetPre.textContent = 'Error loading file: ' + e; 
                    })
                    .finally(() => {
                        fetchesCompleted++;
                        checkAndPrintDocs();
                    });
            }
        }
    });

    var inputs = tempDiv.querySelectorAll('input[type="file"]');
    inputs.forEach(function(input) { input.style.display = 'none'; });
    
    var spans = tempDiv.querySelectorAll('span');
    spans.forEach(function(span) { span.style.display = 'none'; });
    
    var docGroups = tempDiv.querySelectorAll('.doc-group');
    docGroups.forEach(function(group) {
        if (!group.querySelector('a')) {
            group.style.display = 'none';
        } else {
            group.style.setProperty('display', 'block', 'important');
        }
    });
    
    var buttons = tempDiv.querySelectorAll('button');
    buttons.forEach(function(button) { button.style.display = 'none'; });

    var printContent = tempDiv.innerHTML;
    
    printWindow.document.open();
    printWindow.document.write('<html><head><title>Print Documents</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: sans-serif; padding: 20px; color: #333; }');
    printWindow.document.write('a { text-decoration: none; color: #0056b3; }');
    printWindow.document.write('.doc-group { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #ddd; page-break-inside: avoid; }');
    printWindow.document.write('img, iframe, pre { page-break-inside: avoid; }');
    printWindow.document.write('h4 { border-bottom: 2px solid #0056b3; padding-bottom: 10px; margin-bottom: 15px; color: #0056b3; }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(printContent);
    printWindow.document.write('</body></html>');
    
    printWindow.document.close();
    printWindow.focus();
    
    function checkAndPrintDocs() {
        if (fetchesCompleted >= fetchesCount) {
            setTimeout(function() {
                printWindow.print();
                printWindow.close();
            }, 800);
        }
    }
    
    if (fetchesCount === 0) {
        checkAndPrintDocs();
    }
}
</script>
</html>
