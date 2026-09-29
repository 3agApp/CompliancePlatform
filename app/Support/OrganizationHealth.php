<?php

namespace App\Support;

use App\Data\OrganizationPermissions;
use App\Models\Organization;

/**
 * The short list of things on the organization settings page that someone
 * has to act on. Worked out here rather than on the page so that "needs
 * attention" means the same thing everywhere it is shown.
 */
class OrganizationHealth
{
    public function __construct(
        private readonly Organization $organization,
        private readonly OrganizationPermissions $permissions,
    ) {}

    /**
     * The things someone should act on, most urgent first.
     *
     * @return array<int, array{key: string, tone: string, title: string, description: string, target: string}>
     */
    public function attention(): array
    {
        $items = [];

        $setting = $this->permissions->canManageAiProvider ? $this->organization->aiSetting : null;

        // The key is encrypted with the application key, so rotating that
        // leaves a saved key unreadable and every guess quietly failing.
        if ($setting !== null && ! rescue(fn () => filled($setting->api_key), false, report: false)) {
            $items[] = [
                'key' => 'ai-key-unreadable',
                'tone' => 'danger',
                'title' => __('The AI provider key can no longer be read'),
                'description' => __('Enter the API key again so document kinds are guessed on upload.'),
                'target' => 'ai-provider',
            ];
        }

        if ($this->permissions->canCreateInvitation || $this->permissions->canCancelInvitation) {
            $expired = $this->organization->invitations()
                ->whereNull('accepted_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->count();

            if ($expired > 0) {
                $items[] = [
                    'key' => 'expired-invitations',
                    'tone' => 'warning',
                    'title' => trans_choice('{1} An invitation has expired|[2,*] :count invitations have expired', $expired),
                    'description' => __('Resend it so the person can still join, or cancel it.'),
                    'target' => 'invitations',
                ];
            }
        }

        return $items;
    }
}
