<?php

namespace App\Enums;

/** Documents issued for an order (fiscal receipts through the V-PFR, invoices for firms). */
enum ReceiptType: string
{
    case FiscalSale = 'fiscal_sale';       // "Promet prodaja", payment method "prenos na račun"
    case FiscalAdvance = 'fiscal_advance'; // "Avans prodaja": payment seen after the day it arrived
    case FiscalRefund = 'fiscal_refund';   // "Promet refundacija", references the original receipt
    case Invoice = 'invoice';              // firm buyer: plain invoice, no SEF (outside the VAT system)
    case InvoiceCredit = 'invoice_credit'; // firm refund: credit note referencing the invoice

    public function label(): string
    {
        return match ($this) {
            self::FiscalSale => 'Fiskalni račun (promet prodaja)',
            self::FiscalAdvance => 'Avansni fiskalni račun',
            self::FiscalRefund => 'Fiskalni račun refundacije',
            self::Invoice => 'Račun',
            self::InvoiceCredit => 'Knjižno odobrenje',
        };
    }
}
