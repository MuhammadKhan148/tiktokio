<?php
/**
 * Helper functions for slug redirects display
 */

if (!function_exists('get_slug_redirects_info')) {
    /**
     * Get information about slug redirects for the current page
     * Returns array with current_slug and old_slugs that redirect to it
     * 
     * @param mysqli $conn Database connection
     * @param string $current_slug Current active slug
     * @param int $lang_id Language ID
     * @return array ['current_slug' => string, 'old_slugs' => array, 'page_type' => string]
     */
    function get_slug_redirects_info($conn, $current_slug, $lang_id) {
        $result = [
            'current_slug' => $current_slug,
            'old_slugs' => [],
            'page_type' => 'home'
        ];
        
        if (empty($current_slug)) {
            // Homepage - check languages_home
            $stmt = $conn->prepare("SELECT slug FROM languages_home WHERE language_id=? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $lang_id);
                $stmt->execute();
                $stmt->bind_result($home_slug);
                if ($stmt->fetch()) {
                    $result['current_slug'] = $home_slug;
                    // Get old slugs from languages_home_redirects
                    $stmt2 = $conn->prepare("SELECT old_slug FROM languages_home_redirects WHERE language_id=? ORDER BY old_slug");
                    if ($stmt2) {
                        $stmt2->bind_param('i', $lang_id);
                        $stmt2->execute();
                        $stmt2->bind_result($old_slug);
                        while ($stmt2->fetch()) {
                            $result['old_slugs'][] = $old_slug;
                        }
                        $stmt2->close();
                    }
                }
                $stmt->close();
            }
            return $result;
        }
        
        // Check MP3 pages
        $stmt = $conn->prepare("SELECT mp.id FROM mp3_pages mp 
                                JOIN mp3_page_slugs mps ON mp.id = mps.mp3_page_id 
                                WHERE mp.language_id=? AND mps.slug=? AND mps.status='active' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('is', $lang_id, $current_slug);
            $stmt->execute();
            $stmt->bind_result($page_id);
            if ($stmt->fetch()) {
                $stmt->close();
                $result['page_type'] = 'mp3';
                // Get all inactive slugs for this page
                $stmt2 = $conn->prepare("SELECT slug FROM mp3_page_slugs WHERE mp3_page_id=? AND status='inactive' ORDER BY slug");
                if ($stmt2) {
                    $stmt2->bind_param('i', $page_id);
                    $stmt2->execute();
                    $stmt2->bind_result($old_slug);
                    while ($stmt2->fetch()) {
                        $result['old_slugs'][] = $old_slug;
                    }
                    $stmt2->close();
                }
                return $result;
            }
            $stmt->close();
        }
        
        // Check MP4 pages (stories)
        $stmt = $conn->prepare("SELECT sp.id FROM stories_pages sp 
                                JOIN stories_page_slugs sps ON sp.id = sps.stories_page_id 
                                WHERE sp.language_id=? AND sps.slug=? AND sps.status='active' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('is', $lang_id, $current_slug);
            $stmt->execute();
            $stmt->bind_result($page_id);
            if ($stmt->fetch()) {
                $stmt->close();
                $result['page_type'] = 'stories';
                // Get all inactive slugs for this page
                $stmt2 = $conn->prepare("SELECT slug FROM stories_page_slugs WHERE stories_page_id=? AND status='inactive' ORDER BY slug");
                if ($stmt2) {
                    $stmt2->bind_param('i', $page_id);
                    $stmt2->execute();
                    $stmt2->bind_result($old_slug);
                    while ($stmt2->fetch()) {
                        $result['old_slugs'][] = $old_slug;
                    }
                    $stmt2->close();
                }
                return $result;
            }
            $stmt->close();
        }
        
        // Check HOW pages
        $stmt = $conn->prepare("SELECT hp.id FROM how_pages hp 
                                JOIN how_page_slugs hps ON hp.id = hps.how_page_id 
                                WHERE hp.language_id=? AND hps.slug=? AND hps.status='active' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('is', $lang_id, $current_slug);
            $stmt->execute();
            $stmt->bind_result($page_id);
            if ($stmt->fetch()) {
                $stmt->close();
                $result['page_type'] = 'how';
                // Get all inactive slugs for this page
                $stmt2 = $conn->prepare("SELECT slug FROM how_page_slugs WHERE how_page_id=? AND status='inactive' ORDER BY slug");
                if ($stmt2) {
                    $stmt2->bind_param('i', $page_id);
                    $stmt2->execute();
                    $stmt2->bind_result($old_slug);
                    while ($stmt2->fetch()) {
                        $result['old_slugs'][] = $old_slug;
                    }
                    $stmt2->close();
                }
                return $result;
            }
            $stmt->close();
        }
        
        // Check custom pages
        $stmt = $conn->prepare("SELECT cp.id FROM custom_pages cp 
                                JOIN custom_page_slugs cps ON cp.id = cps.custom_page_id 
                                WHERE cp.language_id=? AND cps.slug=? AND cps.status='active' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('is', $lang_id, $current_slug);
            $stmt->execute();
            $stmt->bind_result($page_id);
            if ($stmt->fetch()) {
                $stmt->close();
                $result['page_type'] = 'custom';
                // Get all inactive slugs for this page
                $stmt2 = $conn->prepare("SELECT slug FROM custom_page_slugs WHERE custom_page_id=? AND status='inactive' ORDER BY slug");
                if ($stmt2) {
                    $stmt2->bind_param('i', $page_id);
                    $stmt2->execute();
                    $stmt2->bind_result($old_slug);
                    while ($stmt2->fetch()) {
                        $result['old_slugs'][] = $old_slug;
                    }
                    $stmt2->close();
                }
                return $result;
            }
            $stmt->close();
        }
        
        return $result;
    }
}

if (!function_exists('display_slug_redirects')) {
    /**
     * Display slug redirects information at the bottom of the page
     * 
     * @param mysqli $conn Database connection
     * @param string $current_slug Current active slug
     * @param int $lang_id Language ID
     */
    function display_slug_redirects($conn, $current_slug, $lang_id) {
        $slug_info = get_slug_redirects_info($conn, $current_slug, $lang_id);
        
        // Only show if there are old slugs that redirect
        if (empty($slug_info['old_slugs'])) {
            return;
        }
        
        $current = $slug_info['current_slug'] ?: '/';
        $old_slugs = $slug_info['old_slugs'];
        
        echo '<div style="margin-top: 20px; padding: 10px; background: #f5f5f5; border-top: 1px solid #ddd; font-size: 11px; color: #666; text-align: center;">';
        echo '<strong>Slug Redirects:</strong> ';
        echo '<span style="color: #333;">Current: <code>/' . htmlspecialchars($current) . '</code></span>';
        if (!empty($old_slugs)) {
            echo ' | Old slugs redirecting here: ';
            $old_links = [];
            foreach ($old_slugs as $old) {
                $old_links[] = '<code>/' . htmlspecialchars($old) . '</code>';
            }
            echo implode(', ', $old_links);
        }
        echo '</div>';
    }
}

