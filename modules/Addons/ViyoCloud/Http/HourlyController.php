<?php

namespace Modules\Addons\ViyoCloud\Http;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Addons\ViyoCloud\Support\Catalog;
use Modules\Addons\ViyoCloud\Support\HourlyBilling;
use RuntimeException;

/** Ordering, watching and deleting servers paid by the hour. */
class HourlyController extends Controller
{
    use ResolvesClient;

    /** Order keys the form asks, in order. */
    private const KEYS = ['location', 'os', 'cpu', 'app'];

    public function create(Request $request)
    {
        $client = $this->currentClient();
        abort_unless($client, 403);

        $plans = Catalog::plans('cloud')->filter(fn ($p) => $p['hourly_raw'])->values();
        abort_if($plans->isEmpty() || ! HourlyBilling::settings()['enabled'], 404);

        $slug = (string) $request->query('plan', '');
        $plan = $plans->firstWhere('slug', $slug) ?? $plans->firstWhere('featured', true) ?? $plans->first();
        $product = Product::where('slug', $plan['slug'])->firstOrFail();

        return view('viyocloud::client.hourly-create', [
            'plans' => $plans,
            'plan' => $plan,
            'choices' => self::choices($product),
            'balance' => (float) $client->credit,
            'settings' => HourlyBilling::settings(),
        ]);
    }

    public function store(Request $request, HourlyBilling $billing)
    {
        $client = $this->currentClient();
        abort_unless($client, 403);

        $data = $request->validate([
            'plan' => ['required', 'string'],
            'hostname' => ['nullable', 'string', 'max:63', 'regex:/^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$/'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:50'],
            'accept' => ['accepted'],
        ]);

        $product = Product::where('slug', $data['plan'])->where('hidden', false)->where('retired', false)
            ->whereHas('group', fn ($q) => $q->where('slug', Catalog::GROUPS['cloud']))->firstOrFail();

        // Only values the product actually offers.
        $choices = self::choices($product);
        $options = [];
        foreach (self::KEYS as $key) {
            $value = (string) ($data['options'][$key] ?? '');
            if (isset($choices[$key])) {
                abort_unless($value === '' || array_key_exists($value, $choices[$key]['values']), 422);
                $options[$key] = $value !== '' ? $value : (string) array_key_first($choices[$key]['values']);
            }
        }

        try {
            $service = $billing->start($client, $product, $options, strtolower((string) ($data['hostname'] ?? '')));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('client.services.show', $service)->with('success', __('viyocloud::site.hourly_order.started'));
    }

    public function usage(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $row = app(HourlyBilling::class)->row($service);
        abort_unless($row, 404);

        $charges = DB::table('viyo_hourly_charges')->where('service_id', $service->id)->orderByDesc('period_start')->paginate(48);

        return view('viyocloud::client.hourly-usage', compact('service', 'row', 'charges'));
    }

    public function destroy(Request $request, Service $service, HourlyBilling $billing)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        abort_unless(HourlyBilling::isHourly($service), 404);
        $request->validate(['confirm' => ['accepted']]);

        $result = $billing->destroy($service);

        return back()->with(($result['success'] ?? false) ? 'success' : 'error', ($result['success'] ?? false)
            ? __('viyocloud::site.hourly_order.deleted')
            : __('viyocloud::site.hourly_order.delete_pending', ['reason' => $result['message'] ?? '']));
    }

    /**
     * The order choices a product offers: its configurable options named
     * "key|Title" with choices "value|Label" (the same ones the cart shows).
     *
     * @return array<string, array{title: string, values: array<string, string>}>
     */
    public static function choices(Product $product): array
    {
        $out = [];
        foreach ($product->configOptionGroups()->with('options.subs')->get() as $group) {
            foreach ($group->options as $option) {
                [$key, $title] = array_pad(explode('|', (string) $option->option_name, 2), 2, '');
                $key = strtolower(trim($key));
                if ($option->hidden || ! in_array($key, self::KEYS, true) || isset($out[$key])) {
                    continue;
                }
                $values = [];
                // A hidden choice stays on the orders that made it, but is not offered.
                foreach ($option->subs->where('hidden', false)->sortBy('sort_order') as $sub) {
                    [$value, $label] = array_pad(explode('|', (string) $sub->option_name, 2), 2, '');
                    $values[trim($value)] = trim($label) !== '' ? trim($label) : trim($value);
                }
                if ($values !== []) {
                    $out[$key] = ['title' => trim($title) !== '' ? trim($title) : $key, 'values' => $values];
                }
            }
        }

        return $out;
    }
}
