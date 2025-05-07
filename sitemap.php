<?php
/**
 * Directory Crawler with Grid Display
 * 
 * This tool crawls through your site's directory structure and displays
 * contents in a grid format with description submission areas.
 */

// Configuration
$rootDir = './'; // Set this to the root directory you want to crawl
$excludeDirs = array('.git', '.github', 'node_modules', '.vscode'); // Directories to exclude
$descriptionFile = '.directory-descriptions.json'; // File to store descriptions

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
 * Get all directories in the specified path
 */
function getDirs($path) {
    global $excludeDirs;
    $dirs = array();
    
    if ($handle = opendir($path)) {
        while (false !== ($entry = readdir($handle))) {
            $fullPath = $path . '/' . $entry;
            
            if ($entry != "." && $entry != ".." && is_dir($fullPath) && !in_array($entry, $excludeDirs)) {
                $hasIndex = file_exists($fullPath . '/index.html') || file_exists($fullPath . '/index.php');
                
                $dirs[] = array(
                    'name' => $entry,
                    'path' => $fullPath,
                    'hasIndex' => $hasIndex,
                    'contents' => array()
                );
            }
        }
        closedir($handle);
    }
    
    return $dirs;
}

/**
 * Get all files in the specified directory
 */
function getFiles($dirPath) {
    $files = array();
    
    if ($handle = opendir($dirPath)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != ".." && !is_dir($dirPath . '/' . $entry)) {
                $files[] = $entry;
            }
        }
        closedir($handle);
    }
    
    return $files;
}

// Get all directories in root
$allDirs = getDirs($rootDir);

// Process directories recursively
foreach ($allDirs as &$dir) {
    // Get contents (files and subdirectories)
    $dir['files'] = getFiles($dir['path']);
    $dir['subdirs'] = getDirs($dir['path']);
}

// Get directory to display contents for
$showContents = isset($_GET['dir']) ? $_GET['dir'] : '';

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
        
        a:hover {
            text-decoration: underline;
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
                    
                    <div class="contents-list">
                        <?php foreach ($currentDir['files'] as $file): ?>
                            <a href="<?php echo $currentDir['path'] . '/' . $file; ?>" class="file-item">
                                <span class="icon">📄</span> <?php echo $file; ?>
                            </a>
                        <?php endforeach; ?>
                        
                        <?php foreach ($currentDir['subdirs'] as $subdir): ?>
                            <a href="<?php echo $_SERVER['PHP_SELF'] . '?dir=' . urlencode($subdir['path']); ?>" class="dir-item">
                                <span class="icon">📁</span> <?php echo $subdir['name']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
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
    </script>
</body>
</html>
