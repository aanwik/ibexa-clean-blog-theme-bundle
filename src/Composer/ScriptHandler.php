<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\Composer;

use Composer\Script\Event;

/**
 * Composer ScriptHandler for post-install automation.
 *
 * Add to your project's composer.json "auto-scripts" section:
 *   "aanwik:clean-blog:import-demo": "symfony-cmd"
 *
 * Or call directly via:
 *   "post-install-cmd": ["Aanwik\\IbexaCleanBlogThemeBundle\\Composer\\ScriptHandler::postInstall"]
 */
class ScriptHandler
{
    public static function postInstall(Event $event): void
    {
        $io = $event->getIO();
        $composer = $event->getComposer();
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        $projectDir = dirname($vendorDir);

        $io->write('<info>Aanwik Clean Blog Theme: Running post-install configuration...</info>');

        // Step 4: Create routing file
        self::ensureRoutingFile($projectDir, $io);

        // Step 6: Run console commands (assets:install handled by auto-scripts, import-demo here)
        self::runConsoleCommand($projectDir, 'aanwik:clean-blog:import-demo', $io);
    }

    private static function ensureRoutingFile(string $projectDir, $io): void
    {
        $routingFile = $projectDir . '/config/routes/00_ibexa_clean_blog_theme.yaml';

        if (!file_exists($routingFile)) {
            $routesDir = dirname($routingFile);
            if (!is_dir($routesDir)) {
                @mkdir($routesDir, 0755, true);
            }
            $content = "ibexa_clean_blog_theme:\n    resource: '@AanwikIbexaCleanBlogThemeBundle/Resources/config/routing.yaml'\n";
            file_put_contents($routingFile, $content);
            $io->write('<info>  ✓ Created routing file: config/routes/00_ibexa_clean_blog_theme.yaml</info>');
        } else {
            $io->write('<info>  ✓ Routing file already exists</info>');
        }
    }

    private static function runConsoleCommand(string $projectDir, string $command, $io): void
    {
        $consolePath = $projectDir . '/bin/console';
        if (!file_exists($consolePath)) {
            $io->write("<warning>  ⚠ Cannot find bin/console, skipping: $command</warning>");
            return;
        }

        $io->write("<info>  Running: php bin/console $command</info>");
        $process = proc_open(
            "php $consolePath $command --no-interaction 2>&1",
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            $projectDir
        );

        if (is_resource($process)) {
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode === 0) {
                $io->write("<info>  ✓ $command completed successfully</info>");
            } else {
                $io->write("<warning>  ⚠ $command exited with code $exitCode</warning>");
                if ($output) {
                    $io->write($output);
                }
            }
        }
    }
}
