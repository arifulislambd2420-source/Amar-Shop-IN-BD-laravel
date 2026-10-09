<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches an image from a link an admin pasted, safely, into a temp file
 * (the caller then runs it through ImageUpload::storeFromPath, which checks
 * the real file type and size).
 *
 * The server makes this request, so it must not be usable to reach the
 * server's own network (SSRF):
 *  - only http / https, only ports 80 and 443, no credentials in the URL;
 *  - the host must resolve, and every address it resolves to must be public
 *    (no loopback, private, link-local or reserved ranges);
 *  - the request is pinned to the address that was checked (so the name
 *    cannot resolve differently a moment later);
 *  - redirects are followed by hand (max 3), each hop checked the same way;
 *  - the download is cut off at the upload size limit.
 */
class RemoteImage
{
    private const MAX_REDIRECTS = 3;

    /**
     * @return string path of a temp file holding the downloaded bytes (caller deletes it)
     *
     * @throws RuntimeException with a Bangla message fit to show the admin
     */
    public static function download(string $url): string
    {
        $url = trim($url);
        $limit = (int) config('media.max_kb') * 1024;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$host, $ip] = self::checkedTarget($url);

            try {
                $response = Http::timeout(15)
                    ->withHeaders(['Accept' => 'image/*', 'User-Agent' => 'Mozilla/5.0 (compatible; ShopImageFetch)'])
                    ->withOptions([
                        'allow_redirects' => false,
                        'on_headers' => function ($response) use ($limit) {
                            if ((int) $response->getHeaderLine('Content-Length') > $limit) {
                                throw new RuntimeException('ছবিটি অনেক বড় — সর্বোচ্চ '.round($limit / 1048576, 1).' MB।');
                            }
                        },
                        'progress' => function ($total, $downloaded) use ($limit) {
                            if ($downloaded > $limit) {
                                throw new RuntimeException('ছবিটি অনেক বড় — সর্বোচ্চ '.round($limit / 1048576, 1).' MB।');
                            }
                        },
                        // Connect to the address we checked, whatever DNS says now.
                        'curl' => defined('CURLOPT_RESOLVE') ? [CURLOPT_RESOLVE => [$host.':80:'.$ip, $host.':443:'.$ip]] : [],
                    ])
                    ->get($url);
            } catch (RuntimeException $e) {
                throw $e;
            } catch (ConnectionException) {
                throw new RuntimeException('লিংকটি খোলা যায়নি। লিংক ঠিক আছে কি না দেখুন।');
            } catch (\Throwable $e) {
                if ($e->getPrevious() instanceof RuntimeException) {
                    throw $e->getPrevious();
                }

                throw new RuntimeException('লিংক থেকে ছবি আনা যায়নি।');
            }

            if ($response->redirect()) {
                $next = $response->header('Location');

                if (! $next) {
                    break;
                }

                $url = self::absolute($url, $next);

                continue;
            }

            if (! $response->successful()) {
                throw new RuntimeException('লিংক থেকে ছবি পাওয়া যায়নি (HTTP '.$response->status().')।');
            }

            $body = $response->body();

            if (strlen($body) > $limit) {
                throw new RuntimeException('ছবিটি অনেক বড় — সর্বোচ্চ '.round($limit / 1048576, 1).' MB।');
            }

            $tmp = tempnam(sys_get_temp_dir(), 'img');
            file_put_contents($tmp, $body);

            return $tmp;
        }

        throw new RuntimeException('লিংকটি অনেকবার অন্য জায়গায় পাঠাচ্ছে — সরাসরি ছবির লিংক দিন।');
    }

    /**
     * Validate the URL and resolve it to one public IP.
     *
     * @return array{0: string, 1: string} [host, ip]
     *
     * @throws RuntimeException
     */
    public static function checkedTarget(string $url): array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('সঠিক ছবির লিংক দিন (https://… দিয়ে শুরু)।');
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new RuntimeException('শুধু http বা https লিংক চলবে।');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('এই লিংকটি ব্যবহার করা যাবে না।');
        }

        $port = $parts['port'] ?? null;

        if ($port !== null && ! in_array((int) $port, [80, 443], true)) {
            throw new RuntimeException('এই লিংকটি ব্যবহার করা যাবে না।');
        }

        $host = trim(strtolower($parts['host']), '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);

        if ($ips === []) {
            throw new RuntimeException('লিংকের ঠিকানাটি পাওয়া যায়নি।');
        }

        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                throw new RuntimeException('এই লিংকটি ব্যবহার করা যাবে না।');
            }
        }

        return [$host, $ips[0]];
    }

    public static function isPublic(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        $ips = [];

        foreach (@dns_get_record($host, DNS_A) ?: [] as $record) {
            $ips[] = $record['ip'];
        }

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ipv6'];
        }

        if ($ips === []) {
            $v4 = gethostbyname($host);

            if ($v4 !== $host) {
                $ips[] = $v4;
            }
        }

        return array_values(array_unique($ips));
    }

    private static function absolute(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return $parts['scheme'].':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        return $origin.rtrim(dirname($parts['path'] ?? '/'), '/').'/'.$location;
    }
}
