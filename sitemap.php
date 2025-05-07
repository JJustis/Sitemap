<?php
/**
 * Enhanced Directory Crawler with Grid Display (Non-Recursive)
 * 
 * This tool crawls through your site's top-level directory structure,
 * displays contents in a grid format with image previews, file details, and
 * description submission areas for each directory.
 */
// Turn off all error reporting
error_reporting(0);

// Turn off all but fatal errors
error_reporting(E_ERROR);
// Configuration
$rootDir = './'; // Set this to the root directory you want to crawl
$excludeDirs = array('.git', '.github', 'node_modules', '.vscode'); // Directories to exclude
$descriptionFile = '.directory-descriptions.json'; // File to store descriptions
$paginationLimit = 1000; // Maximum number of directories to load at once
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == 1; // Check if it's an AJAX request

// Create or load descriptions file
if (file_exists($descriptionFile)) {
    $descriptions = json_decode(file_get_contents($descriptionFile), true);
} else {
    $descriptions = array();
}

// Save description if form submitted
if (isset($_POST['save_description'])) {
    $path = $_POST['directory_path'];
    $desc = $_POST['description'];
    
    $descriptions[$path] = $desc;
    file_put_contents($descriptionFile, json_encode($descriptions, JSON_PRETTY_PRINT));
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/**
 * Check if a file is an image
 */
function isImage($filename) {
    $imageExtensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg');
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $imageExtensions);
}

/**
 * Get file type icon/preview
 */
function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = array(
        'pdf' => '📄 PDF',
        'doc' => '📝 DOC',
        'docx' => '📝 DOCX',
        'xls' => '📊 XLS',
        'xlsx' => '📊 XLSX',
        'ppt' => '📊 PPT',
        'pptx' => '📊 PPTX',
        'txt' => '📄 TXT',
        'zip' => '📦 ZIP',
        'rar' => '📦 RAR',
        'mp3' => '🎵 MP3',
        'mp4' => '🎬 MP4',
        'avi' => '🎬 AVI',
        'mov' => '🎬 MOV',
        'html' => '🌐 HTML',
        'php' => '💻 PHP',
        'js' => '💻 JS',
        'css' => '💻 CSS',
        'json' => '💻 JSON',
        'xml' => '💻 XML'
    );
    
    return isset($icons[$extension]) ? $icons[$extension] : '📄';
}

/**
 * Get only top-level directories in the specified path with pagination
 */
function getDirs($path, $offset = 0, $limit = 1000, &$totalDirs = 0) {
    global $excludeDirs;
    $dirs = array();
    $allDirs = array();
    
    if ($handle = opendir($path)) {
        // First, collect all directories to allow proper pagination
        while (false !== ($entry = readdir($handle))) {
            $fullPath = rtrim($path, '/') . '/' . $entry;
            
            if ($entry != "." && $entry != ".." && is_dir($fullPath) && !in_array($entry, $excludeDirs)) {
                $allDirs[] = $entry;
            }
        }
        closedir($handle);
        
        // Sort directories alphabetically
        sort($allDirs);
        
        // Set total count
        $totalDirs = count($allDirs);
        
        // Apply pagination
        $paginatedDirs = array_slice($allDirs, $offset, $limit);
        
        // Process the paginated subset
        foreach ($paginatedDirs as $entry) {
            $fullPath = rtrim($path, '/') . '/' . $entry;
            $hasIndex = file_exists($fullPath . '/index.html') || file_exists($fullPath . '/index.php');
            
            // Get files for preview
            $files = getFiles($fullPath);
            
            // Get preview images
            $previewImages = array();
            foreach ($files as $file) {
                if (isImage($file['name'])) {
                    $previewImages[] = $file['name'];
                    if (count($previewImages) >= 4) break; // Limit to 4 preview images
                }
            }
            
            $dirs[] = array(
                'name' => $entry,
                'path' => $fullPath,
                'hasIndex' => $hasIndex,
                'previewImages' => $previewImages,
                'files' => $files
            );
        }
    }
    
    return $dirs;
}

/**
 * Get all files in the specified directory with additional metadata
 */
