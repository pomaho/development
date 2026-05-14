<?php

namespace App\Integrations\Amo\Modules\CatalogsBuilder;

class CatalogsBuilderModule
{
    public function code(): string
    {
        return 'catalogs_builder';
    }

    public function name(): string
    {
        return 'Catalogs Builder';
    }

    public function routes(): array
    {
        return [
            'amo-accounts.catalogs.index',
            'amo-accounts.catalogs.store',
            'amo-accounts.catalogs.elements.store',
            'amo-accounts.catalogs.chained-list-fields.store',
        ];
    }
}
