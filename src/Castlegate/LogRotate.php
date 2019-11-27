<?php

namespace Castlegate;

class LogRotate
{
    /**
     * Path to log files.
     *
     * @var string
     */
    private $log_path;

    /**
     * Logs found in log path.
     *
     * @var array
     */
    private $logs = [];

    /**
     * Log filename format regex. Capture groups are required for year in 'Y'
     * format, month in 'm' format and the filename (excluding file extension).
     * The regex must only contain 3 capture groups.
     *
     * @var string
     */
    private $regex = '^([a-zA-Z0-9\-\_\.]+)\-((?:19|20)\d{2})\-([0-1]{1}[0-9]{1})';

    /**
     * The final regex after file extensions have been added.
     *
     * @var string
     */
    private $regex_final;

    /**
     * Regex capture group index for the year.
     *
     * @var int
     */
    private $index_year = 2;

    /**
     * Regex capture group index for the month.
     *
     * @var int
     */
    private $index_month = 3;

    /**
     * Regex capture group index for the file name.
     *
     * @var int
     */
    private $index_name = 1;

    /**
     * Number of months to retain logs for.
     *
     * @var int
     */
    private $retention = 6;

    /**
     * Prevent deleting of logs for testing purposes.
     *
     * @var bool
     */
    private $dry_run = false;

    /**
     * File extensions to rotate.
     *
     * @var array
     */
    private $extensions = [];

    /**
     * Set log rotation settings.
     *
     * @param string $log_path
     * @param int    $retention
     * @param bool   $dry_run
     */
    public function __construct($log_path)
    {
        // Settings.
        $this->log_path = $log_path;

        // Check the log directory exists.
        if (!$this->logDirectoryExists()) {
            error_log(__CLASS__.': The log directory ('.$this->log_path.') does not exist. Unable to rotate CSV logs.');
            return;
        }

        // Check the log directory is writable.
        if (!$this->logDirectoryIsWritable()) {
            error_log(__CLASS__.': The log directory ('.$this->log_path.') is not writable. Unable to rotate CSV logs.');
            return;
        }

        $this->setExtension('log');
    }

    /**
     * Find logs within the log directory which match the allowed file
     * extensions.
     *
     * @return void
     */
    private function discoverLogs()
    {
        // Find logs matching allowed file extensions.
        $logs = $this->findLogs();

        if (count($logs) == 0) {
            error_log(__CLASS__.': No logs were found matching the regex or extension. Regex: "'.$this->regex.'" Extensions: .'.implode(', .', $this->extensions));
        }

        // Loop through results.
        foreach ($logs as $log_path) {
            $log_file_name = basename($log_path);

            // Check against file name format.
            preg_match('/'.$this->getFinalRegex().'/', $log_file_name, $parts);

            // Only three capture groups allowed and logs must have a valid
            // year, month and file name. First match is always the whole string.
            if (count($parts) !== 4
                || !isset($parts[$this->index_year])
                || !isset($parts[$this->index_month])
                || !isset($parts[$this->index_name])
            ) {
                error_log('CSV Log Rotator found log which does not have a matching filename ('.basename($log_file_name).')');
                continue;
            }

            // Extract parts.
            $year = $parts[$this->index_year];
            $month = $parts[$this->index_month];
            $filename = $parts[$this->index_name];

            // Record a viable log file.
            if (!isset($this->logs[$filename])) {
                $this->logs[$filename] = [];
            }

            // Populate log data.
            $this->logs[$filename][] = [
                'year' => $year,
                'month' => $month,
                'path' => $log_path,
                'file_name' => $log_file_name,
            ];
        }
    }

    /**
     * Get all logs in the specified directory which matches the allowed file
     * extensions.
     *
     * @return string
     */
    private function findLogs()
    {
        $extensions = $this->extensions[0];

        if (count($this->extensions) > 1) {
            $extensions = '{'.implode(',', $this->extensions).'}';
        }

        return glob($this->log_path.'/*.'.$extensions);
    }

    /**
     * Trigger the rotation of logs for the chosen retention period.
     *
     * @return void
     */
    public function rotate()
    {
        // Find logs
        $this->discoverLogs();

        // Get each unique log.
        $logs = array_keys($this->logs);

        // Generate date at which we can discard data.
        $retention_date = new \DateTime();
        $retention_date->setDate(date('Y'), date('n'), 1);
        $retention_date->setTime(0, 0, 0, 0);
        $retention_date->modify('-'.($this->retention).' months');

        // Loop through all found logs.
        foreach ($logs as $name) {
            // Check for logs which are not within the allowed dates
            foreach ($this->logs[$name] as $log_data) {
                $check_date = new \DateTime();
                $check_date->setDate($log_data['year'], $log_data['month'], 1);
                $check_date->setTime(0, 0, 0, 0);

                // Perform check.
                if ($check_date < $retention_date) {
                    $this->delete($log_data['path']);
                }
            }
        }
    }