function getFiles($dirPath) {
    $files = array();
    
    if ($handle = opendir($dirPath)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != ".." && !is_dir($dirPath . '/' . $entry)) {
                $fullPath = rtrim($dirPath, '/') . '/' . $entry;
                $isImg = isImage($entry);
                $icon = !$isImg ? getFileIcon($entry) : null;
                $size = filesize($fullPath);
                
                $files[] = array(
                    'name' => $entry,
                    'path' => $fullPath,
                    'isImage' => $isImg,
                    'icon' => $icon,
                    'size' => $size,
                    'modified' => filemtime($fullPath)
                );
            }
        }
        closedir($handle);
    }
    
    return $files;
}

// Get all directories in root
$allDirs = getDirs($rootDir);

// Get all top-level directories with pagination
$totalDirs = 0;
$offset = ($page - 1) * $paginationLimit;
$allDirs = getDirs($rootDir, $offset, $paginationLimit, $totalDirs);

// Set the flag to check if there are more directories to load
$hasMoreDirs = $totalDirs > ($page * $paginationLimit);

// Find the specific directory when viewing contents
if (!empty($showContents)) {
    $currentDir = null;
    
    // Check if the requested directory exists in our list
    foreach ($allDirs as $dir) {
        if ($dir['path'] == $showContents) {
            $currentDir = $dir;
            break;
        }
    }
    
    // If not found in the current page, we need to search for it directly
    if ($currentDir === null) {
        // Get the directory name from the path
        $dirName = basename($showContents);
        $parentPath = dirname($showContents);
        
        // Check if it's a valid directory
        if (is_dir($showContents) && !in_array($dirName, $excludeDirs)) {
            $hasIndex = file_exists($showContents . '/index.html') || file_exists($showContents . '/index.php');
            
            // Get files for preview
            $files = getFiles($showContents);
            
            // Get preview images
            $previewImages = array();
            foreach ($files as $file) {
                if (isImage($file['name'])) {
                    $previewImages[] = $file['name'];
                    if (count($previewImages) >= 4) break; // Limit to 4 preview images
                }
            }
            
            $currentDir = array(
                'name' => $dirName,
                'path' => $showContents,
                'hasIndex' => $hasIndex,
                'previewImages' => $previewImages,
                'files' => $files
            );
        }
    }
}

// Get directory to display contents for
$showContents = isset($_GET['dir']) ? $_GET['dir'] : '';

