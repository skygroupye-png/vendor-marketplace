VENDOR APPROVAL WORKFLOW - MIGRATION AND USAGE

Files added:
- app/Constants/VendorStatus.php  (enum)
- app/Services/VendorApprovalService.php
- app/Database/Migrations/V2_AddVendorApprovalColumns.php

How to run migration (recommended on staging first):
1) Create a backup of your database.
2) From WP code (e.g. a WP-CLI command or theme/plugin bootstrap) call:

    \VMP\Database\Migrations\V2_AddVendorApprovalColumns::up();

   Example using WP-CLI:

    wp eval "require 'wp-load.php'; \VMP\\Database\\Migrations\\V2_AddVendorApprovalColumns::up();"

3) Verify new columns on table `${wpdb->prefix}vmp_vendors`:
   - status, approved_at, approved_by, rejected_at, rejected_by, reject_reason

4) Verify new table `${wpdb->prefix}vmp_vendor_history` created.

Usage notes:
- Use the service to approve/reject vendors programmatically:

    $service = new \VMP\Services\VendorApprovalService();
    $service->approveVendor($vendorId, $adminUserId);

- Hooks fired:
  - do_action('vmp.vendor.approved', $vendorId, $vendorRow, $byUserId);
  - do_action('vmp.vendor.rejected', $vendorId, $vendorRow, $byUserId, $reason);

- Emails/Notifications: listen to the above actions and send using a listener so templates remain editable from settings.

Notes on safety:
- Migration is idempotent (won't duplicate columns/tables if run repeatedly).
- Down migration is intentionally omitted to avoid accidental data loss; implement with care if needed.
