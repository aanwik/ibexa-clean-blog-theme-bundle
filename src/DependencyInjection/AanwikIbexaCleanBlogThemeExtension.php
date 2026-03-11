<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\DependencyInjection;

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Yaml\Yaml;

class AanwikIbexaCleanBlogThemeExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        
        if (file_exists(__DIR__ . '/../Resources/config/services.yaml')) {
            $loader->load('services.yaml');
        }

        // Auto-create routing import file if it doesn't exist (Step 4)
        $this->ensureRoutingFileExists($container);
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Step 5: Design config is automatically prepended
        $configFile = __DIR__ . '/../Resources/config/ibexa_design.yaml';
        $config = Yaml::parseFile($configFile);

        $container->prependExtensionConfig('ibexa', $config['ibexa']);
        $container->prependExtensionConfig('ibexa_design_engine', $config['ibexa_design_engine']);
        $container->addResource(new FileResource($configFile));

        // Register Doctrine ORM mapping for ContactSubmission entity
        $entityPath = realpath(__DIR__ . '/../Entity');
        if ($entityPath) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'AanwikIbexaCleanBlogThemeBundle' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => $entityPath,
                            'prefix' => 'Aanwik\IbexaCleanBlogThemeBundle\Entity',
                            'alias' => 'CleanBlogTheme',
                        ],
                    ],
                ],
            ]);
        }
    }

    /**
     * Step 4: Auto-create the routing import file in the host project's config/routes/ directory.
     */
    private function ensureRoutingFileExists(ContainerBuilder $container): void
    {
        $projectDir = $container->getParameter('kernel.project_dir');
        $routingFile = $projectDir . '/config/routes/00_ibexa_clean_blog_theme.yaml';

        if (!file_exists($routingFile)) {
            $routesDir = dirname($routingFile);
            if (!is_dir($routesDir)) {
                @mkdir($routesDir, 0755, true);
            }
            $content = "ibexa_clean_blog_theme_front:\n    resource: '@AanwikIbexaCleanBlogThemeBundle/Resources/config/routing.yaml'\n\n";
            $content .= "ibexa_clean_blog_theme_admin:\n    resource: '@AanwikIbexaCleanBlogThemeBundle/Resources/config/routing_admin.yaml'\n    prefix: /admin\n";
            @file_put_contents($routingFile, $content);
        }
    }
}
