<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Viyo Cloud's homepage, as ordinary homepage sections: switched on or off,
 * reordered and reworded from Setup -> Appearance like the core ones.
 *
 * - Adds the Viyo sections (their views ship with this addon, under
 *   views/site/sections, and the active theme may restyle them).
 * - Switches off the core sections for products Viyo Cloud does not sell
 *   (domains, shared hosting, VPS cards, sample testimonials and figures).
 * - Empties the core's sample wording on the sections it keeps (hero, FAQ,
 *   call to action, footer description): it describes Panelica hosting in
 *   English. Empty means "the translated default", as the core's own
 *   docker-apps migration does, so each language reads right until the
 *   operator writes their own.
 */
return new class extends Migration
{
    /** slug => [title, editable keys (key => type)] */
    private const SECTIONS = [
        'viyo-lines' => ['Viyo: Product lines', ['title' => 'text', 'subtitle' => 'text']],
        'viyo-plans' => ['Viyo: Cloud plans', ['title' => 'text', 'subtitle' => 'text']],
        'viyo-features' => ['Viyo: Included features', ['title' => 'text', 'subtitle' => 'text']],
        'viyo-hourly' => ['Viyo: Hourly cloud', ['title' => 'text', 'subtitle' => 'text']],
        'viyo-apps' => ['Viyo: One-click apps', ['title' => 'text', 'subtitle' => 'text']],
    ];

    /** The homepage order once installed. */
    private const ORDER = ['hero', 'viyo-lines', 'viyo-plans', 'viyo-features', 'viyo-hourly', 'viyo-apps', 'faq', 'cta-banner'];

    private const OFF = ['domain-search', 'promo-cards', 'hosting-plans', 'vps-plans', 'docker-apps', 'infrastructure', 'stats', 'testimonials'];

    private const CLEARED = ['hero' => null, 'faq' => null, 'cta-banner' => null, 'footer' => ['description']];

    public function up(): void
    {
        $now = now();

        foreach (self::SECTIONS as $slug => [$title, $keys]) {
            if (! DB::table('homepage_sections')->where('slug', $slug)->exists()) {
                DB::table('homepage_sections')->insert([
                    'slug' => $slug, 'title' => $title, 'sort_order' => 0,
                    'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            foreach ($keys as $key => $type) {
                if (! DB::table('homepage_content')->where('section_slug', $slug)->where('content_key', $key)->exists()) {
                    DB::table('homepage_content')->insert([
                        'section_slug' => $slug, 'content_key' => $key, 'content_value' => '',
                        'content_type' => $type, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        foreach (self::ORDER as $i => $slug) {
            DB::table('homepage_sections')->where('slug', $slug)->update(['sort_order' => $i + 1, 'is_enabled' => true]);
        }
        DB::table('homepage_sections')->whereIn('slug', self::OFF)->update(['is_enabled' => false]);
        DB::table('homepage_sections')->where('slug', 'footer')->update(['sort_order' => 99]);

        foreach (self::CLEARED as $slug => $keys) {
            $q = DB::table('homepage_content')->where('section_slug', $slug)->where('content_type', '!=', 'json');
            if ($keys !== null) {
                $q->whereIn('content_key', $keys);
            }
            $q->update(['content_value' => '']);
            // A JSON list (the FAQ items) is emptied to an empty list, not to "".
            DB::table('homepage_content')->where('section_slug', $slug)->where('content_type', 'json')
                ->when($keys !== null, fn ($q) => $q->whereIn('content_key', $keys))
                ->update(['content_value' => '[]']);
        }
    }

    public function down(): void
    {
        DB::table('homepage_content')->whereIn('section_slug', array_keys(self::SECTIONS))->delete();
        DB::table('homepage_sections')->whereIn('slug', array_keys(self::SECTIONS))->delete();
        DB::table('homepage_sections')->whereIn('slug', self::OFF)->update(['is_enabled' => true]);
    }
};
