<?php

namespace App\Enums;

enum ReceiptStatus: string
{
    case Submitted = 'submitted';
    case Validated = 'validated';
    case NeedsReview = 'needs_review';
    case Rejected = 'rejected';
    case Cash = 'cash';
}
