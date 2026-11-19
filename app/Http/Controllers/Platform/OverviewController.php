<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformMetrics;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __invoke(PlatformMetrics $metrics): Response
    {
        return Inertia::render('nexo/Overview', ['metrics' => $metrics->overview()]);
    }
}
