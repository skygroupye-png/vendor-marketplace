<?php
namespace VMP\Constants;

defined('ABSPATH') || exit;

final class VendorStatus
{
    public const PENDING   = 'pending';
    public const APPROVED  = 'approved';
    public const REJECTED  = 'rejected';
    public const SUSPENDED = 'suspended';

    private function __construct() {}
}
