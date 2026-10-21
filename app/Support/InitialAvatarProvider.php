<?php

namespace App\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The round avatar in the admin's top-right menu: the first letter of the signed-in admin's name in the
 * admin's main colour, drawn here as an inline SVG — nothing is fetched from another website.
 */
class InitialAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $name = trim((string) Filament::getNameForDefaultAvatar($record));
        $letter = htmlspecialchars(mb_strtoupper(mb_substr($name, 0, 1)) ?: '?', ENT_QUOTES);

        try {
            $color = AdminTheme::primary();
        } catch (\Throwable) {
            $color = AdminTheme::DEFAULT_PRIMARY;
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="'.$color.'"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" font-family="Arial, sans-serif" font-size="30" font-weight="700" fill="#fff">'.$letter.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
