<?php

namespace App\Http\Requests\Notification;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'post_moderation_database' => ['required', 'boolean'],
            'post_moderation_mail' => ['required', 'boolean'],
            'post_moderation_browser' => ['required', 'boolean'],
            'scene_new_post_database' => ['required', 'boolean'],
            'scene_new_post_mail' => ['required', 'boolean'],
            'scene_new_post_browser' => ['required', 'boolean'],
            'campaign_invitation_database' => ['required', 'boolean'],
            'campaign_invitation_mail' => ['required', 'boolean'],
            'campaign_invitation_browser' => ['required', 'boolean'],
            'character_mention_database' => ['required', 'boolean'],
            'character_mention_mail' => ['required', 'boolean'],
            'offline_queue_opt_out' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'post_moderation_database' => $this->boolean('post_moderation_database'),
            'post_moderation_mail' => $this->boolean('post_moderation_mail'),
            'post_moderation_browser' => $this->boolean('post_moderation_browser'),
            'scene_new_post_database' => $this->boolean('scene_new_post_database'),
            'scene_new_post_mail' => $this->boolean('scene_new_post_mail'),
            'scene_new_post_browser' => $this->boolean('scene_new_post_browser'),
            'campaign_invitation_database' => $this->boolean('campaign_invitation_database'),
            'campaign_invitation_mail' => $this->boolean('campaign_invitation_mail'),
            'campaign_invitation_browser' => $this->boolean('campaign_invitation_browser'),
            'character_mention_database' => $this->boolean('character_mention_database'),
            'character_mention_mail' => $this->boolean('character_mention_mail'),
            'offline_queue_opt_out' => $this->boolean('offline_queue_opt_out'),
        ]);
    }

    public function offlineQueueEnabled(): bool
    {
        return ! (bool) $this->validated('offline_queue_opt_out', false);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function preferences(): array
    {
        $defaults = User::NOTIFICATION_PREFERENCE_DEFAULTS;

        return [
            'post_moderation' => [
                'database' => (bool) (
                    $this->validated('post_moderation_database', data_get($defaults, 'post_moderation.database', true))
                    || $this->validated('post_moderation_browser', data_get($defaults, 'post_moderation.browser', false))
                ),
                'mail' => (bool) $this->validated('post_moderation_mail', data_get($defaults, 'post_moderation.mail', false)),
                'browser' => (bool) $this->validated('post_moderation_browser', data_get($defaults, 'post_moderation.browser', false)),
            ],
            'scene_new_post' => [
                'database' => (bool) (
                    $this->validated('scene_new_post_database', data_get($defaults, 'scene_new_post.database', true))
                    || $this->validated('scene_new_post_browser', data_get($defaults, 'scene_new_post.browser', false))
                ),
                'mail' => (bool) $this->validated('scene_new_post_mail', data_get($defaults, 'scene_new_post.mail', false)),
                'browser' => (bool) $this->validated('scene_new_post_browser', data_get($defaults, 'scene_new_post.browser', false)),
            ],
            'campaign_invitation' => [
                'database' => (bool) (
                    $this->validated('campaign_invitation_database', data_get($defaults, 'campaign_invitation.database', true))
                    || $this->validated('campaign_invitation_browser', data_get($defaults, 'campaign_invitation.browser', false))
                ),
                'mail' => (bool) $this->validated('campaign_invitation_mail', data_get($defaults, 'campaign_invitation.mail', false)),
                'browser' => (bool) $this->validated('campaign_invitation_browser', data_get($defaults, 'campaign_invitation.browser', false)),
            ],
            'character_mention' => [
                'database' => (bool) $this->validated('character_mention_database', data_get($defaults, 'character_mention.database', true)),
                'mail' => (bool) $this->validated('character_mention_mail', data_get($defaults, 'character_mention.mail', false)),
            ],
        ];
    }
}
