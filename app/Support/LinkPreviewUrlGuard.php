<?php

namespace App\Support;

final class LinkPreviewUrlGuard
{
    public static function isAllowed(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '') {
            return false;
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        $hostForIpCheck = self::stripIpv6Brackets($host);

        if (filter_var($hostForIpCheck, FILTER_VALIDATE_IP)) {
            return ! self::isPrivateIp($hostForIpCheck);
        }

        $ips = @gethostbynamel($host) ?: [];

        foreach ($ips as $ip) {
            if (self::isPrivateIp($ip)) {
                return false;
            }
        }

        $aaaaRecords = @dns_get_record($host, DNS_AAAA) ?: [];

        foreach ($aaaaRecords as $record) {
            $ip = (string) ($record['ipv6'] ?? '');

            if ($ip !== '' && self::isPrivateIp($ip)) {
                return false;
            }
        }

        return true;
    }

    public static function isAllowedImageUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        return self::isAllowed($url);
    }

    private static function stripIpv6Brackets(string $host): string
    {
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return substr($host, 1, -1);
        }

        return $host;
    }

    private static function isPrivateIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ! filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return ! filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );
        }

        return true;
    }
}
