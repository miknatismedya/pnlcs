<?php

namespace Modules\Addons\ViyoCloud\Support;

use App\Models\Setting;
use App\Services\AddonManager;
use Modules\Addons\ViyoCloud\ViyoCloudModule;

/** Contact details for the site: the addon's settings, then the shop's own. */
class Site
{
    /** @return array{phone: string, phone_href: string, whatsapp: ?string, email: string} */
    public static function contact(): array
    {
        $cfg = rescue(fn () => app(AddonManager::class)->settings(ViyoCloudModule::NAME), [], false);

        $phone = trim((string) ($cfg['phone'] ?? '')) ?: trim((string) Setting::get('Phone', ''));
        $wa = preg_replace('/\D+/', '', (string) ($cfg['whatsapp'] ?? ''));
        $email = trim((string) ($cfg['email'] ?? ''))
            ?: trim((string) Setting::get('whitelabel_support_email', ''))
            ?: trim((string) Setting::get('Email', ''));

        return [
            'phone' => $phone,
            'phone_href' => $phone !== '' ? 'tel:'.preg_replace('/[^\d+]/', '', $phone) : '',
            'whatsapp' => $wa !== '' ? 'https://wa.me/'.$wa : null,
            'email' => $email,
        ];
    }
}
