<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\Twig;

use Ibexa\Contracts\Core\Repository\Repository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
class CleanBlogExtension extends AbstractExtension
{
    private Repository $repository;
    private \Symfony\Component\Routing\RouterInterface $router;

    public function __construct(Repository $repository, \Symfony\Component\Routing\RouterInterface $router)
    {
        $this->repository = $repository;
        $this->router = $router;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ibexa_location_id_by_remote_id', [$this, 'getLocationIdByRemoteId']),
            new TwigFunction('ibexa_load_content', [$this, 'loadContent']),
            new TwigFunction('ibexa_load_content_by_remote_id', [$this, 'loadContentByRemoteId']),
            new TwigFunction('clean_blog_slug', [$this, 'getSlugByLocationId']),
            new TwigFunction('cb_load_user', [$this, 'loadUserSudo']),
            new TwigFunction('cb_author_url', [$this, 'getAuthorUrlSudo']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new \Twig\TwigFilter('truncate', [$this, 'truncate']),
        ];
    }

    public function truncate(string $text, int $limit = 150, string $separator = '...'): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit) . $separator;
    }

    public function loadContentByRemoteId(string $remoteId)
    {
        try {
            $contentInfo = $this->repository->getContentService()->loadContentInfoByRemoteId($remoteId);
            return $this->repository->getContentService()->loadContent($contentInfo->id);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getLocationIdByRemoteId(string $remoteId): ?int
    {
        try {
            $contentInfo = $this->repository->getContentService()->loadContentInfoByRemoteId($remoteId);
            return (int) $contentInfo->mainLocationId;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getSlugByLocationId(int $locationId): string
    {
        try {
            $location = $this->repository->getLocationService()->loadLocation($locationId);
            $alias = $this->repository->getURLAliasService()->reverseLookup($location);
            return ltrim($alias->path, '/');
        } catch (\Exception $e) {
            return (string) $locationId;
        }
    }

    public function loadContent($contentId)
    {
        if ($contentId === null) {
            return null;
        }

        if (is_object($contentId) && method_exists($contentId, 'id')) {
            $contentId = $contentId->id;
        }

        try {
            return $this->repository->getContentService()->loadContent($contentId);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function loadUserSudo($userId): ?\Ibexa\Contracts\Core\Repository\Values\Content\Content
    {
        if (empty($userId)) {
            return null;
        }

        if ($userId instanceof \Ibexa\Contracts\Core\Repository\Values\Content\Content) {
            return $userId;
        }

        try {
            return $this->repository->sudo(function () use ($userId) {
                return $this->repository->getContentService()->loadContent($userId);
            });
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getAuthorUrlSudo($userId): string
    {
        if (empty($userId)) {
            return '#!';
        }

        if ($userId instanceof \Ibexa\Contracts\Core\Repository\Values\Content\Content) {
            $userId = $userId->id;
        }

        try {
            return $this->repository->sudo(function () use ($userId) {
                $user = $this->repository->getUserService()->loadUser((int)$userId);
                return $this->router->generate('clean_blog_author', ['slug' => $user->login]);
            });
        } catch (\Exception $e) {
            return '#!';
        }
    }
}
