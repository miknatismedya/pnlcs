<?php

namespace Modules\Addons\ViyoCloud\Http;

use App\Http\Controllers\Controller;
use Modules\Addons\ViyoCloud\Support\Catalog;

class SiteController extends Controller
{
    public function cloud()
    {
        return view('viyocloud::pages.cloud', ['plans' => Catalog::plans('cloud')]);
    }

    public function hourly()
    {
        return view('viyocloud::pages.hourly', ['plans' => Catalog::plans('cloud')->filter(fn ($p) => $p['hourly_raw'])->values()]);
    }

    public function ftp()
    {
        return view('viyocloud::pages.ftp', ['plans' => Catalog::plans('ftp')]);
    }

    public function apps()
    {
        return view('viyocloud::pages.apps', ['plans' => Catalog::plans('apps')]);
    }

    public function infrastructure()
    {
        return view('viyocloud::pages.infrastructure');
    }
}
