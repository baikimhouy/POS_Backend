<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Sale = 'sale';
    case PurchaseReceipt = 'purchase_receipt';
    case Adjustment = 'adjustment';
    case Return = 'return';
    case VoidReversal = 'void_reversal';
}
