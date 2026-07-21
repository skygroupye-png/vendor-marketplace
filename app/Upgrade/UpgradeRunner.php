<?php
namespace VMP\Upgrade;

defined('ABSPATH') || exit;

/**
 * Upgrade runner executes versioned migrations safely with a simple lock.
 */
class UpgradeRunner
{
    private string $lockOption = 'vmp_upgrade_lock';
    private string $versionOption = 'vmp_db_version';

    public function run(): void
    {
        // simple lock to avoid concurrent runs
        if (get_option($this->lockOption)) {
            return;
        }

        // attempt to acquire lock
        update_option($this->lockOption, time());

        try {
            $current = get_option($this->versionOption, '1.0.0');

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
                    }
                }
            }

        } catch (\Throwable $e) {
            // log error but do not break the site
            if (function_exists('error_log')) {
                error_log('[VMP][UpgradeRunner] Migration failed: ' . $e->getMessage());
            }
        } finally {
            // release lock
            delete_option($this->lockOption);
        }
    }
}
