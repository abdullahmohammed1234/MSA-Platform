<?php

namespace App\Spms\Enums;

enum PaymentMethod: string
{
    case Square = 'square';
    case Cheque = 'cheque';
    case ETransfer = 'etransfer';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case InvoiceManual = 'invoice_manual';
}