// Handle AJAX requests
if ($isAjax) {
    // Prepare the response data
    $response = array(
        'hasMore' => $hasMoreDirs,
        'projectDirs' => '',
        'otherDirs' => ''
    );
    
    // Get project directories HTML
    ob_start();
    foreach ($allDirs as $dir) {
        if ($dir['hasIndex']) {
            ?>
            <div class="directory-card">
                <div class="card-header">
                    <?php echo $dir['name']; ?>
                </div>
                <div class="card-body">
                    <a href="<?php echo $dir['path']; ?>" target="_blank">View Project</a> | 
                    <a href="<?php echo $_SERVER['PHP_SELF'] . '?dir=' . urlencode($dir['path']); ?>">Contents</a>
                    
                    <?php if (!empty($dir['previewImages'])): ?>
                        <div class="preview-images">
                            <?php 
                            $count = 0;
                            $total = count($dir['previewImages']);
                            foreach ($dir['previewImages'] as $idx => $image): 
                                $count++;
                                if ($count <= 4): // Show at most 4 images
                                    $imgPath = $dir['path'] . '/' . $image;
                                    if ($count == 4 && $total > 4): // If there are more images, show count on the last preview
                            ?>
                                    <div class="preview-container">
                                        <img src="<?php echo $imgPath; ?>" alt="<?php echo $image; ?>" class="preview-image">
                                        <div class="preview-overlay">+<?php echo $total - 3; ?> more</div>
                                    </div>
                            <?php else: ?>
                                    <img src="<?php echo $imgPath; ?>" alt="<?php echo $image; ?>" class="preview-image">
                            <?php 
                                    endif;
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($descriptions[$dir['path']])): ?>
                        <div class="description">
                            <?php echo htmlspecialchars($descriptions[$dir['path']]); ?>
                        </div>
                    <?php else: ?>
                        <div class="description">
                            <em>No description available</em>
                        </div>
                    <?php endif; ?>
                    
                    <form class="description-form" method="post" action="">
                        <input type="hidden" name="directory_path" value="<?php echo $dir['path']; ?>">
                        <textarea name="description" placeholder="Add a description..."><?php echo isset($descriptions[$dir['path']]) ? htmlspecialchars($descriptions[$dir['path']]) : ''; ?></textarea>
                        <button type="submit" name="save_description">Save Description</button>
                    </form>
                </div>
            </div>
            <?php
        }
    }
    $response['projectDirs'] = ob_get_clean();
    
    // Get other directories HTML
    ob_start();
    foreach ($allDirs as $dir) {
        if (!$dir['hasIndex']) {
            ?>
            <tr>
                <td><?php echo $dir['name']; ?></td>
                <td>
                    <?php if (isset($descriptions[$dir['path']])): ?>
                        <?php echo htmlspecialchars($descriptions[$dir['path']]); ?>
                    <?php else: ?>
                        <em>No description</em>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?php echo $_SERVER['PHP_SELF'] . '?dir=' . urlencode($dir['path']); ?>">View Contents</a>
                    <button onclick="toggleForm('form-<?php echo md5($dir['path']); ?>')">Edit Description</button>
                </td>
            </tr>
            <tr id="form-<?php echo md5($dir['path']); ?>" style="display: none;">
                <td colspan="3">
                    <form class="description-form" method="post" action="">
                        <input type="hidden" name="directory_path" value="<?php echo $dir['path']; ?>">
                        <textarea name="description" placeholder="Add a description..."><?php echo isset($descriptions[$dir['path']]) ? htmlspecialchars($descriptions[$dir['path']]) : ''; ?></textarea>
                        <button type="submit" name="save_description">Save Description</button>
                    </form>
                </td>
            </tr>
            <?php
        }
    }
    $response['otherDirs'] = ob_get_clean();
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directory Crawler</title>
    <style>
        :root {
            --primary: #4a6fa5;
            --secondary: #166088;
            --light: #dbe9ee;
            --dark: #333;
            --success: #28a745;
            --warning: #ffc107;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--dark);
            margin: 0;
            padding: 20px;
            background-color: #f5f7fa;
        }
        
        h1, h2, h3 {
            color: var(--secondary);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .directory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .directory-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .directory-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .preview-images {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 5px;
            margin-top: 10px;
        }
        
        .preview-image {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .preview-container {
            position: relative;
            overflow: hidden;
            border-radius: 4px;
            aspect-ratio: 1/1;
        }
        
        .preview-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .card-header {
            background-color: var(--primary);
            color: white;
            padding: 15px;
            font-weight: bold;
        }
        
        .card-body {
            padding: 15px;
        }
        
        .description {
            margin-top: 10px;
            font-style: italic;
            color: #666;
            min-height: 60px;
        }
        
        .description-form {
            margin-top: 15px;
        }
        
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 80px;
        }
        
        button {
            background-color: var(--secondary);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
            transition: background-color 0.3s ease;
        }
        
        button:hover {
            background-color: var(--primary);
        }
        
        .directory-list {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 40px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: var(--light);
            font-weight: bold;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        .contents-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .contents-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .file-item {
            background-color: var(--light);
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            color: var(--dark);
            display: flex;
            align-items: center;
        }
        
        .file-item:hover {
            background-color: #c9d8e0;
        }
        
        .dir-item {
            background-color: #e3f2fd;
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            color: var(--dark);
            display: flex;
            align-items: center;
        }
        
        .dir-item:hover {
            background-color: #bbdefb;
        }
        
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .file-card {
            border-radius: 8px;
            overflow: hidden;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        .file-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .file-preview {
            height: 120px;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .file-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .file-preview .icon {
            font-size: 36px;
            color: var(--secondary);
        }
        
        .file-info {
            padding: 10px;
        }
        
        .file-name {
            font-size: 14px;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .file-meta {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .icon {
            margin-right: 8px;
            font-size: 18px;
        }
        
        .breadcrumbs {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .breadcrumb-item {
            margin-right: 8px;
        }
        
        .breadcrumb-item:not(:last-child)::after {
            content: '>';
            margin-left: 8px;
            color: #999;
        }
        
        a {
            color: var(--secondary);
            text-decoration: none;
        }
        
        .load-more-container {
            text-align: center;
            margin: 30px 0;
        }
        
        .load-more-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .load-more-btn:hover {
            background-color: var(--secondary);
        }
        
        .load-more-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Directory Crawler</h1>
            <p>Browse your site's structure and add descriptions to directories.</p>
        </div>
        
        <?php if (!empty($showContents)): ?>
            <?php
                // Find the directory to show contents for
                $currentDir = null;
                foreach ($allDirs as $dir) {
                    if ($dir['path'] == $showContents) {
                        $currentDir = $dir;
                        break;
                    }
                    
                    foreach ($dir['subdirs'] as $subdir) {
                        if ($subdir['path'] == $showContents) {
                            $currentDir = $subdir;
                            break 2;
                        }
                    }
                }
                
                if ($currentDir !== null):
            ?>
                <div class="breadcrumbs">
                    <div class="breadcrumb-item">
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>">Home</a>
                    </div>
                    <div class="breadcrumb-item"><?php echo $currentDir['name']; ?></div>
                </div>
                
                <div class="contents-section">
                    <h2>Contents of <?php echo $currentDir['name']; ?></h2>
                    
                    <?php if (isset($descriptions[$currentDir['path']])): ?>
                        <div class="description">
                            <?php echo htmlspecialchars($descriptions[$currentDir['path']]); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form class="description-form" method="post" action="">
                        <input type="hidden" name="directory_path" value="<?php echo $currentDir['path']; ?>">
                        <textarea name="description" placeholder="Add a description..."><?php echo isset($descriptions[$currentDir['path']]) ? htmlspecialchars($descriptions[$currentDir['path']]) : ''; ?></textarea>
                        <button type="submit" name="save_description">Save Description</button>
                    </form>
                    
                    <!-- Files with previews -->
                    <?php if (!empty($currentDir['files'])): ?>
                        <h3>Files</h3>
                        <div class="file-grid">
                            <?php foreach ($currentDir['files'] as $file): ?>
                                <div class="file-card">
                                    <div class="file-preview">
                                        <?php if ($file['isImage']): ?>
                                            <img src="<?php echo $file['path']; ?>" alt="<?php echo $file['name']; ?>">
                                        <?php else: ?>
                                            <div class="icon"><?php echo $file['icon']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="file-info">
                                        <div class="file-name">
                                            <a href="<?php echo $file['path']; ?>" target="_blank"><?php echo $file['name']; ?></a>
                                        </div>
                                        <div class="file-meta">
                                            <?php echo number_format($file['size'] / 1024, 2); ?> KB
                                            <br>
                                            <?php echo date('Y-m-d H:i', $file['modified']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Directories with index files (grid view) -->
            <h2>Projects</h2>
            <div class="directory-grid">
                <?php foreach ($allDirs as $dir): ?>
                    <?php if ($dir['hasIndex']): ?>
                        <div class="directory-card">
                            <div class="card-header">
                                <?php echo $dir['name']; ?>
                            </div>
                            <div class="card-body">
                                <a href="<?php echo $dir['path']; ?>" target="_blank">View Project</a> | 
                                <a href="<?php echo $_SERVER['PHP_SELF'] . '?dir=' . urlencode($dir['path']); ?>">Contents</a>
                                
                                <?php if (!empty($dir['previewImages'])): ?>
                                    <div class="preview-images">
                                        <?php 
                                        $count = 0;
                                        $total = count($dir['previewImages']);
                                        foreach ($dir['previewImages'] as $idx => $image): 
                                            $count++;
                                            if ($count <= 4): // Show at most 4 images
                                                $imgPath = $dir['path'] . '/' . $image;
                                                if ($count == 4 && $total > 4): // If there are more images, show count on the last preview
                                        ?>
                                                <div class="preview-container">
                                                    <img src="<?php echo $imgPath; ?>" alt="<?php echo $image; ?>" class="preview-image">
                                                    <div class="preview-overlay">+<?php echo $total - 3; ?> more</div>
                                                </div>
                                        <?php else: ?>
                                                <img src="<?php echo $imgPath; ?>" alt="<?php echo $image; ?>" class="preview-image">
                                        <?php 
                                                endif;
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($descriptions[$dir['path']])): ?>
                                    <div class="description">
                                        <?php echo htmlspecialchars($descriptions[$dir['path']]); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="description">
                                        <em>No description available</em>
                                    </div>
                                <?php endif; ?>
                                
                                <form class="description-form" method="post" action="">
                                    <input type="hidden" name="directory_path" value="<?php echo $dir['path']; ?>">
                                    <textarea name="description" placeholder="Add a description..."><?php echo isset($descriptions[$dir['path']]) ? htmlspecialchars($descriptions[$dir['path']]) : ''; ?></textarea>
                                    <button type="submit" name="save_description">Save Description</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Directories without index files (list view) -->
            <h2>Other Directories</h2>
            <div class="directory-list">
                <table>
                    <thead>
                        <tr>
                            <th>Directory</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allDirs as $dir): ?>
                            <?php if (!$dir['hasIndex']): ?>
                                <tr>
                                    <td><?php echo $dir['name']; ?></td>
                                    <td>
                                        <?php if (isset($descriptions[$dir['path']])): ?>
                                            <?php echo htmlspecialchars($descriptions[$dir['path']]); ?>
                                        <?php else: ?>
                                            <em>No description</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo $_SERVER['PHP_SELF'] . '?dir=' . urlencode($dir['path']); ?>">View Contents</a>
                                        <button onclick="toggleForm('form-<?php echo md5($dir['path']); ?>')">Edit Description</button>
                                    </td>
                                </tr>
                                <tr id="form-<?php echo md5($dir['path']); ?>" style="display: none;">
                                    <td colspan="3">
                                        <form class="description-form" method="post" action="">
                                            <input type="hidden" name="directory_path" value="<?php echo $dir['path']; ?>">
                                            <textarea name="description" placeholder="Add a description..."><?php echo isset($descriptions[$dir['path']]) ? htmlspecialchars($descriptions[$dir['path']]) : ''; ?></textarea>
                                            <button type="submit" name="save_description">Save Description</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($hasMoreDirs): ?>
                <div class="load-more-container">
                    <button id="load-more-btn" class="load-more-btn" data-page="<?php echo $page + 1; ?>">
                        Load More Directories
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        function toggleForm(formId) {
            const form = document.getElementById(formId);
            if (form.style.display === 'none') {
                form.style.display = 'table-row';
            } else {
                form.style.display = 'none';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const loadMoreBtn = document.getElementById('load-more-btn');
            if (loadMoreBtn) {
                loadMoreBtn.addEventListener('click', function() {
                    const nextPage = this.getAttribute('data-page');
                    loadMoreDirectories(nextPage);
                });
            }
        });
        
        function loadMoreDirectories(page) {
            const gridContainer = document.querySelector('.directory-grid');
            const listContainer = document.querySelector('.directory-list tbody');
            const loadMoreBtn = document.getElementById('load-more-btn');
            
            // Show loading state
            loadMoreBtn.textContent = 'Loading...';
            loadMoreBtn.disabled = true;
            
            // Create AJAX request
            const xhr = new XMLHttpRequest();
            xhr.open('GET', '<?php echo $_SERVER['PHP_SELF']; ?>?page=' + page + '&ajax=1', true);
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    
                    // Add new directories to the grid
                    if (response.projectDirs) {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = response.projectDirs;
                        while (tempDiv.firstChild) {
                            gridContainer.appendChild(tempDiv.firstChild);
                        }
                    }
                    
                    // Add new directories to the list
                    if (response.otherDirs) {
                        const tempTable = document.createElement('table');
                        tempTable.innerHTML = '<tbody>' + response.otherDirs + '</tbody>';
                        const newRows = tempTable.querySelector('tbody').children;
                        
                        // Convert HTMLCollection to Array and append each row
                        Array.from(newRows).forEach(row => {
                            listContainer.appendChild(row);
                        });
                    }
                    
                    // Update the load more button
                    if (response.hasMore) {
                        loadMoreBtn.textContent = 'Load More Directories';
                        loadMoreBtn.disabled = false;
                        loadMoreBtn.setAttribute('data-page', parseInt(page) + 1);
                    } else {
                        // Remove button if no more results
                        loadMoreBtn.parentNode.removeChild(loadMoreBtn);
                    }
                }
            };
            
            xhr.send();
        }
    </script>
</body>
</html>