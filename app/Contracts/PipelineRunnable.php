<?php

namespace App\Contracts;

use App\Enums\PipelineRunType;

/**
 * Implemented by any model that owns a pipeline event stream (a "run").
 *
 * Lets the recorder accept the run model directly instead of a loose
 * type/id pair, so new run tables (Clockify sync runs) plug in without
 * touching the recorder.
 */
interface PipelineRunnable
{
    /**
     * The run kind this model writes events under.
     */
    public function pipelineRunType(): PipelineRunType;

    /**
     * The run's identifier within its stream (string so it fits any run table).
     */
    public function pipelineRunId(): string;
}
