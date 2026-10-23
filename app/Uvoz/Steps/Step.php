<?php

namespace App\Uvoz\Steps;

use App\Uvoz\Importer;
use App\Uvoz\Report;
use App\Uvoz\WordPress;

abstract class Step
{
    protected WordPress $wp;

    protected Report $report;

    public function __construct(protected Importer $ctx)
    {
        $this->wp = $ctx->wp;
        $this->report = $ctx->report;
    }

    abstract public function run(): void;
}
