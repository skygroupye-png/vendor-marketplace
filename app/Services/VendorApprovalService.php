<?php
namespace VMP\Services;

defined('ABSPATH') || exit;

use VMP\Constants\VendorStatus;

/**
 * Service responsible for approving/rejecting vendors.
 * - Runs DB transaction
 * - Updates vendor status fields
 * - Assigns vmp_vendor role to WP user (add_role)
 * - Records history in vmp_vendor_history
 * - Fires actions for other listeners
 */
class VendorApprovalService
{
    private \wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Approve a vendor.
     * @param int $vendorId
     * @param int $byUserId
     * @return bool
     * @throws \Exception
     */
    public function approveVendor(int $vendorId, int $byUserId): bool
    {
        $table = $this->wpdb->prefix . 'vmp_vendors';
        $historyTable = $this->wpdb->prefix . 'vmp_vendor_history';

        $this->wpdb->query('START TRANSACTION');
        try {
            $vendor = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d FOR UPDATE", $vendorId));
            if (!$vendor) {
                throw new \Exception(__('Vendor not found.', 'vmp'));
            }

            $now = current_time('mysql');

            $updated = $this->wpdb->update(
                $table,
                [
                    'status' => VendorStatus::Approved->value,
                    'approved_at' => $now,
                    'approved_by' => $byUserId,
                ],
                ['id' => $vendorId],
                ['%s', '%s', '%d'],
                ['%d']
            );

            if ($updated === false) {
                throw new \Exception(__('Failed to update vendor status.', 'vmp'));
            }

            // Assign role to the WP user associated with vendor (use add_role to preserve existing roles)
            $user_id = (int) ($vendor->user_id ?? 0);
            if ($user_id) {
                $wp_user = get_user_by('id', $user_id);
                if ($wp_user) {
                    // add_role is method on WP_User
                    $wp_user->add_role('vmp_vendor');
                }
            }

            // Insert history record
            $this->wpdb->insert(
                $historyTable,
                [
                    'vendor_id' => $vendorId,
                    'action' => 'approved',
                    'performed_by' => $byUserId,
                    'reason' => null,
                    'created_at' => $now,
                ],
                ['%d', '%s', '%d', '%s', '%s']
            );

            do_action('vmp.vendor.approved', $vendorId, $vendor, $byUserId);

            $this->wpdb->query('COMMIT');
            return true;
        } catch (\Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Reject a vendor with optional reason.
     * @param int $vendorId
     * @param int $byUserId
     * @param string|null $reason
     * @return bool
     * @throws \Exception
     */
    public function rejectVendor(int $vendorId, int $byUserId, ?string $reason = null): bool
    {
        $table = $this->wpdb->prefix . 'vmp_vendors';
        $historyTable = $this->wpdb->prefix . 'vmp_vendor_history';

        $this->wpdb->query('START TRANSACTION');
        try {
            $vendor = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d FOR UPDATE", $vendorId));
            if (!$vendor) {
                throw new \Exception(__('Vendor not found.', 'vmp'));
            }

            $now = current_time('mysql');

            $updated = $this->wpdb->update(
                $table,
                [
                    'status' => VendorStatus::Rejected->value,
                    'rejected_at' => $now,
                    'rejected_by' => $byUserId,
                    'reject_reason' => $reason,
                ],
                ['id' => $vendorId],
                ['%s', '%s', '%d', '%s'],
                ['%d']
            );

            if ($updated === false) {
                throw new \Exception(__('Failed to update vendor status.', 'vmp'));
            }

            // Insert history record
            $this->wpdb->insert(
                $historyTable,
                [
                    'vendor_id' => $vendorId,
                    'action' => 'rejected',
                    'performed_by' => $byUserId,
                    'reason' => $reason,
                    'created_at' => $now,
                ],
                ['%d', '%s', '%d', '%s', '%s']
            );

            do_action('vmp.vendor.rejected', $vendorId, $vendor, $byUserId, $reason);

            $this->wpdb->query('COMMIT');
            return true;
        } catch (\Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            throw $e;
        }
    }
}
