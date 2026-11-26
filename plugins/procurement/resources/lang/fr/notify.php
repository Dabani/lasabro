<?php

return [
    'supplier_decided' => [
        'title' => 'Revue du fournisseur : :name',
        'body' => 'La revue du fournisseur :name est terminée : :outcome. :note',
    ],
    'requisition_decided' => [
        'title' => 'Demande d\'achat :code : :outcome',
        'body' => 'Votre demande « :title » : :outcome. :note',
    ],
    'po_decided' => [
        'title' => 'Bon de commande :code : :outcome',
        'body' => 'Votre bon de commande « :title » : :outcome. :note',
    ],
    'po_issued' => [
        'title' => 'Bon de commande émis : :code',
        'body' => 'Le bon :code pour « :title » a été envoyé au fournisseur. Merci de confirmer la réception à l\'arrivée des marchandises.',
    ],
    'goods_received' => [
        'title' => 'Marchandises reçues : :code',
        'body' => 'La réception :receipt a été enregistrée sur le bon :code (« :title »).',
    ],
];
