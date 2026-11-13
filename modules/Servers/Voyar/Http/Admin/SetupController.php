<?php

namespace Modules\Servers\Voyar\Http\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Servers\Voyar\VoyarException;
use Modules\Servers\Voyar\VoyarSetup;

/** Setup → Voyar: tie products to Voyar plans and build the order options. */
class SetupController extends Controller
{
    public function index(VoyarSetup $setup)
    {
        $server = VoyarSetup::server();
        $catalogue = null;
        $error = null;

        if ($server) {
            try {
                $catalogue = Cache::remember('voyar.setup.catalogue.'.$server->id, 300, fn () => $setup->catalogue($server));
            } catch (VoyarException $e) {
                $error = $e->getMessage();
            }
        }

        return view('voyar::admin.setup', [
            'server' => $server,
            'catalogue' => $catalogue,
            'error' => $error,
            'products' => VoyarSetup::products(),
        ]);
    }

    public function refresh()
    {
        if ($server = VoyarSetup::server()) {
            Cache::forget('voyar.setup.catalogue.'.$server->id);
            Cache::forget('voyar.os');
        }

        return back();
    }

    public function mapping(Request $request, VoyarSetup $setup)
    {
        $data = $request->validate([
            'map' => ['array'],
            'map.*' => ['nullable', 'string', 'max:50'],
            'kind' => ['array'],
            'kind.*' => ['nullable', 'in:vm,ftp'],
        ]);

        $n = $setup->applyMapping($data['map'] ?? [], $data['kind'] ?? []);

        return back()->with('success', __('voyar::voyar.setup.mapped', ['n' => $n]));
    }

    public function options(Request $request, VoyarSetup $setup)
    {
        $server = VoyarSetup::server();
        abort_unless($server, 404);
        $catalogue = Cache::get('voyar.setup.catalogue.'.$server->id) ?? $setup->catalogue($server);

        $data = $request->validate([
            'os' => ['array'], 'os.*' => ['string'],
            'location' => ['array'], 'location.*' => ['string'],
            'cpu' => ['array'], 'cpu.*' => ['in:amd,intel'],
            'app' => ['array'], 'app.*' => ['string'],
            'products' => ['required', 'array', 'min:1'], 'products.*' => ['integer'],
        ]);

        // Labels come from Voyar's own catalogue, never from the form.
        // Not flatMap: it renumbers the numeric ids.
        $os = [];
        foreach ($catalogue['families'] as $family) {
            foreach ($family['operatingsystems'] ?? [] as $o) {
                $os[(string) $o['id']] = $o['name'];
            }
        }
        $locations = collect($catalogue['locations'])->mapWithKeys(fn ($l) => [(string) $l['id'] => $l['name']]);
        $apps = collect($catalogue['apps'])->mapWithKeys(fn ($a) => [(string) $a['id'] => $a['name']]);
        $cpu = ['amd' => 'AMD EPYC', 'intel' => 'Intel Xeon'];

        $pick = function ($all, array $chosen): array {
            $out = [];
            foreach ($chosen as $id) {
                if (isset($all[$id])) {
                    $out[(string) $id] = $all[$id];
                }
            }

            return $out;
        };
        $choices = [
            'location' => $pick($locations, $data['location'] ?? []),
            'os' => $pick($os, $data['os'] ?? []),
            'cpu' => $pick($cpu, $data['cpu'] ?? []),
            'app' => ($a = $pick($apps, $data['app'] ?? [])) ? ['none' => __('voyar::voyar.setup.no_app')] + $a : [],
        ];
        if ($choices['location'] === [] || $choices['os'] === []) {
            return back()->with('error', __('voyar::voyar.setup.need_os_location'));
        }

        $setup->buildOptions($choices, array_map('intval', $data['products']));

        return back()->with('success', __('voyar::voyar.setup.options_built'));
    }

    public function addons(Request $request, VoyarSetup $setup)
    {
        $data = $request->validate([
            'addons' => ['required', 'array', 'min:1'], 'addons.*' => ['integer'],
            'margin' => ['required', 'numeric', 'min:0', 'max:1000'],
            'products' => ['required', 'array', 'min:1'], 'products.*' => ['integer'],
        ]);
        $chosen = collect($this->catalogue($setup)['addons'] ?? [])->whereIn('id', array_map('intval', $data['addons']))->values()->all();

        try {
            $n = $setup->addAddons($chosen, (float) $data['margin'], array_map('intval', $data['products']));
        } catch (VoyarException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('voyar::voyar.setup.addons_built', ['n' => $n]));
    }

    public function ftp(Request $request, VoyarSetup $setup)
    {
        $data = $request->validate(['margin' => ['required', 'numeric', 'min:0', 'max:1000']]);

        try {
            $n = $setup->createFtpProducts($this->catalogue($setup)['products'] ?? [], (float) $data['margin']);
        } catch (VoyarException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('voyar::voyar.setup.ftp_built', ['n' => $n]));
    }

    private function catalogue(VoyarSetup $setup): array
    {
        $server = VoyarSetup::server();
        abort_unless($server, 404);

        return Cache::get('voyar.setup.catalogue.'.$server->id) ?? $setup->catalogue($server);
    }
}
