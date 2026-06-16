<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TestConnectionRequest;
use App\Http\Requests\Settings\UpdateConnectionRequest;
use App\Models\Connection;
use App\Services\OpenCart\ConnectionService;
use App\Services\OpenCart\IbsRouteResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConnectionController extends Controller
{
    public function __construct(
        private readonly ConnectionService $connectionService,
    ) {}

    public function edit(Request $request): View
    {
        if ($request->boolean('edit')) {
            session(['connection_editing' => true]);
        }

        if ($request->boolean('cancel')) {
            session()->forget('connection_editing');
        }

        $connection = $this->connectionService->getActive();
        $ibsDefaults = IbsRouteResolver::defaultFormEndpoints();
        $hasSavedConnection = $this->hasSavedConnection($connection);
        $isEditing = $this->isEditingMode($hasSavedConnection);
        $formData = $this->formDataFromRequestOrConnection($connection);
        $testPayload = session('test_results');
        $testChecks = is_array($testPayload) ? ($testPayload['checks'] ?? $testPayload) : null;
        $requiredChecksPassed = is_array($testPayload) && $this->connectionService->allChecksPassed($testPayload);
        $testPassed = $requiredChecksPassed && session()->has('connection_verified_fingerprint');
        $canSave = $isEditing && $testPassed && $this->connectionService->isVerifiedForSave($formData, $connection);
        $badgeStatus = $this->connectionService->resolveBadgeStatus($connection, $hasSavedConnection, $isEditing);

        return view('settings.connection', [
            'connection' => $connection,
            'ibsDefaults' => $ibsDefaults,
            'hasSavedConnection' => $hasSavedConnection,
            'isEditing' => $isEditing,
            'canSave' => $canSave,
            'testPassed' => $testPassed,
            'requiredChecksPassed' => $requiredChecksPassed,
            'badgeStatus' => $badgeStatus,
            'testResults' => $testChecks,
            'testSample' => is_array($testPayload) ? ($testPayload['sample'] ?? null) : null,
            'testMeta' => is_array($testPayload) ? ($testPayload['meta'] ?? null) : null,
            'testDiagnostics' => is_array($testPayload) ? ($testPayload['diagnostics'] ?? null) : null,
        ]);
    }

    public function update(UpdateConnectionRequest $request): RedirectResponse
    {
        $connection = Connection::getInstance();
        $validated = $request->validated();

        if (! $this->connectionService->isVerifiedForSave($validated, $connection)) {
            return redirect()
                ->route('connection.edit', ['edit' => 1])
                ->withInput($request->except('api_token'))
                ->with('error', 'Run Test Connection and pass all checks before saving.');
        }

        $this->connectionService->save(
            $this->connectionService->dataForSave($validated, $connection)
        );

        session()->forget([
            'connection_editing',
            'test_results',
        ]);
        $this->connectionService->clearVerification();

        return redirect()
            ->route('connection.edit')
            ->with('success', 'Connection saved successfully.');
    }

    public function test(TestConnectionRequest $request): RedirectResponse
    {
        $connection = Connection::getInstance();
        $validated = $request->validated();

        $resolved = $this->connectionService->resolveConnectionForTest($validated);
        $results = $this->connectionService->runTests($resolved);
        $allPassed = $this->connectionService->allChecksPassed($results);

        $formForVerification = IbsRouteResolver::normalizeConnectionInput($validated);
        if (is_array($results['resolved_endpoints'] ?? null)) {
            $formForVerification = array_merge($formForVerification, $results['resolved_endpoints']);
        }

        if ($allPassed) {
            $this->connectionService->markVerificationPassed($formForVerification, $connection);
        } else {
            $this->connectionService->clearVerification();
        }

        $this->connectionService->recordTestSnapshot(Connection::getInstance(), $results, $allPassed);

        return redirect()
            ->route('connection.edit', ['edit' => 1])
            ->with('test_results', $results)
            ->withInput(array_merge(
                $request->except(['api_token', 'is_active']),
                $results['resolved_endpoints'] ?? [],
                ['is_active' => $validated['is_active'] ?? false],
            ))
            ->with($allPassed ? 'success' : 'error', $allPassed
                ? 'Connection verified. You can save your settings.'
                : 'Connection test did not pass. Please review the checklist.');
    }

    public function clearLogs(): RedirectResponse
    {
        session()->forget('test_results');

        return redirect()
            ->back()
            ->with('info', 'Connection test logs cleared.')
            ->with('logs_tab', 'connection');
    }

    protected function hasSavedConnection(Connection $connection): bool
    {
        return filled($connection->store_url) && filled($connection->api_token);
    }

    protected function isEditingMode(bool $hasSavedConnection): bool
    {
        if (session('connection_editing', false)) {
            return true;
        }

        if (old('store_url') !== null) {
            return true;
        }

        return ! $hasSavedConnection;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDataFromRequestOrConnection(Connection $connection): array
    {
        if (old('store_url') !== null) {
            return [
                'store_url' => old('store_url', ''),
                'product_api_endpoint' => old('product_api_endpoint', ''),
                'order_api_endpoint' => old('order_api_endpoint', ''),
                'order_status_api_endpoint' => old('order_status_api_endpoint', ''),
                'supplier_filter' => old('supplier_filter', ''),
                'is_active' => $this->connectionService->normalizeBooleanInput(
                    old('is_active'),
                    (bool) $connection->is_active
                ),
                'api_token' => old('api_token'),
            ];
        }

        return [
            'store_url' => $connection->store_url,
            'product_api_endpoint' => $connection->product_api_endpoint,
            'order_api_endpoint' => $connection->order_api_endpoint,
            'order_status_api_endpoint' => $connection->order_status_api_endpoint,
            'supplier_filter' => $connection->supplier_filter,
            'is_active' => (bool) $connection->is_active,
            'api_token' => null,
        ];
    }
}
