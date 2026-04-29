<?php

namespace App\Integrations\Amo\Modules\PipelinesBuilder;

class PipelinesBuilderModule
{
    public function code(): string
    {
        return 'pipelines_builder';
    }

    public function name(): string
    {
        return 'Pipelines Builder';
    }

    public function routes(): array
    {
        return [
            'amo-accounts.pipelines.index',
            'amo-accounts.pipelines.create',
            'amo-accounts.pipelines.store',
        ];
    }
}
