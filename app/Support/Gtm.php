<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Ported from the old Next.js app's src/lib/gtm.ts getActiveGtmId(): returns
 * the configured GTM container id, but only when it's non-empty AND the
 * ENABLE_GTM kill switch isn't explicitly set to false (so a dev/staging
 * environment never fires production analytics events even if a gtm_id
 * happens to be saved in the DB).
 */
class Gtm
{
    public static function activeId(): ?string
    {
        if (config('services.gtm.enabled') === false) {
            return null;
        }

        $id = trim((string) (SiteSetting::where('setting_key', 'gtm_id')->value('setting_value') ?? ''));

        return $id !== '' ? $id : null;
    }
}
