<?php

namespace App\WordPressImport\Steps;

use App\WordPressImport\Importer;
use App\WordPressImport\Report;
use App\WordPressImport\WordPress;

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
