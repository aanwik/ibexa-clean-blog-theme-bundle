<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\Controller;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\SearchService;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationQuery;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause;
use Ibexa\Core\Pagination\Pagerfanta\ContentSearchAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CleanBlogAuthorController extends AbstractController
{
    private Repository $repository;
    private SearchService $searchService;
    private ContentService $contentService;

    public function __construct(Repository $repository, SearchService $searchService, ContentService $contentService)
    {
        $this->repository = $repository;
        $this->searchService = $searchService;
        $this->contentService = $contentService;
    }

    public function showAuthorAction(?object $content = null, ?object $location = null, ?string $slug = null, int $page = 1): Response
    {
        return $this->repository->sudo(function () use ($content, $slug, $page) {
            if ($content instanceof \Ibexa\Contracts\Core\Repository\Values\Content\Content) {
                $author = $content;
                $contentId = $author->id;
            } elseif ($slug !== null) {
                try {
                    $user = $this->repository->getUserService()->loadUserByLogin($slug);
                    $author = $user->content;
                    $contentId = $author->id;
                } catch (\Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException $e) {
                    throw new NotFoundHttpException('Author not found: ' . $slug, $e);
                }
            } else {
                throw new \InvalidArgumentException('Author content, ID or slug must be provided.');
            }
            $postsPerPage = $this->getPostsPerPage();

            // Search for posts by this author
            $query = new LocationQuery();
            $query->filter = new Criterion\LogicalAnd([
                new Criterion\ContentTypeIdentifier('aanwik_clean_blog_post'),
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                new Criterion\Field('post_author', Criterion\Operator::IN, [$contentId]),
            ]);
            $query->sortClauses = [new SortClause\DatePublished(Query::SORT_DESC)];

            $pagerfanta = new Pagerfanta(new ContentSearchAdapter($query, $this->searchService));
            $pagerfanta->setMaxPerPage($postsPerPage);
            $pagerfanta->setCurrentPage($page);

            return $this->render('@clean_blog_theme/full/author.html.twig', [
                'author' => $author,
                'pager' => $pagerfanta,
            ]);
        });
    }

    private function getPostsPerPage(): int
    {
        try {
            $contentInfo = $this->contentService->loadContentInfoByRemoteId('aanwik_clean_blog_settings_global');
            $content = $this->contentService->loadContent($contentInfo->id);
            $value = $content->getFieldValue('posts_per_page');
            if ($value !== null && (string) $value !== '') {
                $perPage = (int) (string) $value;
                if ($perPage > 0) {
                    return $perPage;
                }
            }
        } catch (\Exception $e) {
        }

        return 10;
    }
}
