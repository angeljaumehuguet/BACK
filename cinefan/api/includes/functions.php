<?php
class Utils {
    
    public static function generateSlug($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s_-]+/', '-', $text);
        return trim($text, '-');
    }
    
    public static function formatDuration($minutes) {
        if ($minutes <= 0) {
            return 'N/A';
        }
        
        $hours = intval($minutes / 60);
        $mins = $minutes % 60;
        
        if ($hours > 0) {
            if ($mins > 0) {
                return "{$hours}h {$mins}min";
            } else {
                return "{$hours}h";
            }
        } else {
            return "{$mins}min";
        }
    }
    
    public static function timeAgo($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'hace unos segundos';
        if ($time < 3600) return 'hace ' . floor($time/60) . ' minutos';
        if ($time < 86400) return 'hace ' . floor($time/3600) . ' horas';
        if ($time < 2592000) return 'hace ' . floor($time/86400) . ' días';
        if ($time < 31536000) return 'hace ' . floor($time/2592000) . ' meses';
        
        return 'hace ' . floor($time/31536000) . ' años';
    }
    
    public static function validateMovieYear($year) {
        $currentYear = date('Y');
        return $year >= 1895 && $year <= ($currentYear + 5);
    }
    
    public static function validateRating($rating) {
        return is_numeric($rating) && $rating >= 1 && $rating <= 5;
    }
    
    public static function getClientInfo() {
        return [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    public static function log($message, $level = 'INFO') {
        if (!ENABLE_LOGGING) return;
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        error_log($logMessage, 3, __DIR__ . '/../logs/cinefan.log');
    }
}