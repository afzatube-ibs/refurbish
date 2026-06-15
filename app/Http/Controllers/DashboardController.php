<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $connection = Connection::getInstance();
        $connectionSaved = filled($connection->store_url) && $connection->is_active;

        return view('dashboard.index', [
            'connectionSaved' => $connectionSaved,
            'connection' => $connection,
            'roadmap' => config('dropflow.roadmap', []),
            'modules' => config('dropflow.modules', []),
        ]);
    }
}
