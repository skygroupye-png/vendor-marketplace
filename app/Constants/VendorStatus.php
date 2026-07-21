<?php
namespace VMP\Constants;

defined('ABSPATH') || exit;

enum VendorStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
