<?php

$files = [
    'resources/views/home.blade.php',
    'resources/views/account.blade.php',
    'resources/views/orders.blade.php',
    'resources/views/schedule.blade.php',
    'resources/views/shop.blade.php',
    'resources/views/checkout.blade.php',
    'resources/views/estimator.blade.php',
];

foreach ($files as $file) {
    if (! file_exists($file)) {
        continue;
    }

    $content = file_get_contents($file);

    // 1. Extract <style> block inside <head> if it exists
    $styles = '';
    if (preg_match('/<style>(.*?)<\/style>/s', $content, $matches)) {
        $styles = $matches[1];
        // Remove common styles that are already in customer.blade.php
        $styles = preg_replace('/\* \{ font-family: \'Figtree\', sans-serif; \}/', '', $styles);
        $styles = preg_replace('/\.font-display \{ font-family: \'Cormorant Garamond\', serif; \}/', '', $styles);
        $styles = preg_replace('/::\-webkit\-scrollbar.*?border\-radius: \dpx; \}/s', '', $styles);
    }

    // 2. We want to drop everything from the beginning of the file up to the end of <nav>
    $mainContent = '';
    // Let's use string operations instead of regex for safety on the nav block.
    $navStart = strpos($content, '<nav');
    $navEnd = strpos($content, '</nav>');

    if ($navStart !== false && $navEnd !== false) {
        $contentAfterNav = substr($content, $navEnd + 6);
    } else {
        // If no <nav>, just find <body>
        $bodyStart = strpos($content, '<body');
        if ($bodyStart !== false) {
            $bodyEnd = strpos($content, '>', $bodyStart);
            $contentAfterNav = substr($content, $bodyEnd + 1);
        } else {
            $contentAfterNav = $content;
        }
    }

    // 3. Remove logout form and functions at the bottom and </body></html>
    // These start with <form id="logout-form"
    $logoutStart = strpos($contentAfterNav, '<form id="logout-form"');
    if ($logoutStart !== false) {
        $contentAfterNav = substr($contentAfterNav, 0, $logoutStart);
    }

    // Also strip trailing </body> and </html> and any remaining <script> handleLogout()
    $contentAfterNav = preg_replace('/<script>\s*window\.addEventListener\(\'pageshow\'.*?<\/script>/s', '', $contentAfterNav);
    $contentAfterNav = preg_replace('/<script>\s*function handleLogout\(\).*?<\/script>/s', '', $contentAfterNav);
    $contentAfterNav = str_replace(['</body>', '</html>'], '', $contentAfterNav);

    // Some sections like 'Hero section' in home.blade.php have a "pt-16" class because of the old fixed top nav.
    // In a sidebar layout, we don't need pt-16 for the top navbar.
    $contentAfterNav = str_replace('pt-16 h-screen', 'h-screen', $contentAfterNav);
    $contentAfterNav = str_replace('pt-24', 'pt-8', $contentAfterNav); // common for other pages
    $contentAfterNav = str_replace('pt-20', 'pt-8', $contentAfterNav);

    // Build new file content
    $newContent = "@extends('layouts.customer')\n\n";
    if (trim($styles) !== '') {
        $newContent .= "@section('styles')\n<style>\n".trim($styles)."\n</style>\n@endsection\n\n";
    }

    // Check if the file already had @section('content') somewhere, wait, previously they were standalone HTML files.
    // Wait, let's just wrap everything in @section('content')
    $newContent .= "@section('content')\n";
    $newContent .= trim($contentAfterNav)."\n";
    $newContent .= "@endsection\n";

    file_put_contents($file, $newContent);
    echo "Processed $file\n";
}
