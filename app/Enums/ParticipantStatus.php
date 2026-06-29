<?php

namespace App\Enums;

enum ParticipantStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
}
