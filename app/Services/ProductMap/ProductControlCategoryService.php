<?php



namespace App\Services\ProductMap;



use App\Models\ProductMap\ProductControlState;

use App\Models\Supplier;



class ProductControlCategoryService

{

    /**

     * Local Product Control categories only (user-created, persisted in DB).

     *

     * @return array<int, string>

     */

    public function categoriesForSupplier(?Supplier $supplier = null): array

    {

        $supplier ??= app(ProductControlSupplierResolver::class)->resolve();



        return ProductControlState::query()

            ->where('supplier_id', $supplier->id)

            ->whereNotNull('product_category')

            ->where('product_category', '!=', '')

            ->distinct()

            ->orderBy('product_category')

            ->pluck('product_category')

            ->all();

    }



    public function normalize(?string $value): ?string

    {

        $value = trim((string) $value);



        return $value !== '' ? $value : null;

    }

}

