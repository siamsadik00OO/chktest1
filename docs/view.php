<?php
/**
 * Simple Markdown file viewer
 */

// Security
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

// Get the file name from URL parameters
$file = $_GET['file'] ?? 'api_key_guide.md';

// Path sanitization - only allow viewing of .md files in the current directory
$file = basename($file);
if (!preg_match('/^[a-zA-Z0-9_-]+\.md$/', $file)) {
    die('Invalid file name');
}

// Check if file exists
$filepath = __DIR__ . '/' . $file;
if (!file_exists($filepath)) {
    die('File not found');
}

// Read the file
$content = file_get_contents($filepath);

// Basic markdown to HTML conversion
function parseMarkdown($text) {
    // Headers
    $text = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $text);
    
    // Bold and italic
    $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $text);
    
    // Lists
    $text = preg_replace('/^\- (.*?)$/m', '<li>$1</li>', $text);
    $text = preg_replace('/^\d+\. (.*?)$/m', '<li>$1</li>', $text);
    
    // Replace multiple list items with ul
    $text = preg_replace('/<li>(.*?)<\/li>(\s*<li>.*?<\/li>)+/s', '<ul>$0</ul>', $text);
    
    // Links
    $text = preg_replace('/\[(.*?)\]\((.*?)\)/s', '<a href="$2">$1</a>', $text);
    
    // Code blocks
    $text = preg_replace('/```(.*?)```/s', '<pre><code>$1</code></pre>', $text);
    $text = preg_replace('/`(.*?)`/s', '<code>$1</code>', $text);
    
    // Paragraphs
    $text = preg_replace('/^\s*$/m', '</p><p>', $text);
    
    // Line breaks
    $text = preg_replace('/\n/s', '<br>', $text);
    
    return '<p>' . $text . '</p>';
}

// Parse markdown to HTML
$html = parseMarkdown($content);

// Get the title from the first h1 tag
preg_match('/<h1>(.*?)<\/h1>/', $html, $matches);
$title = $matches[1] ?? 'Documentation';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        h2 {
            color: #3498db;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        h3 {
            color: #2980b9;
            margin-top: 25px;
            margin-bottom: 10px;
        }
        code {
            background: #f8f8f8;
            padding: 2px 5px;
            border-radius: 3px;
            color: #e74c3c;
        }
        pre {
            background: #f8f8f8;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        ul {
            padding-left: 20px;
        }
        li {
            margin-bottom: 5px;
        }
        a {
            color: #3498db;
        }
        a:hover {
            text-decoration: underline;
        }
        .back-link {
            margin-bottom: 20px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="../" class="back-link btn btn-sm btn-secondary">&laquo; Back to Application</a>
        <?php echo $html; ?>
    </div>
</body>
</html>