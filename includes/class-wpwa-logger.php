<?php
/**
 * Logger Class
 *
 * Handles structured logging for debugging and monitoring purposes.
 *
 * @package WP_WhatsApp_API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger Class
 */
class WPWA_Logger {
    /**
     * Log file path
     *
     * @var string
     */
    private $log_file;
    
    /**
     * Debug mode enabled
     *
     * @var boolean
     */
    private $debug_mode;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Set up log file path
        $upload_dir = wp_upload_dir();
        $wpwa_dir = $upload_dir['basedir'] . '/wpwa/logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($wpwa_dir)) {
            wp_mkdir_p($wpwa_dir);
            
            // Create htaccess file to protect logs
            file_put_contents($wpwa_dir . '/.htaccess', 'deny from all');
            
            // Create index.php to prevent directory listing
            file_put_contents($wpwa_dir . '/index.php', '<?php // Silence is golden');
        }
        
        // Generate log filename based on date
        $date_suffix = date('Y-m-d');
        $this->log_file = $wpwa_dir . '/wpwa-' . $date_suffix . '.log';
        
        // Check debug mode
        $this->debug_mode = get_option('wpwa_debug_mode', '0') === '1';
        
        // Add action to rotate logs
        add_action('wpwa_rotate_logs', array($this, 'rotate_logs'));
        
        // Schedule log rotation if not already scheduled
        if (!wp_next_scheduled('wpwa_rotate_logs')) {
            wp_schedule_event(time(), 'daily', 'wpwa_rotate_logs');
        }
    }
    
    /**
     * Log an info message
     *
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    public function info($message, $context = array()) {
        $this->log('INFO', $message, $context);
    }
    
    /**
     * Log a warning message
     *
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    public function warning($message, $context = array()) {
        $this->log('WARNING', $message, $context);
    }
    
    /**
     * Log an error message
     *
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    public function error($message, $context = array()) {
        $this->log('ERROR', $message, $context);
    }
    
    /**
     * Log a debug message
     * Only logged when debug mode is enabled
     *
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    public function debug($message, $context = array()) {
        if ($this->debug_mode) {
            $this->log('DEBUG', $message, $context);
        }
    }
    
    /**
     * Log a message
     *
     * @param string $level   Log level
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    public function log($level, $message, $context = array()) {
        // Format timestamp
        $timestamp = date('Y-m-d H:i:s');
        
        // Format context data as JSON
        $context_json = !empty($context) ? ' ' . wp_json_encode($context) : '';
        
        // Format log entry
        $log_entry = sprintf(
            "[%s] [%s] %s%s\n",
            $timestamp,
            $level,
            $message,
            $context_json
        );
        
        // Write to file
        error_log($log_entry, 3, $this->log_file);
        
        // Also log to PHP error log if in debug mode
        if ($this->debug_mode) {
            error_log('WPWA: ' . trim($log_entry));
        }
        
        // Hook for external logging systems
        do_action('wpwa_log_message', $level, $message, $context, $timestamp);
    }
    
    /**
     * Rotate logs to keep disk usage under control
     * Deletes logs older than 14 days
     */
    public function rotate_logs() {
        $upload_dir = wp_upload_dir();
        $wpwa_dir = $upload_dir['basedir'] . '/wpwa/logs';
        
        if (!is_dir($wpwa_dir)) {
            return;
        }
        
        // Get all log files
        $log_files = glob($wpwa_dir . '/wpwa-*.log');
        
        if (!is_array($log_files)) {
            return;
        }
        
        // Calculate cutoff date (14 days ago)
        $cutoff_time = strtotime('-14 days');
        
        foreach ($log_files as $log_file) {
            // Extract date from filename (wpwa-YYYY-MM-DD.log)
            $filename = basename($log_file);
            if (preg_match('/wpwa-(\d{4}-\d{2}-\d{2})\.log$/', $filename, $matches)) {
                $log_date = $matches[1];
                $log_time = strtotime($log_date);
                
                // Delete if older than cutoff date
                if ($log_time < $cutoff_time) {
                    @unlink($log_file);
                    $this->info("Removed old log file: $filename");
                }
            }
        }
        
        // Check total log size and compress if necessary
        $this->maybe_compress_logs();
    }
    
    /**
     * Compress logs if total size exceeds threshold
     */
    private function maybe_compress_logs() {
        $upload_dir = wp_upload_dir();
        $wpwa_dir = $upload_dir['basedir'] . '/wpwa/logs';
        
        if (!is_dir($wpwa_dir)) {
            return;
        }
        
        // Get all non-compressed log files
        $log_files = glob($wpwa_dir . '/wpwa-*.log');
        
        if (!is_array($log_files) || count($log_files) <= 1) {
            return; // Don't compress if only one log file or none
        }
        
        // Calculate total size
        $total_size = 0;
        $files_by_date = array();
        
        foreach ($log_files as $log_file) {
            $total_size += filesize($log_file);
            $filename = basename($log_file);
            
            if (preg_match('/wpwa-(\d{4}-\d{2}-\d{2})\.log$/', $filename, $matches)) {
                $date = $matches[1];
                $files_by_date[$date] = $log_file;
            }
        }
        
        // 10MB threshold
        $threshold = 10 * 1024 * 1024;
        
        // If we exceed threshold, compress oldest logs
        if ($total_size > $threshold && class_exists('ZipArchive')) {
            // Sort by date (oldest first)
            ksort($files_by_date);
            
            // Get files to compress (all except most recent)
            $files_to_compress = array_slice($files_by_date, 0, -1, true);
            
            if (!empty($files_to_compress)) {
                $this->compress_logs($files_to_compress);
            }
        }
    }
    
    /**
     * Compress specified log files into a ZIP archive
     *
     * @param array $files Files to compress
     */
    private function compress_logs($files) {
        if (!class_exists('ZipArchive')) {
            return;
        }
        
        $upload_dir = wp_upload_dir();
        $wpwa_dir = $upload_dir['basedir'] . '/wpwa/logs';
        
        // Create unique archive name
        $archive_name = 'wpwa-logs-archive-' . date('YmdHis') . '.zip';
        $archive_path = $wpwa_dir . '/' . $archive_name;
        
        $zip = new ZipArchive();
        
        if ($zip->open($archive_path, ZipArchive::CREATE) !== true) {
            $this->error('Could not create ZIP archive for log compression');
            return;
        }
        
        foreach ($files as $date => $file) {
            $filename = basename($file);
            if ($zip->addFile($file, $filename)) {
                // Successfully added to ZIP
                $this->info("Compressed log file: $filename");
            } else {
                $this->error("Failed to compress log file: $filename");
            }
        }
        
        $zip->close();
        
        // Delete original files if they were successfully added to the archive
        foreach ($files as $date => $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Get all logs for admin view
     *
     * @param int $limit Maximum number of log entries to return
     * @return array Log entries
     */
    public function get_logs_for_admin($limit = 100) {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $logs = array();
        $file = fopen($this->log_file, 'r');
        
        if ($file) {
            // Read from end of file to get most recent logs first
            $line_count = 0;
            $lines = array();
            
            while (($line = fgets($file)) !== false) {
                $lines[] = $line;
                $line_count++;
            }
            
            fclose($file);
            
            // Process lines in reverse order (newest first)
            $lines = array_reverse($lines);
            $lines = array_slice($lines, 0, $limit);
            
            foreach ($lines as $line) {
                if (preg_match('/\[(.*?)\] \[(.*?)\] (.*?)(?:\s(\{.*\}))?$/', $line, $matches)) {
                    $logs[] = array(
                        'timestamp' => $matches[1],
                        'level'     => $matches[2],
                        'message'   => $matches[3],
                        'context'   => isset($matches[4]) ? json_decode($matches[4], true) : array()
                    );
                }
            }
        }
        
        return $logs;
    }
    
    /**
     * Clean all logs
     *
     * @return boolean Success status
     */
    public function clean_logs() {
        $upload_dir = wp_upload_dir();
        $wpwa_dir = $upload_dir['basedir'] . '/wpwa/logs';
        
        if (!is_dir($wpwa_dir)) {
            return false;
        }
        
        // Get all log files
        $log_files = glob($wpwa_dir . '/wpwa-*.log');
        $archives = glob($wpwa_dir . '/wpwa-logs-archive-*.zip');
        
        $all_files = array_merge(is_array($log_files) ? $log_files : array(), 
                                is_array($archives) ? $archives : array());
        
        foreach ($all_files as $file) {
            @unlink($file);
        }
        
        // Create empty current log file
        file_put_contents($this->log_file, '');
        
        return true;
    }
}