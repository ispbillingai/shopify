<?php
/**
 * Counter & warehouse copy, English.
 *
 * The module does not use PrestaShop's translation catalogues. Those live in the
 * database and are edited through the back office, which means a fresh install
 * of this module speaks English until somebody sits down and translates it in
 * the UI — and the staff who use these two screens are Italian. A pair of flat
 * files is the house convention anyway, and it makes the Italian ship with the
 * code.
 *
 * ShopFloorLang picks the file matching the signed-in employee's language and
 * falls back to this one key by key, so a missing Italian string degrades to
 * English rather than to a blank.
 */

declare(strict_types=1);

return [
    // Screens
    'counter_title' => 'Counter sales',
    'warehouse_title' => 'Warehouse',
    'serving_as' => 'Serving as',
    'loading_as' => 'Loading as',
    'taken_today' => 'taken today',
    'sales' => 'sales',
    'units_loaded_today' => 'units loaded today',
    'movements' => 'movements',

    // Search
    'scan_label' => 'Scan a barcode, or type a SKU or product name',
    'scan_placeholder' => 'Scan or search…',
    'results_here' => 'Results appear here.',
    'nothing_found' => 'Nothing found.',
    'in_stock' => 'in stock',
    'out_of_stock' => 'out of stock',
    'not_online' => 'not online',

    // Ticket
    'ticket' => 'Ticket',
    'ticket_empty' => 'Nothing on the ticket yet.',
    'total' => 'Total',
    'cash' => 'Cash',
    'card' => 'Card',
    'cash_received' => 'Cash received',
    'complete_sale' => 'Complete sale',
    'remove' => 'Remove',
    'change' => 'Change',
    'short_by' => 'Short by',

    // Receipt
    'sale_completed' => 'Sale completed',
    'order' => 'Order',
    'paid' => 'Paid',
    'next_customer' => 'Next customer',
    'print' => 'Print',
    'open_order' => 'Open order',

    // Warehouse
    'load_goods' => 'Load goods',
    'pick_product_left' => 'Pick a product on the left.',
    'in_stock_now' => 'In stock now',
    'goods_arrived' => 'Goods arrived',
    'stock_take' => 'Stock take',
    'how_many_arrived' => 'How many arrived',
    'how_many_shelf' => 'How many are actually on the shelf',
    'note_label' => 'Note (delivery number, supplier, reason…)',
    'save' => 'Save',
    'recent_movements' => 'Recent movements',
    'full_stock_page' => 'Full stock page',
    'nothing_loaded' => 'Nothing loaded yet.',

    // Movement log columns
    'col_when' => 'When',
    'col_product' => 'Product',
    'col_sku' => 'SKU',
    'col_type' => 'Type',
    'col_change' => 'Change',
    'col_after' => 'After',
    'col_who' => 'Who',
    'col_note' => 'Note',

    // Movement types
    'type_intake' => 'Goods in',
    'type_correction' => 'Stock take',
    'type_sale' => 'Sale',

    // Messages
    'err_ticket_empty' => 'The ticket is empty.',
    'err_unknown_payment' => 'Unknown payment method.',
    'err_enter_arrived' => 'Enter how many units arrived.',
    'err_enter_shelf' => 'Enter the quantity actually on the shelf.',
    'err_pick_product' => 'Pick a product first.',
    'err_same_quantity' => 'That is already the recorded quantity.',
    'err_enter_quantity' => 'Enter a quantity.',
    'err_server' => 'The server did not answer properly. Reload the page and sign in again.',
    'err_not_setup' => 'The counter is not set up. Reinstall the "Counter sales & warehouse" module.',
    'err_cart' => 'The till could not open a cart for this sale.',
    'err_no_order' => 'PrestaShop did not return an order for this ticket.',
    'err_line_incomplete' => 'A ticket line is missing a product or a quantity.',
    'err_only_in_stock' => 'Only %n in stock, %w asked for. Load the goods in the warehouse first, or reduce the quantity.',
    'err_not_orderable' => '%p is flagged "not available for order" in the catalogue, so it cannot be sold.',
    'err_not_added' => '%p could not be added to the ticket.',
    'err_out_of_stock' => '%p is out of stock.',
    'err_only_n_of' => 'Only %n of %p in stock.',
    'err_customer' => 'The counter customer could not be created.',
    'err_address' => 'The counter address could not be created.',
    'err_carrier' => 'No free carrier is available for any active country, so counter sales could not be set up. Create a free "pick up in store" carrier, then install this module again.',

    // Payment labels recorded on the order
    'payment_cash' => 'Counter sale — cash',
    'payment_card' => 'Counter sale — card',
];
