<?php

namespace App\Http\Resources\Export;

use App\Http\Resources\Pipeline\PipelineRunResource;

/**
 * @deprecated Use {@see PipelineRunResource} directly.
 *
 * Retained so existing references keep working; it now returns the aggregated
 * pipeline run contract (PIPE-02).
 */
class DataProcessingJobResource extends PipelineRunResource {}
