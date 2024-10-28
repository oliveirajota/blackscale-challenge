<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RegisterBotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $stoken;
    protected $cookieJar;
    protected $lastResponseBody;

    public function __construct()
    {
        $this->email = $this->generateUniqueEmail();
        $this->cookieJar = new CookieJar();
    }

    /**
     * Handles the main registration flow
     * Initializes HTTP client, starts registration, and submits form
     */
    public function handle()
    {
        $client = new Client([
            'cookies' => $this->cookieJar,
            'allow_redirects' => true,
        ]);

        $this->startRegistration($client);
        $this->simulateHumanDelay(2, 4);
        $this->submitRegistrationForm($client);
        // $this->bypassRecaptcha($client);
        // $this->verifyEmail($client);
        // $this->solveMathProblem($client);
        // $this->collectSuccessToken($client);

        Log::info('Bot registration process completed');
    }

    /**
     * Loads the registration page and extracts necessary tokens
     * @param Client $client Guzzle HTTP client
     */
    protected function startRegistration(Client $client)
    {
        try {
            $response = $client->get('https://challenge.blackscale.media/register.php');
            $html = $response->getBody()->getContents();

            Log::debug('Registration page HTML:', ['html' => $html]);

            // Extract all form fields
            preg_match_all('/<input[^>]*name=["\']([^"\']+)["\'][^>]*>/', $html, $matches);
            $formFields = $matches[1];
            Log::info('Found form fields:', ['fields' => $formFields]);

            // Extract stoken
            if (preg_match('/<input[^>]*name=["\']stoken["\'][^>]*value=["\']([^"\']+)["\']/', $html, $matches)) {
                $this->stoken = $matches[1];
                Log::info('Successfully extracted stoken', ['stoken' => $this->stoken]);
            } else {
                Log::error('Failed to extract stoken. HTML structure:', ['html' => $html]);
            }

            $this->logCookies('After loading registration page');

            // Ensure PHPSESSID and ctoken are set
            $this->ensureRequiredCookies($response);

        } catch (GuzzleException $e) {
            Log::error('Failed to start registration: ' . $e->getMessage());
        }
    }

    /**
     * Submits the registration form with required data
     * Handles response and checks for Error:003
     * @param Client $client Guzzle HTTP client
     */
    protected function submitRegistrationForm(Client $client)
    {
        try {
            $formData = [
                'stoken' => $this->stoken,
                'fullname' => $this->generateRandomName(),
                'email' => $this->generateRandomEmail(),
                'request_signature' => base64_encode($this->email),
            ];

            // Simulate typing each field
            foreach ($formData as $field => $value) {
                $this->simulateHumanDelay(0.5, 1.5);  // 0.5 to 1.5 seconds per field
            }

            // Simulate a pause before clicking submit
            $this->simulateHumanDelay(1, 2);

            // Submit registration form to captcha_bot.php endpoint
            $response = $client->post('https://challenge.blackscale.media/captcha_bot.php', [
                'form_params' => $formData,
                'headers' => $this->getCommonHeaders(),
            ]);

            // Store response body and log it
            $this->lastResponseBody = $response->getBody()->getContents();
            Log::info('Registration form response:', ['response' => $this->lastResponseBody]);

            // Log cookies after form submission
            $this->logCookies('After submitting registration form');

            // Check response for different scenarios
            if (strpos($this->lastResponseBody, 'Invalid registration details. Error:003') !== false) {
                // Error:003
                Log::error('Registration failed. Error:003 received. Response details:', [
                    'status_code' => $response->getStatusCode(),
                    'headers' => $response->getHeaders(),
                    'body' => $this->lastResponseBody,
                ]);
            } elseif (strpos($this->lastResponseBody, 'Stage 2 - Bot CAPTCHA') !== false) {
                // Successfully moved to CAPTCHA stage
                Log::info('Successfully reached Stage 2 - Bot CAPTCHA');
            } else {
                // Unexpected response received
                Log::warning('Unexpected response received:', [
                    'status_code' => $response->getStatusCode(),
                    'headers' => $response->getHeaders(),
                    'body' => $this->lastResponseBody,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Exception in submitRegistrationForm', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Ensures required cookies (PHPSESSID, ctoken, twk_uuid) are present
     * Adds missing cookies if necessary
     * @param Response $response HTTP response object
     */
    protected function ensureRequiredCookies($response)
    {
        // Begin cookie handling
        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $phpSessionId = null;
        $ctoken = null;
        $twkUuid = null;

        // Extract cookies from response headers
        foreach ($setCookieHeaders as $cookieString) {
            if (strpos($cookieString, 'PHPSESSID') !== false) {
                preg_match('/PHPSESSID=([^;]+)/', $cookieString, $matches);
                $phpSessionId = $matches[1] ?? null;
            } elseif (strpos($cookieString, 'ctoken') !== false) {
                preg_match('/ctoken=([^;]+)/', $cookieString, $matches);
                $ctoken = $matches[1] ?? null;
            } elseif (strpos($cookieString, 'twk_uuid_61a0fb3053b398095a6640c4') !== false) {
                preg_match('/twk_uuid_61a0fb3053b398095a6640c4=([^;]+)/', $cookieString, $matches);
                $twkUuid = $matches[1] ?? null;
            }
        }

        // Check for missing PHPSESSID
        if (!$phpSessionId) {
            Log::warning('PHPSESSID not found in response cookies');
        }

        // Handle missing ctoken
        if (!$ctoken) {
            Log::warning('ctoken not found in response cookies');
            $ctoken = $this->generateCtoken();
            $this->cookieJar->setCookie(new \GuzzleHttp\Cookie\SetCookie([
                'Name' => 'ctoken',
                'Value' => $ctoken,
                'Domain' => 'challenge.blackscale.media',
                'Path' => '/',
                'Expires' => time() + 3600,
                'Secure' => true,
                'HttpOnly' => true
            ]));
            Log::info('Added new ctoken to cookie jar', ['ctoken' => $ctoken]);
        }

        // Handle missing twk_uuid
        if (!$twkUuid) {
            Log::warning('twk_uuid not found in response cookies');
            $twkUuidData = [
                "uuid" => "1.2BiqepvTYlw8iT0IlpE7HTJRFXeyn9qKFtpKjfWCgXijIQpEcycDFAPdqrhnXsznqfLURqmwJebs29VgcxeOIJ501joIUyiL2zSUvBZwuzjy917f9ZnNEB0mrMK",
                "version" => 3,
                "domain" => "blackscale.media",
                "ts" => time()
            ];
            $twkUuid = urlencode(json_encode($twkUuidData));
            $this->cookieJar->setCookie(new \GuzzleHttp\Cookie\SetCookie([
                'Name' => 'twk_uuid_61a0fb3053b398095a6640c4',
                'Value' => $twkUuid,
                'Domain' => '.blackscale.media',
                'Path' => '/',
                'Expires' => strtotime('2025-04-22T13:17:18.000Z'),
                'Secure' => false,
                'HttpOnly' => false,
                'SameSite' => 'Lax'
            ]));
            Log::info('Added twk_uuid to cookie jar', ['twk_uuid' => $twkUuid]);
        }

        // Log status of required cookies
        Log::info('Checked required cookies', [
            'PHPSESSID' => $phpSessionId ? 'present' : 'missing',
            'ctoken' => $ctoken ? 'present' : 'missing',
            'twk_uuid' => $twkUuid ? 'present' : 'missing',
        ]);

        // Verify cookies in cookie jar
        $cookiesInJar = $this->cookieJar->toArray();
        $phpSessionIdInJar = false;
        $ctokenInJar = false;
        $twkUuidInJar = false;

        // Check each cookie in jar
        foreach ($cookiesInJar as $cookie) {
            if ($cookie['Name'] === 'PHPSESSID') {
                $phpSessionIdInJar = true;
            } elseif ($cookie['Name'] === 'ctoken') {
                $ctokenInJar = true;
            } elseif ($cookie['Name'] === 'twk_uuid_61a0fb3053b398095a6640c4') {
                $twkUuidInJar = true;
            }
        }

        // Log final cookie jar status
        Log::info('Checked cookies in jar', [
            'PHPSESSID' => $phpSessionIdInJar ? 'present' : 'missing',
            'ctoken' => $ctokenInJar ? 'present' : 'missing',
            'twk_uuid' => $twkUuidInJar ? 'present' : 'missing',
        ]);
    }

    /**
     * Logs current state of cookies for debugging
     * @param string $context Description of when cookies are being logged
     */
    protected function logCookies($context)
    {
        $cookies = [];
        foreach ($this->cookieJar->toArray() as $cookie) {
            $cookies[] = [
                'Name' => $cookie['Name'],
                'Value' => $cookie['Value'],
                'Domain' => $cookie['Domain'],
                'Path' => $cookie['Path'],
                'Max-Age' => $cookie['Max-Age'] ?? 'N/A',
                'Expires' => $cookie['Expires'] ?? 'N/A',
                'Secure' => $cookie['Secure'],
                'HttpOnly' => $cookie['HttpOnly'],
            ];
        }
        Log::info("Cookies $context:", ['cookies' => $cookies]);
    }

    /**
     * Simulates human-like delays between actions
     * @param float $min Minimum delay in seconds
     * @param float $max Maximum delay in seconds
     */
    protected function simulateHumanDelay($min, $max)
    {
        $delay = rand($min * 1000, $max * 1000) / 1000;
        Log::info("Simulating human delay: {$delay} seconds");
        sleep($delay);
    }

    /**
     * Generates a unique email for registration
     * @return string Generated email address
     */
    protected function generateUniqueEmail()
    {
        $baseEmail = 'user';
        $domain = 'example.com';
        $uniqueId = Str::random(10);
        $timestamp = now()->timestamp;

        return $baseEmail . '+' . $uniqueId . '.' . $timestamp . '@' . $domain;
    }

    /**
     * Generates a random name for registration
     * @return string Generated full name
     */
    protected function generateRandomName()
    {
        $firstNames = ['John', 'Jane', 'Michael', 'Emily', 'David', 'Sarah'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia'];
        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }

    /**
     * Generates a random email for registration
     * @return string Generated email address
     */
    protected function generateRandomEmail()
    {
        $username = strtolower(str_replace(' ', '', $this->generateRandomName()));
        $domains = ['example.com', 'test.com', 'mail.com', 'emailtest.com'];
        return $username . '+' . bin2hex(random_bytes(4)) . '@' . $domains[array_rand($domains)];
    }

    /**
     * Returns common headers to mimic browser behavior
     * @return array HTTP headers
     */
    protected function getCommonHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
            'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control' => 'max-age=0',
            'Sec-Ch-Ua' => '"Chromium";v="130", "Google Chrome";v="130", "Not?A_Brand";v="99"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"Windows"',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',
            'Origin' => 'https://challenge.blackscale.media',
            'Referer' => 'https://challenge.blackscale.media/register.php',
            'Viewport-Width' => '1920',
            'Device-Memory' => '8',
            'Dpr' => '1',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
    }

    /**
     * Generates a 10-character hexadecimal token for ctoken cookie
     * @return string Generated token
     */
    protected function generateCtoken()
    {
        return bin2hex(random_bytes(5));
    }
}
