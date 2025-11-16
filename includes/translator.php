<?php
/**
 * Google Translate API Helper
 * Auto-translates content for languages missing translations
 */

class GoogleTranslator {
    private $api_key;
    private $conn;
    
    public function __construct($conn, $api_key = 'AIzaSyB3qd_SfztM7JWb7yLgUUnUVi1bmXfZl0U') {
        $this->conn = $conn;
        $this->api_key = $api_key;
    }
    
    /**
     * Translate text using Google Translate API
     */
    public function translate($text, $target_lang, $source_lang = 'en') {
        if (empty($text) || empty($target_lang) || $target_lang === $source_lang) {
            return $text;
        }
        
        $url = 'https://translation.googleapis.com/language/translate/v2';
        $data = [
            'q' => $text,
            'target' => $target_lang,
            'source' => $source_lang,
            'key' => $this->api_key,
            'format' => 'html'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $json = json_decode($response, true);
            if (isset($json['data']['translations'][0]['translatedText'])) {
                return $json['data']['translations'][0]['translatedText'];
            }
        }
        
        error_log("Translation failed for '$text' to $target_lang: HTTP $http_code");
        return $text; // Return original if translation fails
    }
    
    /**
     * Translate multiple texts at once
     */
    public function translateBatch($texts, $target_lang, $source_lang = 'en') {
        if (empty($texts) || empty($target_lang) || $target_lang === $source_lang) {
            return $texts;
        }
        
        $url = 'https://translation.googleapis.com/language/translate/v2';
        $data = [
            'q' => array_values($texts),
            'target' => $target_lang,
            'source' => $source_lang,
            'key' => $this->api_key,
            'format' => 'html'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $json = json_decode($response, true);
            if (isset($json['data']['translations'])) {
                $translated = [];
                $keys = array_keys($texts);
                foreach ($json['data']['translations'] as $i => $translation) {
                    $translated[$keys[$i]] = $translation['translatedText'];
                }
                return $translated;
            }
        }
        
        error_log("Batch translation failed to $target_lang: HTTP $http_code");
        return $texts; // Return originals if translation fails
    }
    
    /**
     * Auto-create missing language content by translating from English
     */
    public function autoTranslateHomeContent($language_id, $target_lang_code) {
        // Get English content (source)
        $en_res = $this->conn->query("SELECT * FROM languages WHERE code='en' LIMIT 1");
        if (!$en_res || $en_res->num_rows === 0) {
            error_log("English language not found for translation source");
            return false;
        }
        $en_lang = $en_res->fetch_assoc();
        $en_id = $en_lang['id'];
        
        $home_res = $this->conn->query("SELECT * FROM languages_home WHERE language_id={$en_id} LIMIT 1");
        if (!$home_res || $home_res->num_rows === 0) {
            error_log("English home content not found");
            return false;
        }
        $en_home = $home_res->fetch_assoc();
        
        // Fields to translate
        $fields = [
            'header', 'header2', 'description', 'title1', 'description1',
            'title2', 'description2', 'title3', 'description3',
            'button_text', 'placeholder_text'
        ];
        
        $to_translate = [];
        foreach ($fields as $field) {
            if (!empty($en_home[$field])) {
                $to_translate[$field] = $en_home[$field];
            }
        }
        
        error_log("Auto-translating home content to {$target_lang_code} for language_id {$language_id}");
        
        // Translate all fields
        $translated = $this->translateBatch($to_translate, $target_lang_code, 'en');
        
        // Build insert query
        $columns = ['language_id'];
        $values = [$language_id];
        
        foreach ($fields as $field) {
            if (isset($translated[$field])) {
                $columns[] = $field;
                $values[] = "'" . $this->conn->real_escape_string($translated[$field]) . "'";
            }
        }
        
        $insert_sql = "INSERT INTO languages_home (" . implode(', ', $columns) . ") 
                       VALUES (" . implode(', ', $values) . ")";
        
        if ($this->conn->query($insert_sql)) {
            error_log("Successfully created translated home content for {$target_lang_code}");
            return true;
        } else {
            error_log("Failed to insert translated content: " . $this->conn->error);
            return false;
        }
    }
}

/**
 * Get or create translated content for a language
 * This ensures content always exists when switching languages
 */
function ensure_language_content($conn, $language_id, $lang_code) {
    // Check if content exists
    $check = $conn->query("SELECT COUNT(*) as cnt FROM languages_home WHERE language_id={$language_id}");
    if ($check) {
        $row = $check->fetch_assoc();
        if ($row['cnt'] > 0) {
            return true; // Content exists
        }
    }
    
    // Content missing - auto-translate it
    error_log("Missing content for language {$lang_code} (ID: {$language_id}), auto-translating...");
    $translator = new GoogleTranslator($conn);
    return $translator->autoTranslateHomeContent($language_id, $lang_code);
}
?>

