<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Onboarding\RegisteredChurch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The reply to starting a church: the new church, its first admin and where to sign in.
 *
 * @mixin RegisteredChurch
 */
class ChurchRegisteredResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Shapes the new church for the API. The church lives on its own subdomain of the host the request came to.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $url = $request->getScheme().'://'.$this->church->slug.'.'.$request->getHttpHost();

        return [
            'church' => [
                'id' => $this->church->id,
                'name' => $this->church->name,
                'slug' => $this->church->slug,
                'member_number_prefix' => $this->church->member_number_prefix,
                'url' => $url,
            ],
            'admin' => ['id' => $this->admin->id, 'name' => $this->admin->name],
            'login_url' => $url.'/login',
        ];
    }
}
