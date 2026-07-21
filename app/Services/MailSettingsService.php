<?php

namespace App\Services;

use App\Models\MailSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class MailSettingsService
{
    private const CACHE_KEY = 'mail_settings_active';

    public function activeSettings(): ?array
    {
        if (!$this->tableAvailable()) {
            return null;
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function () {
            $setting = MailSetting::query()->latest('id')->first();
            if (!$setting) {
                return null;
            }

            return [
                'host' => (string) ($setting->host ?? ''),
                'port' => (int) ($setting->port ?? 0),
                'username' => $this->safeEncryptedValue($setting, 'username'),
                'password' => $this->safeEncryptedValue($setting, 'password'),
                'encryption' => (string) ($setting->encryption ?? ''),
                'from_address' => $this->safeEncryptedValue($setting, 'from_address'),
                'from_name' => (string) ($setting->from_name ?? ''),
                'ehlo_domain' => (string) ($setting->ehlo_domain ?? ''),
            ];
        });
    }

    public function applyRuntimeConfig(): void
    {
        $active = $this->activeSettings();
        if (!$active) {
            return;
        }

        config(['mail.default' => 'smtp']);

        if ($active['host'] !== '') {
            config(['mail.mailers.smtp.host' => $active['host']]);
        }
        if ($active['port'] > 0) {
            config(['mail.mailers.smtp.port' => $active['port']]);
        }
        if ($active['username'] !== '') {
            config(['mail.mailers.smtp.username' => $active['username']]);
        }
        if ($active['password'] !== '') {
            config(['mail.mailers.smtp.password' => $active['password']]);
        }
        if ($active['encryption'] !== '') {
            config(['mail.mailers.smtp.encryption' => $active['encryption']]);
        }
        if ($active['ehlo_domain'] !== '') {
            config(['mail.mailers.smtp.local_domain' => $active['ehlo_domain']]);
        }
        if ($active['from_address'] !== '') {
            config(['mail.from.address' => $active['from_address']]);
        }
        if ($active['from_name'] !== '') {
            config(['mail.from.name' => $active['from_name']]);
        }
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function safeEncryptedValue(MailSetting $setting, string $key): string
    {
        $raw = $setting->getRawOriginal($key);
        if (blank($raw)) {
            return '';
        }

        try {
            return (string) Crypt::decryptString((string) $raw);
        } catch (DecryptException) {
            return '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function tableAvailable(): bool
    {
        try {
            return Schema::hasTable('mail_settings');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
