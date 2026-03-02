<?php

namespace STS\Services;

use STS\Models\User;
use Illuminate\Support\Facades\Http;

class UserEditablePropertiesService
{
    /**
     * Get properties that are NEVER editable by anyone (including admins).
     */
    public function getForbiddenProperties(): array
    {
        return config('carpoolear.user_edit_properties.forbidden', ['is_admin']);
    }

    /**
     * Get properties that trigger log + Slack when a non-admin attempts to edit them.
     */
    public function getFlaggedProperties(): array
    {
        return config('carpoolear.user_edit_properties.flagged', []);
    }

    /**
     * Get properties editable by regular users (self-edit).
     */
    public function getAllowedProperties(): array
    {
        return config('carpoolear.user_edit_properties.allowed', []);
    }

    /**
     * Get additional properties editable only by admin.
     */
    public function getAdminAllowedProperties(): array
    {
        return config('carpoolear.user_edit_properties.admin_allowed', []);
    }

    /**
     * Get allowlist for changeBooleanProperty (boolean properties only).
     */
    public function getChangeBooleanAllowedProperties(): array
    {
        return [
            'emails_notifications',
            'do_not_alert_request_seat',
            'do_not_alert_accept_passenger',
            'do_not_alert_pending_rates',
            'do_not_alert_pricing',
            'autoaccept_requests',
        ];
    }

    /**
     * Check if a property is allowed for the given role.
     */
    public function isPropertyAllowed(string $property, bool $isAdmin): bool
    {
        $forbidden = $this->getForbiddenProperties();
        if (in_array($property, $forbidden)) {
            return false;
        }

        $allowed = $this->getAllowedProperties();
        if (in_array($property, $allowed)) {
            return true;
        }

        if ($isAdmin) {
            $adminAllowed = $this->getAdminAllowedProperties();
            return in_array($property, $adminAllowed);
        }

        return false;
    }

    /**
     * Filter data to only include editable keys for the given role.
     * Returns the filtered array. Does not modify the input.
     */
    public function filterForUser(array $data, bool $isAdmin): array
    {
        $filtered = [];
        $forbidden = $this->getForbiddenProperties();
        $allowed = $this->getAllowedProperties();
        $adminAllowed = $isAdmin ? $this->getAdminAllowedProperties() : [];
        $editableKeys = array_merge($allowed, $adminAllowed);

        foreach ($data as $key => $value) {
            if (in_array($key, $forbidden)) {
                continue;
            }
            if (in_array($key, $editableKeys)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Get requested keys that were blocked (flagged/forbidden) and where the requested value
     * actually differs from the current user (so we only alert when they're trying to change something).
     */
    public function getBlockedFlaggedPropertiesThatDiffer(User $user, array $requestData, array $filteredData, bool $isAdmin): array
    {
        if ($isAdmin) {
            return [];
        }

        $forbidden = $this->getForbiddenProperties();
        $flagged = $this->getFlaggedProperties();
        $blockedKeys = array_unique(array_merge($forbidden, $flagged));
        $actuallyChanged = [];

        foreach ($blockedKeys as $key) {
            if (!array_key_exists($key, $requestData)) {
                continue;
            }
            if (array_key_exists($key, $filteredData)) {
                continue; // they're allowed to edit it
            }
            $requested = $requestData[$key];
            $current = $user->getAttribute($key);
            if ($this->valueActuallyDiffers($key, $requested, $current)) {
                $actuallyChanged[] = $key;
            }
        }

        return $actuallyChanged;
    }

    /**
     * Compare requested value vs current user value (normalize booleans, strings, etc.).
     */
    private function valueActuallyDiffers(string $key, $requested, $current): bool
    {
        $booleanAttrs = ['banned', 'active', 'is_admin', 'emails_notifications', 'identity_validated',
            'driver_is_verified', 'do_not_alert_request_seat', 'do_not_alert_accept_passenger',
            'do_not_alert_pending_rates', 'do_not_alert_pricing', 'autoaccept_requests'];
        if (in_array($key, $booleanAttrs)) {
            $reqBool = filter_var($requested, FILTER_VALIDATE_BOOLEAN);
            $curBool = (bool) $current;
            return $reqBool !== $curBool;
        }
        if (is_string($requested)) {
            $requested = trim($requested);
        }
        if (is_string($current)) {
            $current = trim((string) $current);
        }
        if ($current instanceof \DateTimeInterface) {
            $current = $current->format('Y-m-d');
        }
        if (is_string($requested) && preg_match('/^\d{4}-\d{2}-\d{2}/', $requested)) {
            $requested = substr($requested, 0, 10);
        }
        return $requested != $current;
    }

    /**
     * Send Slack alert for forbidden profile edit attempt.
     */
    public function sendFlaggedPropertyAlert(User $user, array $bannedProperties): void
    {
        $webhookUrl = config('services.slack.forbidden_edit_webhook_url');
        if (empty($webhookUrl)) {
            return;
        }

        $bannedPropertiesStr = implode(', ', $bannedProperties);
        $adminLinkToProfile = rtrim(config('carpoolear.frontend_url'), '/') . '#/profile/' . $user->id;
        $slackMessage = "Edición prohibida de perfil: {$bannedPropertiesStr} en usuario ID {$user->id}. Link al perfil: {$adminLinkToProfile}";

        try {
            $response = Http::timeout(3)->post($webhookUrl, ['text' => $slackMessage]);
            if (!$response->successful()) {
                \Log::warning('Slack forbidden edit webhook failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Slack forbidden edit webhook failed', ['error' => $e->getMessage()]);
        }
    }
}
