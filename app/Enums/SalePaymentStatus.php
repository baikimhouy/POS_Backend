<?php

namespace App\Enums;

/** Kept separate from SaleStatus: choosing a payment method does not mean the sale is paid. */
enum SalePaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
}
