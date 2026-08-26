<?php
/**
 * Counter & warehouse copy, Italian.
 *
 * This is the language the staff who use these two screens actually work in.
 * Wording follows what a shop says out loud rather than a literal translation:
 * "scontrino" for the ticket, "carico" for goods in, "giacenza" for stock on
 * hand, "resto" for change.
 */

declare(strict_types=1);

return [
    // Schermate
    'counter_title' => 'Vendita al banco',
    'warehouse_title' => 'Magazzino',
    'serving_as' => 'Alla cassa',
    'loading_as' => 'Carico a cura di',
    'taken_today' => 'incassato oggi',
    'sales' => 'vendite',
    'units_loaded_today' => 'pezzi caricati oggi',
    'movements' => 'movimenti',

    // Ricerca
    'scan_label' => 'Scansiona un codice a barre, oppure scrivi uno SKU o il nome del prodotto',
    'scan_placeholder' => 'Scansiona o cerca…',
    'results_here' => 'Qui compaiono i risultati.',
    'nothing_found' => 'Nessun risultato.',
    'in_stock' => 'in giacenza',
    'out_of_stock' => 'esaurito',
    'not_online' => 'non online',

    // Scontrino
    'ticket' => 'Scontrino',
    'ticket_empty' => 'Nessun articolo sullo scontrino.',
    'total' => 'Totale',
    'cash' => 'Contanti',
    'card' => 'Carta',
    'cash_received' => 'Contanti ricevuti',
    'complete_sale' => 'Concludi vendita',
    'remove' => 'Rimuovi',
    'change' => 'Resto',
    'short_by' => 'Mancano',

    // Ricevuta
    'sale_completed' => 'Vendita conclusa',
    'order' => 'Ordine',
    'paid' => 'Pagato',
    'next_customer' => 'Cliente successivo',
    'print' => 'Stampa',
    'open_order' => 'Apri ordine',

    // Magazzino
    'load_goods' => 'Carico merce',
    'pick_product_left' => 'Scegli un prodotto a sinistra.',
    'in_stock_now' => 'Giacenza attuale',
    'goods_arrived' => 'Merce arrivata',
    'stock_take' => 'Inventario',
    'how_many_arrived' => 'Quanti pezzi sono arrivati',
    'how_many_shelf' => 'Quanti pezzi ci sono davvero a scaffale',
    'note_label' => 'Nota (numero bolla, fornitore, motivo…)',
    'save' => 'Salva',
    'recent_movements' => 'Movimenti recenti',
    'full_stock_page' => 'Pagina giacenze completa',
    'nothing_loaded' => 'Nessun movimento registrato.',

    // Colonne del registro
    'col_when' => 'Quando',
    'col_product' => 'Prodotto',
    'col_sku' => 'SKU',
    'col_type' => 'Tipo',
    'col_change' => 'Variazione',
    'col_after' => 'Dopo',
    'col_who' => 'Chi',
    'col_note' => 'Nota',

    // Tipi di movimento
    'type_intake' => 'Carico',
    'type_correction' => 'Inventario',
    'type_sale' => 'Vendita',

    // Messaggi
    'err_ticket_empty' => 'Lo scontrino è vuoto.',
    'err_unknown_payment' => 'Metodo di pagamento sconosciuto.',
    'err_enter_arrived' => 'Inserisci quanti pezzi sono arrivati.',
    'err_enter_shelf' => 'Inserisci la quantità realmente a scaffale.',
    'err_pick_product' => 'Scegli prima un prodotto.',
    'err_same_quantity' => 'È già la quantità registrata.',
    'err_enter_quantity' => 'Inserisci una quantità.',
    'err_server' => 'Il server non ha risposto correttamente. Ricarica la pagina e accedi di nuovo.',
    'err_not_setup' => 'Il banco non è configurato. Reinstalla il modulo "Vendita al banco e magazzino".',
    'err_cart' => 'La cassa non è riuscita ad aprire un carrello per questa vendita.',
    'err_no_order' => 'PrestaShop non ha restituito un ordine per questo scontrino.',
    'err_line_incomplete' => 'In una riga dello scontrino manca il prodotto o la quantità.',
    'err_only_in_stock' => 'Solo %n in giacenza, ne sono stati richiesti %w. Carica prima la merce in magazzino, oppure riduci la quantità.',
    'err_not_orderable' => '%p è segnato come "non disponibile per l\'ordine" nel catalogo, quindi non può essere venduto.',
    'err_not_added' => 'Non è stato possibile aggiungere %p allo scontrino.',
    'err_out_of_stock' => '%p è esaurito.',
    'err_only_n_of' => 'Solo %n pezzi di %p in giacenza.',
    'err_customer' => 'Non è stato possibile creare il cliente del banco.',
    'err_address' => 'Non è stato possibile creare l\'indirizzo del banco.',
    'err_carrier' => 'Nessun corriere gratuito è disponibile per i paesi attivi, quindi la vendita al banco non è stata configurata. Crea un corriere gratuito di ritiro in negozio, poi installa di nuovo il modulo.',

    // Etichette di pagamento registrate sull\'ordine
    'payment_cash' => 'Vendita al banco — contanti',
    'payment_card' => 'Vendita al banco — carta',
];
