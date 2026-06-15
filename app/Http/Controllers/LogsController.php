<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogsController extends Controller
{
    public function clearProductMap(): RedirectResponse
    {
        session()->forget('product_preview');

        return $this->redirectBackWithCleared('product-map', 'Product Map preview logs cleared.');
    }

    protected function redirectBackWithCleared(string $tab, string $message): RedirectResponse
    {
        return redirect()
            ->back()
            ->with('info', $message)
            ->with('logs_tab', $tab);
    }
}
