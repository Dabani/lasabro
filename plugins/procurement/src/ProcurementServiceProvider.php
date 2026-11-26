<?php

namespace Lasabro\Procurement;

use Lasabro\Framework\PluginServiceProvider;
use Lasabro\Procurement\Services\OrderApprovalHandler;
use Lasabro\Procurement\Services\RequisitionApprovalHandler;
use Lasabro\Procurement\Services\SupplierApprovalHandler;

/** Approval outcome handlers for the subjects "supplier", "purchase_requisition" and "purchase_order". */
class ProcurementServiceProvider extends PluginServiceProvider
{
    protected function bootPlugin(): void
    {
        config([
            'cops.approval_handlers' => array_merge(config('cops.approval_handlers', []), ['supplier' => SupplierApprovalHandler::class, 'purchase_requisition' => RequisitionApprovalHandler::class, 'purchase_order' => OrderApprovalHandler::class]),
        ]);
    }
}
