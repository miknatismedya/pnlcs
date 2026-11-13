<?php

namespace Modules\Addons\ViyoCloud;

use App\Contracts\AddonModuleInterface;
use Illuminate\Http\Request;
use Modules\Addons\ViyoCloud\Support\Catalog;

/**
 * Viyo Cloud: the public site (cloud, hourly cloud, FTP storage, apps,
 * infrastructure pages) and its translations. Everything lives in this
 * folder, so a PNLCS update never touches it.
 */
class ViyoCloudModule implements AddonModuleInterface
{
    public const NAME = 'viyocloud';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDisplayName(): string
    {
        return 'Viyo Cloud';
    }

    public function getDescription(): string
    {
        return 'Viyo Cloud site pages: cloud servers, hourly cloud, FTP storage, one-click apps.';
    }

    public function getVersion(): string
    {
        return '0.1.0';
    }

    public function getAuthor(): string
    {
        return 'Viyo Cloud';
    }

    public function activate(): array
    {
        return ['success' => true, 'message' => 'Viyo Cloud activated'];
    }

    public function deactivate(): array
    {
        return ['success' => true, 'message' => 'Viyo Cloud deactivated'];
    }

    public function config(): array
    {
        return [
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'default' => ''],
            ['name' => 'whatsapp', 'label' => 'WhatsApp number (international, digits only)', 'type' => 'text', 'default' => ''],
            ['name' => 'email', 'label' => 'Sales e-mail', 'type' => 'text', 'default' => ''],
        ];
    }

    public function sidebar(): array
    {
        return [
            ['label' => 'Viyo Cloud', 'url' => '/admin/config/addons/modules/'.self::NAME],
        ];
    }

    public function upgrade(string $fromVersion): array
    {
        return ['success' => true, 'message' => 'Upgraded from '.e($fromVersion)];
    }

    public function output(Request $request): string
    {
        $rows = '';
        foreach (Catalog::GROUPS as $key => $slug) {
            $count = Catalog::plans($key)->count();
            $rows .= '<tr><td>'.e(__('viyocloud::site.lines.'.$key.'.title')).'</td><td><code>'.e($slug).'</code></td><td>'.$count.'</td></tr>';
        }

        return '<p>'.e(__('viyocloud::site.admin.intro')).'</p>'
            .'<table class="table"><thead><tr><th>'.e(__('viyocloud::site.admin.line')).'</th><th>'.e(__('viyocloud::site.admin.group_slug')).'</th><th>'.e(__('viyocloud::site.admin.products')).'</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }
}