    /**
     * Deletes a log given file, or pretends to do so when performing a dry run.
     *
     * @param  string $file_path
     * @return bool
     */
    private function delete($file_path)
    {
        // Indicate dry runs in logging
        $message = '';
        if ($this->dry_run) {
            $message = 'DRY RUN: ';
        }

        $message.= 'Retention set to '.$this->retention.' month(s). ';

        if (unlink($file_path)) {
            error_log($message.'Deleted '.basename($file_path).' ('.$file_path.')');
            return true;
        } else {
            error_log($message.'Failed to delete '.basename($file_path).' ('.$file_path.')');
            return false;
        }
    }

    /**
     * Checks if the log directory exists.
     *
     * @return bool
     */
    private function logDirectoryExists()
    {
        return is_dir($this->log_path);
    }

    /**
     * Checks if the log directory is writable.
     *
     * @return bool
     */
    private function logDirectoryIsWritable()
    {
        return is_writable($this->log_path);
    }

    /**
     * The log regex needs to be built to include any file extensions.
     *
     * @return void
     */
    private function buildRegex()
    {
        // Add file extensions to the regex.
        $regex = $this->regex.'\.';
        $regex.= '(?:'.implode('|', $this->extensions).')';

        $this->regex_final = $regex;
    }

    /**
     * Get the current file extension setting.
     *
     * @return array
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * Set the file extensions to rotate.
     *
     * @param  array $extensions
     * @return void
     */
    public function setExtensions($extensions)
    {
        if (!is_array($extensions)) {
            $extensions = [$extensions];
        }

        $this->extensions = $extensions;

        // Build the regular expression.
        $this->buildRegex();
    }

    /**
     * Set the file extension to rotate.
     *
     * @param  string $extension
     * @return void
     */
    public function setExtension($extension)
    {
        $this->setExtensions($extension);

        // Build the regular expression.
        $this->buildRegex();
    }

    /**
     * Get the current retention setting.
     *
     * @return int
     */
    public function getRetention()
    {
        return $this->retention;
    }

    /**
     * Set the retention to x number of months.
     *
     * @param  int $retention
     * @return void
     */
    public function setRetention($retention)
    {
        // Ensure a positive value.
        $retention = (int) $retention;

        if ($retention < 1) {
            $retention = 1;
        }

        $this->retention = $retention;
    }

    /**
     * Get the current dry run setting.
     *
     * @return bool
     */
    public function getDryRun()
    {
        return $this->dry_run;
    }

    /**
     * Set the dry run setting. Dry run prevents files from being deleting.
     *
     * @param  bool $value
     * @return void
     */
    public function setDryRun($value)
    {
        $this->dry_run = (bool) $value;
    }

    /**
     * Set the dry run setting to true, preventing files from being deleted.
     *
     * @return void
     */
    public function dryRun()
    {
        $this->setDryRun(true);
    }

    /**
     * Get the current file name regex.
     *
     * @return string
     */
    public function getRegex()
    {
        return $this->regex;
    }

    /**
     * Set the file name regex. Capture groups must exist for the year, month
     * and name segments of the file name. File extension should be excluded.
     *
     * @param  string $regex
     * @return void
     */
    public function setRegex($regex)
    {
        $this->regex = $regex;
    }

    /**
     * Get the current file name regex.
     *
     * @return string
     */
    public function getFinalRegex()
    {
        return $this->regex_final;
    }

    /**
     * Get the index of the regex match used to extract the year segment of the
     * file name.
     *
     * @return int
     */
    public function getIndexYear()
    {
        return $this->index_year;
    }

    /**
     * Set the index of the regex match used to extract the year segment of the
     * file name.
     *
     * @param  int  $index_year
     * @return void
     */
    public function setIndexYear($index_year)
    {
        $this->index_year = (int) $index_year;
    }

    /**
     * Get the index of the regex match used to extract the month segment of the
     * file name.
     *
     * @return int
     */
    public function getIndexMonth()
    {
        return $this->index_month;
    }

    /**
     * Set the index of the regex match used to extract the month segment of the
     * file name.
     *
     * @param  int  $index_month
     * @return void
     */
    public function setIndexMonth($index_month)
    {
        $this->index_month = (int) $index_month;
    }

    /**
     * Get the index of the regex match used to extract the name segment of the
     * file name.
     *
     * @return string
     */
    public function getIndexName()
    {
        return $this->index_name;
    }

    /**
     * Set the index of the regex match used to extract the name segment of the
     * file name.
     *
     * @param  str  $index_name
     * @return void
     */
    public function setIndexName($index_name)
    {
        $this->index_name = $index_name;
    }
}
