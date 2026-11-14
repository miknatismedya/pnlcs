<?php

namespace Modules\Servers\Voyar\Http\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Servers\Voyar\VoyarClient;
use Modules\Servers\Voyar\VoyarException;
use Modules\Servers\Voyar\VoyarModule;
use Modules\Servers\Voyar\VoyarSetup;

/**
 * Staff: every server on the Voyar account next to the PNLCS service it
 * belongs to, servers nobody owns here (imported to a client), and the
 * places where the two disagree.
 */
class ServersController extends Controller
{
    public function index(Request $request)
    {
        $server = VoyarSetup::server();
        $remote = [];
        $error = null;
        if ($server) {
            try {
                $remote = $this->remoteServices($server);
            } catch (VoyarException $e) {
                $error = $e->getMessage();
            }
        }

        // PNLCS services that carry a Voyar id, by that id.
        $local = Service::with('client', 'product')->whereHas('product', fn ($q) => $q->where('server_type', 'voyar'))->get();
        $byId = $local->filter(fn ($s) => ! empty($s->module_data['voyar_id']))->keyBy(fn ($s) => (string) $s->module_data['voyar_id']);
        $remoteIds = collect($remote)->pluck('id')->map(fn ($id) => (string) $id)->all();

        $issues = [];
        foreach ($remote as $r) {
            $svc = $byId->get((string) $r['id']);
            if ($svc && $svc->status === 'active' && in_array($r['status'] ?? '', ['terminated', 'cancelled'], true)) {
                $issues[] = ['service' => $svc, 'text' => __('voyar::voyar.servers.issue_gone', ['id' => $r['id']])];
            }
            if ($svc && in_array($svc->status, ['terminated', 'cancelled'], true) && ($r['status'] ?? '') === 'active') {
                $issues[] = ['service' => $svc, 'text' => __('voyar::voyar.servers.issue_still_running', ['id' => $r['id']])];
            }
        }
        foreach ($local as $svc) {
            $vid = (string) ($svc->module_data['voyar_id'] ?? '');
            if ($vid === '' && $svc->status === 'pending' && $svc->created_at?->lt(now()->subMinutes(30))) {
                $issues[] = ['service' => $svc, 'text' => __('voyar::voyar.servers.issue_not_created')];
            }
            if ($vid !== '' && $svc->status === 'active' && $remote !== [] && ! in_array($vid, $remoteIds, true)) {
                $issues[] = ['service' => $svc, 'text' => __('voyar::voyar.servers.issue_unknown', ['id' => $vid])];
            }
        }

        return view('voyar::admin.servers', [
            'server' => $server,
            'error' => $error,
            'remote' => $remote,
            'byId' => $byId,
            'issues' => $issues,
            'showEnded' => $request->boolean('ended'),
            'products' => Product::where('server_type', 'voyar')->where('retired', false)->orderBy('name')->get(),
        ]);
    }

    /** Gives an unowned Voyar server to a client as a service on a product. */
    public function import(Request $request)
    {
        $data = $request->validate([
            'voyar_id' => ['required', 'string', 'max:40', 'regex:/^[A-Z]+-\d+$/'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'product_id' => ['required', 'integer'],
        ]);
        $server = VoyarSetup::server();
        abort_unless($server, 404);
        $product = Product::where('server_type', 'voyar')->findOrFail($data['product_id']);

        $taken = Service::where('module_data', 'like', '%"voyar_id":"'.$data['voyar_id'].'"%')->whereNotIn('status', ['terminated', 'cancelled'])->exists();
        if ($taken) {
            return back()->with('error', __('voyar::voyar.servers.already_owned'));
        }

        try {
            $remote = VoyarClient::for($server)->service($data['voyar_id']);
        } catch (VoyarException $e) {
            return back()->with('error', $e->getMessage());
        }
        if (($remote['status'] ?? '') !== 'active') {
            return back()->with('error', __('voyar::voyar.servers.not_active'));
        }
        if (($remote['type'] ?? 'prepaid') !== 'prepaid') {
            return back()->with('error', __('voyar::voyar.servers.hourly_import'));
        }

        $service = DB::transaction(function () use ($data, $product, $server, $remote) {
            $service = Service::create([
                'client_id' => $data['client_id'],
                'product_id' => $product->id,
                'server_id' => $server->id,
                'domain' => (string) ($remote['hostname'] ?? ''),
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'amount' => (float) ($product->priceFor('monthly') ?? 0),
                'first_payment_amount' => 0,
                'registration_date' => now()->toDateString(),
                'next_due_date' => ! empty($remote['expires_at']) ? substr((string) $remote['expires_at'], 0, 10) : now()->addMonth()->toDateString(),
                'username' => (string) ($remote['username'] ?? 'root'),
            ]);
            (new VoyarModule)->adopt($service, $remote);

            return $service;
        });

        return redirect()->route('admin.services.show', $service)->with('success', __('voyar::voyar.servers.imported', ['id' => $data['voyar_id']]));
    }

    /** @return array<int, array<string, mixed>> every service on the account, newest first */
    private function remoteServices($server): array
    {
        $api = VoyarClient::for($server);
        $all = [];
        for ($page = 1; $page <= 10; $page++) {
            $r = $api->get('/services/services/search', ['limit' => 100, 'page' => $page]);
            $rows = $r['services']['data'] ?? $r['services'] ?? [];
            $all = array_merge($all, $rows);
            if (count($rows) < 100) {
                break;
            }
        }

        return $all;
    }
}
