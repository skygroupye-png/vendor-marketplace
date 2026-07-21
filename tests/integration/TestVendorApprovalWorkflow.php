<?php
/**
 * Integration tests for Vendor Approval Workflow.
 * Requires WP PHPUnit environment.
 */
class TestVendorApprovalWorkflow extends WP_UnitTestCase
{
    private $wpdb;

    public function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $this->wpdb = $wpdb;

        // Ensure migration has run on test DB
        if (method_exists('\VMP\Database\Migrations\V2_AddVendorApprovalColumns', 'up')) {
            \VMP\Database\Migrations\V2_AddVendorApprovalColumns::up();
        }
    }

    public function test_full_approval_flow()
    {
        // create admin and user
        $admin_id = $this->factory->user->create(['role' => 'administrator']);
        $user_id  = $this->factory->user->create(['role' => 'subscriber']);

        // create vendor record (simulate registration)
        $vendor_table = $this->wpdb->prefix . 'vmp_vendors';
        $now = current_time('mysql');
        $this->wpdb->insert($vendor_table, [
            'user_id' => $user_id,
            'store_name' => 'Test Store',
            'store_slug' => 'test-store-' . time(),
            'status' => 'pending',
            'created_at' => $now,
        ], ['%d','%s','%s','%s','%s']);

        $vendorId = (int)$this->wpdb->insert_id;
        $this->assertGreaterThan(0, $vendorId);

        // ensure pending
        $status = $this->wpdb->get_var($this->wpdb->prepare("SELECT status FROM {$vendor_table} WHERE id = %d", $vendorId));
        $this->assertEquals('pending', $status);

        // listen to event
        $fired = false;
        add_action('vmp.vendor.approved', function($id) use (&$fired) { $fired = true; });

        // approve
        $svc = new \VMP\Services\VendorApprovalService();
        $svc->approveVendor($vendorId, $admin_id);

        // assertions
        $status = $this->wpdb->get_var($this->wpdb->prepare("SELECT status FROM {$vendor_table} WHERE id = %d", $vendorId));
        $this->assertEquals('approved', $status);

        $approved_by = $this->wpdb->get_var($this->wpdb->prepare("SELECT approved_by FROM {$vendor_table} WHERE id = %d", $vendorId));
        $this->assertEquals((string)$admin_id, (string)$approved_by);

        $approved_at = $this->wpdb->get_var($this->wpdb->prepare("SELECT approved_at FROM {$vendor_table} WHERE id = %d", $vendorId));
        $this->assertNotEmpty($approved_at);

        // check user role
        $user = get_userdata($user_id);
        $this->assertTrue(in_array('vmp_vendor', (array)$user->roles));

        // check history
        $history_table = $this->wpdb->prefix . 'vmp_vendor_history';
        $exists = $this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$history_table} WHERE vendor_id = %d AND action = %s LIMIT 1", $vendorId, 'approved'));
        $this->assertNotFalse($exists);

        // check event fired
        $this->assertTrue($fired);
    }
}
