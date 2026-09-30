<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Open = 'open';
    case Filled = 'filled';
    case Cancelled = 'cancelled';
}
