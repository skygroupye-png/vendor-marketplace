### PR: feat(vendors): implement vendor approval workflow and audit history

This Pull Request contains the backend implementation for the Vendor Approval workflow.

What this PR includes:

- VendorStatus (final class) — constants for vendor states
- VendorApprovalService — approveVendor() and rejectVendor() with DB transactions, add_role('vmp_vendor'), history writes and events
- Migration: V2_AddVendorApprovalColumns (idempotent) to add approval/rejection columns and create vmp_vendor_history
- UpgradeRunner — versioned upgrade runner wired into VendorServiceProvider (safe lock with timeout and logging)
- Integration test: tests/integration/TestVendorApprovalWorkflow.php
- MIGRATIONS_README.md (how to run migration, usage)

Important notes before merging:

- Run the migration on Staging first (backup DB). The UpgradeRunner will attempt to run migrations automatically when the plugin loads, but running manually on staging is recommended to verify behavior.
- Do NOT merge into main until all Staging tests and integration tests pass and code review is complete.

Checklist (to be validated on Staging / CI):
- [ ] Backup DB on Staging
- [ ] Run migration (or allow UpgradeRunner to run) and confirm new columns exist
- [ ] Confirm vmp_vendor_history table was created
- [ ] Register flow creates vendor with status = pending
- [ ] Approve flow updates status/approved_at/approved_by and adds vmp_vendor role
- [ ] Reject flow updates rejected_* and records reason in history
- [ ] Integration test passes in CI
- [ ] No critical code review comments

