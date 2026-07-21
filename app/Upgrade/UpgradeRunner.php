<?php
namespace VMP\Upgrade;

defined('ABSPATH') || exit;

/**
 * Upgrade runner executes versioned migrations safely with a lock and timeout.
 */
class UpgradeRunner
{
    private string $lockOption = 'vmp_upgrade_lock';
    private string $versionOption = 'vmp_db_version';
    /** timeout in seconds (10 minutes) */
    private int $lockTimeout = 600;

    public function run(): void
    {
        // check lock and timeout
        $lock = get_option($this->lockOption);
        if ($lock) {
            if (is_numeric($lock) && (time() - (int) $lock) < $this->lockTimeout) {
                // another process is running migrations recently -> skip
                return;
            }
            // lock expired — attempt to continue and overwrite old lock
        }

        // attempt to acquire lock (timestamp)
        update_option($this->lockOption, time());

        $start = microtime(true);
        $previousVersion = get_option($this->versionOption, '1.0.0');
        $newVersion = $previousVersion;

        try {
            $current = $previousVersion;

            $migrations = [
                // version => [ClassName, 'up']
                '1.1.0' => ['\\VMP\\Database\\Migrations\\V2_AddVendorApprovalColumns', 'up'],
            ];

            foreach ($migrations as $version => $callable) {
                if (version_compare($current, $version, '<')) {
                    if (is_callable($callable)) {
                        call_user_func($callable);
                        update_option($this->versionOption, $version);
                        $current = $version;
                        $newVersion = $version;
                    }
                }
            }

            $duration = microtime(true) - $start;
            // log upgrade success
            $msg = sprintf(
                '[VMP][UpgradeRunner] Upgrade succeeded: %s -> %s (%.3f sec)',
                $previousVersion,
                $newVersion,
                $duration
            );
            if (function_exists('error_log')) {
                error_log($msg);
            }
            if (function_exists('do_action')) {
                do_action('vmp.upgrade.success', $previousVersion, $newVersion, $duration);
            }

        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            $msg = sprintf('[VMP][UpgradeRunner] Upgrade failed: %s -> %s after %.3f sec: %s', $previousVersion, $newVersion, $duration, $e->getMessage());
            if (function_exists('error_log')) {
                error_log($msg);
            }
            if (function_exists('do_action')) {
                do_action('vmp.upgrade.failed', $previousVersion, $newVersion, $duration, $e);
            }

            // release lock on failure so future attempts can proceed
            delete_option($this->lockOption);
            return;
        }

        // release lock
        delete_option($this->lockOption);
    }
}
