<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\Controller;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\SearchService;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause;
use Ibexa\Core\MVC\Symfony\View\ContentView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CleanBlogPostController extends AbstractController
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

    public function showPostAction(ContentView $view): ContentView
    {
        return $this->repository->sudo(function () use ($view) {
            // Fetch recent posts (latest 3) for potential sidebar use
            $query = new \Ibexa\Contracts\Core\Repository\Values\Content\LocationQuery();
            $query->filter = new Criterion\LogicalAnd([
                new Criterion\ContentTypeIdentifier('aanwik_clean_blog_post'),
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
            ]);
            $query->sortClauses = [new SortClause\DatePublished(Query::SORT_DESC)];
            $query->limit = 3;

            $searchResult = $this->searchService->findLocations($query);
            $recentPosts = [];
            foreach ($searchResult->searchHits as $hit) {
                $recentPosts[] = $this->contentService->loadContentByContentInfo($hit->valueObject->contentInfo);
            }

            $view->addParameters([
                'recent_posts' => $recentPosts,
            ]);

            return $view;
        });
    }
}
