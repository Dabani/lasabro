<?php

return [
    'supplier_decided' => [
        'title' => 'Supplier review: :name',
        'body' => 'The review of supplier :name ended: :outcome. :note',
    ],
    'requisition_decided' => [
        'title' => 'Purchase requisition :code: :outcome',
        'body' => 'Your requisition ":title" was :outcome. :note',
    ],
    'po_decided' => [
        'title' => 'Purchase order :code: :outcome',
        'body' => 'Your purchase order ":title" was :outcome. :note',
    ],
    'po_issued' => [
        'title' => 'Purchase order issued: :code',
        'body' => 'Order :code for ":title" was sent to the supplier. Please confirm receipt when the goods arrive.',
    ],
    'goods_received' => [
        'title' => 'Goods received: :code',
        'body' => 'Receipt :receipt was recorded on order :code (":title").',
    ],
];
