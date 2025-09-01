<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

final class BrowsershotService
{
    /**
     * Create a new Browsershot instance with the correct configuration.
     *
     * @param  string|null  $html
     * @param  string|null  $url
     */
    public static function create($html = null, $url = null): Browsershot
    {
        $browsershot = $html
            ? Browsershot::html($html)
            : ($url
                ? Browsershot::url($url)
                : new Browsershot);

        return self::configureBrowsershot($browsershot);
    }

    /**
     * Create a Browsershot instance from HTML.
     */
    public static function html(string $html): Browsershot
    {
        return self::configureBrowsershot(Browsershot::html($html));
    }

    /**
     * Create a Browsershot instance from URL.
     */
    public static function url(string $url): Browsershot
    {
        return self::configureBrowsershot(Browsershot::url($url));
    }

    /**
     * Generate a PDF with default settings.
     *
     * @return string|bool Returns the PDF content if no output path, or true/false if saving to file
     */
    public static function generatePdf(
        string $html,
        ?string $outputPath = null,
        array $options = []
    ): bool|string {
        // Force environment variables to be set for subprocess inheritance
        // This ensures that queue jobs properly pass environment to Browsershot
        $config = config('browsershot', []);
        $chromePath = env('CHROME_PATH') ?: self::detectChromePath();
        $nodePath = env('NODE_BINARY_PATH') ?: self::detectNodePath();

        if ($chromePath) {
            putenv("CHROME_PATH=$chromePath");
            $_ENV['CHROME_PATH'] = $chromePath;
        }

        if ($nodePath) {
            putenv("NODE_BINARY_PATH=$nodePath");
            $_ENV['NODE_BINARY_PATH'] = $nodePath;

            // Set NODE_PATH to prevent npm usage and avoid syntax errors
            $nodeDir = dirname((string) $nodePath);
            $globalNodeModules = $nodeDir.'/../lib/node_modules';
            if (is_dir($globalNodeModules)) {
                putenv("NODE_PATH=$globalNodeModules");
                $_ENV['NODE_PATH'] = $globalNodeModules;
            } else {
                // Fallback: set empty NODE_PATH to prevent npm calls
                putenv('NODE_PATH=');
                $_ENV['NODE_PATH'] = '';
            }
        }

        // Use fake npm binary to prevent shell script execution errors
        putenv('NPM_BINARY_PATH=/tmp/fake-npm-bin/npm.js');
        $_ENV['NPM_BINARY_PATH'] = '/tmp/fake-npm-bin/npm.js';

        Log::info('BrowsershotService environment variables forced', [
            'chrome_path' => $chromePath,
            'node_path' => $nodePath,
            'node_path_env' => $_ENV['NODE_PATH'] ?? 'not_set',
            'fake_npm_exists' => file_exists('/tmp/fake-npm-bin/npm.js'),
        ]);

        $browsershot = self::html($html);

        // Apply PDF-specific configuration
        $pdfConfig = config('browsershot.pdf_options', []);
        $options = array_merge($pdfConfig, $options);

        if (isset($options['format'])) {
            $browsershot->format($options['format']);
        }

        if (
            isset(
                $options['margin_top'],
                $options['margin_bottom'],
                $options['margin_left'],
                $options['margin_right']
            )
        ) {
            $browsershot->margins(
                $options['margin_top'],
                $options['margin_right'],
                $options['margin_bottom'],
                $options['margin_left']
            );
        }

        if ($options['print_background'] ?? true) {
            $browsershot->printBackground();
        }

        // Support landscape orientation
        if ($options['landscape'] ?? false) {
            $browsershot->landscape();
        }

        // Support waiting for network idle
        if ($options['wait_until_network_idle'] ?? false) {
            $browsershot->waitUntilNetworkIdle();
        }

        // Support custom timeout
        if (isset($options['timeout'])) {
            $browsershot->timeout($options['timeout']);
        }

        // Chrome arguments are now applied from config during configureBrowsershot
        // This ensures they're applied early and not overridden by other method calls

        // Log the final configuration for debugging
        $config = config('browsershot', []);
        $chromePath = self::detectChromePath();
        $nodePath = self::detectNodePath();
        $npmPath = self::detectNpmPath();

        Log::info('BrowsershotService PDF generation starting', [
            'output_path' => $outputPath,
            'chrome_path_detected' => $chromePath,
            'node_path_detected' => $nodePath,
            'npm_path_detected' => $npmPath,
            'config_chrome_args' => $config['default_options']['chrome_args'] ?? 'not_set',
            'options' => $options,
            'environment_vars' => [
                'CHROME_PATH' => env('CHROME_PATH'),
                'NODE_BINARY_PATH' => env('NODE_BINARY_PATH'),
                'NPM_BINARY_PATH' => env('NPM_BINARY_PATH'),
                'NODE_PATH' => $_ENV['NODE_PATH'] ?? 'not_set',
            ],
        ]);

        // Validate that all required binaries are available
        if ($chromePath === null || $chromePath === '' || $chromePath === '0') {
            throw new Exception(
                'Chrome/Chromium binary not found or not executable'
            );
        }
        if ($nodePath === null || $nodePath === '' || $nodePath === '0') {
            throw new Exception('Node.js binary not found or not executable');
        }
        if ($npmPath === null || $npmPath === '' || $npmPath === '0') {
            throw new Exception('NPM binary not found or not executable');
        }

        try {
            if ($outputPath !== null && $outputPath !== '' && $outputPath !== '0') {
                $browsershot->save($outputPath);

                return file_exists($outputPath);
            }

            return $browsershot->pdf();

        } catch (Exception $e) {
            Log::error('BrowsershotService PDF generation failed', [
                'error' => $e->getMessage(),
                'options' => $options,
                'output_path' => $outputPath,
                'chrome_path_detected' => $chromePath ?? self::detectChromePath(),
                'node_path_detected' => $nodePath ?? self::detectNodePath(),
                'npm_path_detected' => $npmPath ?? self::detectNpmPath(),
                'exception_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate a screenshot with default settings.
     *
     * @return string|bool Returns the screenshot content if no output path, or true/false if saving to file
     */
    public static function generateScreenshot(
        string $url,
        ?string $outputPath = null,
        array $options = []
    ): bool|string {
        $browsershot = self::url($url);

        // Apply screenshot-specific configuration
        $screenshotConfig = config('browsershot.screenshot_options', []);
        $options = array_merge($screenshotConfig, $options);

        if (isset($options['width'], $options['height'])) {
            $browsershot->windowSize($options['width'], $options['height']);
        }

        if (isset($options['device_scale_factor'])) {
            $browsershot->deviceScaleFactor($options['device_scale_factor']);
        }

        if ($options['full_page'] ?? false) {
            $browsershot->fullPage();
        }

        try {
            if ($outputPath !== null && $outputPath !== '' && $outputPath !== '0') {
                $browsershot->save($outputPath);

                return file_exists($outputPath);
            }

            return $browsershot->screenshot();

        } catch (Exception $e) {
            Log::error('BrowsershotService screenshot generation failed', [
                'error' => $e->getMessage(),
                'url' => $url,
                'options' => $options,
                'output_path' => $outputPath,
            ]);
            throw $e;
        }
    }

    /**
     * Test if Browsershot is working correctly.
     */
    public static function test(): array
    {
        $result = [
            'success' => false,
            'chrome_path' => null,
            'node_path' => null,
            'npm_path' => null,
            'errors' => [],
            'test_pdf_created' => false,
        ];

        try {
            // Check paths
            $config = config('browsershot', []);
            $result['chrome_path'] =
                in_array(self::detectChromePath(), [null, '', '0'], true) ? 'not found' : self::detectChromePath();
            $result['node_path'] = in_array(self::detectNodePath(), [null, '', '0'], true) ? 'not found' : self::detectNodePath();
            $result['npm_path'] = in_array(self::detectNpmPath(), [null, '', '0'], true) ? 'not found' : self::detectNpmPath();

            // Test PDF generation
            $testHtml = '<h1>Test</h1><p>This is a test PDF.</p>';
            $testPath = storage_path('app/browsershot-test.pdf');

            $pdfCreated = self::generatePdf($testHtml, $testPath);
            $result['test_pdf_created'] = $pdfCreated && file_exists($testPath);

            if ($result['test_pdf_created']) {
                // Clean up test file
                unlink($testPath);
                $result['success'] = true;
            }
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Get system information for debugging.
     */
    public static function getSystemInfo(): array
    {
        config('browsershot', []);

        $info = [
            'chrome_which' => mb_trim(shell_exec('which chromium') ?: 'not found'),
            'node_which' => mb_trim(shell_exec('which node') ?: 'not found'),
            'npm_which' => mb_trim(shell_exec('which npm') ?: 'not found'),
            'chrome_env' => env('CHROME_PATH', 'not set'),
            'node_env' => env('NODE_BINARY_PATH', 'not set'),
            'npm_env' => env('NPM_BINARY_PATH', 'not set'),
            'chrome_detected' => in_array(self::detectChromePath(), [null, '', '0'], true) ? 'not detected' : self::detectChromePath(),
            'node_detected' => in_array(self::detectNodePath(), [null, '', '0'], true) ? 'not detected' : self::detectNodePath(),
            'npm_detected' => in_array(self::detectNpmPath(), [null, '', '0'], true) ? 'not detected' : self::detectNpmPath(),
            'temp_dir' => config(
                'browsershot.temp_directory',
                'not configured'
            ),
            'nix_store_chromium' => [],
            'nix_profile_binaries' => [],
        ];

        // Check Nix store for chromium
        $nixStoreList = shell_exec(
            'ls /nix/store 2>/dev/null | grep chromium | head -5'
        );
        if ($nixStoreList) {
            $info['nix_store_chromium'] = array_filter(
                explode("\n", mb_trim($nixStoreList))
            );
        }

        // Check Nix profile binaries
        $nixProfileBinaries = shell_exec(
            'ls /root/.nix-profile/bin/ 2>/dev/null | grep -E "(chromium|node|npm)"'
        );
        if ($nixProfileBinaries) {
            $info['nix_profile_binaries'] = array_filter(
                explode("\n", mb_trim($nixProfileBinaries))
            );
        }

        return $info;
    }

    /**
     * Configure a Browsershot instance with the application settings.
     */
    private static function configureBrowsershot(
        Browsershot $browsershot
    ): Browsershot {
        $config = config('browsershot', []);

        // Force the correct Chrome path for containerized environment
        // Check environment variable first, then config, then detection
        $chromePath =
            env('CHROME_PATH') ?:
            $config['chrome_path'] ?:
            self::detectChromePath();

        if ($chromePath) {
            $browsershot->setChromePath($chromePath);
            Log::info('BrowsershotService using Chrome path', [
                'chrome_path' => $chromePath,
                'source' => env('CHROME_PATH')
                    ? 'environment'
                    : ($config['chrome_path']
                        ? 'config'
                        : 'detection'),
            ]);
        }

        // Force the correct Node path for containerized environment
        $nodePath =
            env('NODE_BINARY_PATH') ?:
            $config['node_binary_path'] ?:
            self::detectNodePath();
        if ($nodePath) {
            $browsershot->setNodeBinary($nodePath);
        }

        // For Nix environments, set NPM binary to prevent argument escaping issues
        $npmPath = env('NPM_BINARY_PATH') ?: '/tmp/fake-npm-bin/npm';
        if (file_exists($npmPath)) {
            $browsershot->setNpmBinary($npmPath);
            Log::info('BrowsershotService using NPM binary', [
                'npm_path' => $npmPath,
            ]);
        } else {
            // Skip NPM binary to prevent shell script execution issues
            Log::info(
                'BrowsershotService skipping NPM binary for Nix compatibility'
            );
        }

        // Set NODE_PATH environment variable to prevent Browsershot from calling npm
        if ($nodePath && is_executable($nodePath)) {
            $nodeDir = dirname((string) $nodePath);
            $globalNodeModules = $nodeDir.'/../lib/node_modules';
            if (is_dir($globalNodeModules)) {
                putenv('NODE_PATH='.$globalNodeModules);
                $_ENV['NODE_PATH'] = $globalNodeModules;
                Log::info('NODE_PATH set to prevent npm usage', [
                    'node_path' => $globalNodeModules,
                ]);
            }
        }

        // Set temporary directory
        $tempDir =
            $config['temp_directory'] ?? storage_path('app/browsershot-temp');
        if (! File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }
        $browsershot->setTemporaryDirectory($tempDir);

        // Apply default options - but apply Chrome arguments AFTER other configurations
        $defaultOptions = $config['default_options'] ?? [];

        // Configure basic options first
        if ($defaultOptions['no_sandbox'] ?? true) {
            $browsershot->noSandbox();
        }

        if ($defaultOptions['disable_web_security'] ?? true) {
            $browsershot->disableWebSecurity();
        }

        if ($defaultOptions['ignore_https_errors'] ?? true) {
            $browsershot->ignoreHttpsErrors();
        }

        $timeout = $defaultOptions['timeout'] ?? 120;
        $browsershot->timeout($timeout);

        // Apply Chrome arguments LAST to ensure they override any conflicting defaults
        if (isset($config['default_options']['chrome_args'])) {
            $chromeArgs = $config['default_options']['chrome_args'];

            // Clean up any double-escaped arguments
            $cleanArgs = array_map(fn ($arg): ?string =>
                // Remove any double dashes that might have been escaped
                preg_replace('/^-{3,}/', '--', (string) $arg), $chromeArgs);

            Log::info('BrowsershotService applying Chrome arguments', [
                'args_count' => count($cleanArgs),
                'sample_args' => array_slice($cleanArgs, 0, 5),
                'original_sample' => array_slice($chromeArgs, 0, 5),
            ]);
            $browsershot->addChromiumArguments($cleanArgs);
        }

        // Add protocol and network timeout configurations to prevent Chrome timeouts
        $browsershot->setOption('protocolTimeout', 120000); // 2 minutes
        $browsershot->setOption('slowMo', 100); // Slow down operations slightly
        $browsershot->setOption('devtools', false); // Disable devtools for performance

        return $browsershot;
    }

    /**
     * Detect Chrome/Chromium path with fallbacks.
     */
    private static function detectChromePath(): ?string
    {
        // Try environment variable first
        $chromePath = env('CHROME_PATH') ?: getenv('CHROME_PATH');
        if (
            $chromePath &&
            file_exists($chromePath) &&
            is_executable($chromePath)
        ) {
            Log::info('Chrome path found via environment variable', [
                'path' => $chromePath,
            ]);

            return $chromePath;
        }
        // Try Nix profile paths in priority order
        $nixPaths = [
            '/root/.nix-profile/bin/chromium',
            '/nix/var/nix/profiles/default/bin/chromium', // Fallback path
        ];
        foreach ($nixPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                // Test the binary by running --version
                $version = mb_trim(
                    shell_exec("$path --version 2>/dev/null") ?: ''
                );
                if ($version !== '' && $version !== '0') {
                    Log::info('Chrome path found in Nix profile', [
                        'path' => $path,
                        'version' => $version,
                    ]);

                    return $path;
                }
                Log::warning('Chrome binary found but not working', [
                    'path' => $path,
                ]);

            }
        }
        // Try which command with multiple Chrome binary names
        $whichPaths = [
            'google-chrome-stable',
            'google-chrome',
            'chromium',
            'chromium-browser',
        ];
        foreach ($whichPaths as $binary) {
            $path = mb_trim(shell_exec("which $binary 2>/dev/null") ?: '');
            if ($path && file_exists($path) && is_executable($path)) {
                // Test the binary by running --version
                $version = mb_trim(
                    shell_exec("$path --version 2>/dev/null") ?: ''
                );
                if ($version !== '' && $version !== '0') {
                    Log::info('Chrome path found via which command', [
                        'binary' => $binary,
                        'path' => $path,
                        'version' => $version,
                    ]);

                    return $path;
                }
                Log::warning(
                    'Chrome binary found via which but not working',
                    ['binary' => $binary, 'path' => $path]
                );

            }
        }
        Log::error('No working Chrome/Chromium binary found');

        return null;
    }

    /**
     * Detect Node.js path with fallbacks.
     */
    private static function detectNodePath(): ?string
    {
        // Try environment variable first
        $nodePath = env('NODE_BINARY_PATH') ?: getenv('NODE_BINARY_PATH');
        if ($nodePath && file_exists($nodePath) && is_executable($nodePath)) {
            $version = mb_trim(
                shell_exec("$nodePath --version 2>/dev/null") ?: ''
            );
            if ($version !== '' && $version !== '0') {
                Log::info('Node path found via environment variable', [
                    'path' => $nodePath,
                    'version' => $version,
                ]);

                return $nodePath;
            }
        }
        // Try Nix profile paths
        $nixPaths = [
            '/root/.nix-profile/bin/node',
            '/nix/var/nix/profiles/default/bin/node', // Fallback path
        ];
        foreach ($nixPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $version = mb_trim(
                    shell_exec("$path --version 2>/dev/null") ?: ''
                );
                if ($version !== '' && $version !== '0') {
                    Log::info('Node path found in Nix profile', [
                        'path' => $path,
                        'version' => $version,
                    ]);

                    return $path;
                }
            }
        }
        // Try which command
        $path = mb_trim(shell_exec('which node 2>/dev/null') ?: '');
        if ($path && file_exists($path) && is_executable($path)) {
            $version = mb_trim(shell_exec("$path --version 2>/dev/null") ?: '');
            if ($version !== '' && $version !== '0') {
                Log::info('Node path found via which command', [
                    'path' => $path,
                    'version' => $version,
                ]);

                return $path;
            }
        }
        Log::error('No working Node.js binary found');

        return null;
    }

    /**
     * Detect NPM path with fallbacks.
     */
    private static function detectNpmPath(): ?string
    {
        // Try environment variable first
        $npmPath = env('NPM_BINARY_PATH') ?: getenv('NPM_BINARY_PATH');
        if ($npmPath && file_exists($npmPath) && is_executable($npmPath)) {
            $version = mb_trim(shell_exec("$npmPath --version 2>/dev/null") ?: '');
            if ($version !== '' && $version !== '0') {
                Log::info('NPM path found via environment variable', [
                    'path' => $npmPath,
                    'version' => $version,
                ]);

                return $npmPath;
            }
        }
        // Try Nix profile paths
        $nixPaths = [
            '/root/.nix-profile/bin/npm',
            '/nix/var/nix/profiles/default/bin/npm', // Fallback path
        ];
        foreach ($nixPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $version = mb_trim(
                    shell_exec("$path --version 2>/dev/null") ?: ''
                );
                if ($version !== '' && $version !== '0') {
                    Log::info('NPM path found in Nix profile', [
                        'path' => $path,
                        'version' => $version,
                    ]);

                    return $path;
                }
            }
        }
        // Try which command
        $path = mb_trim(shell_exec('which npm 2>/dev/null') ?: '');
        if ($path && file_exists($path) && is_executable($path)) {
            $version = mb_trim(shell_exec("$path --version 2>/dev/null") ?: '');
            if ($version !== '' && $version !== '0') {
                Log::info('NPM path found via which command', [
                    'path' => $path,
                    'version' => $version,
                ]);

                return $path;
            }
        }
        Log::error('No working NPM binary found');

        return null;
    }
}
