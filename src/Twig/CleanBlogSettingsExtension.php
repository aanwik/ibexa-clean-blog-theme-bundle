<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\Twig;

use Ibexa\Contracts\Core\Repository\Repository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CleanBlogSettingsExtension extends AbstractExtension
{
    private Repository $repository;
    private mixed $cachedSettings = false;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('clean_blog_settings', [$this, 'getSettings']),
        ];
    }

    public function getSettings()
    {
        if ($this->cachedSettings !== false) {
            return $this->cachedSettings;
        }

        try {
            $contentInfo = $this->repository->getContentService()->loadContentInfoByRemoteId('clean_blog_settings_global');
            $this->cachedSettings = $this->repository->getContentService()->loadContent($contentInfo->id);
        } catch (\Exception $e) {
            $this->cachedSettings = null;
        }

        return $this->cachedSettings;
    }
}
