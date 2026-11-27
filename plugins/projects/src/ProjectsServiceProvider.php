<?php

namespace Lasabro\Projects;

use Lasabro\Framework\PluginServiceProvider;
use Lasabro\Projects\Services\ProjectReviewerResolver;
use Lasabro\Projects\Services\TaskReviewApprovalHandler;

/** Registers the task_review approval handler and the project_reviewer step resolver. */
class ProjectsServiceProvider extends PluginServiceProvider
{
    protected function bootPlugin(): void
    {
        config([
            'cops.approval_handlers' => array_merge(config('cops.approval_handlers', []), ['task_review' => TaskReviewApprovalHandler::class]),
            'cops.step_resolvers' => array_merge(config('cops.step_resolvers', []), ['project_reviewer' => ProjectReviewerResolver::class]),
        ]);
    }
}
