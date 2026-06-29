<?php

namespace App\Enums;

enum ReasonCode: string
{
    case NotAReceipt = 'not_a_receipt';
    case AmountUnreadable = 'amount_unreadable';
    case AmountMismatch = 'amount_mismatch';
    case MethodNotAccepted = 'method_not_accepted';
    case LowConfidence = 'low_confidence';
    case AiUnavailable = 'ai_unavailable';
    case OrganizerRejected = 'organizer_rejected';
}
