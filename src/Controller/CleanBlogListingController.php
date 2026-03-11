<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\Controller;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\SearchService;
use Ibexa\Contracts\Core\Repository\URLAliasService;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationQuery;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause;
use Ibexa\Core\Pagination\Pagerfanta\ContentSearchAdapter;
use Netgen\TagsBundle\API\Repository\TagsService;
use Netgen\TagsBundle\API\Repository\Values\Content\Query\Criterion\TagId;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CleanBlogListingController extends AbstractController
{
    private Repository $repository;
    private SearchService $searchService;
    private ContentService $contentService;
    private TagsService $tagsService;
    private LocationService $locationService;
    private URLAliasService $urlAliasService;

    public function __construct(
        Repository $repository,
        SearchService $searchService,
        ContentService $contentService,
        TagsService $tagsService,
        LocationService $locationService,
        URLAliasService $urlAliasService
    ) {
        $this->repository = $repository;
        $this->searchService = $searchService;
        $this->contentService = $contentService;
        $this->tagsService = $tagsService;
        $this->locationService = $locationService;
        $this->urlAliasService = $urlAliasService;
    }

    public function listAction(Request $request, int $page = 1): Response
    {
        return $this->repository->sudo(function () use ($request, $page) {
            $postsPerPage = $this->getPostsPerPage();
            
            $query = new LocationQuery();
            $query->filter = new Criterion\LogicalAnd([
                new Criterion\ContentTypeIdentifier('aanwik_clean_blog_post'),
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
            ]);
            $query->sortClauses = [new SortClause\DatePublished(Query::SORT_DESC)];

            $pagerfanta = new Pagerfanta(new ContentSearchAdapter($query, $this->searchService));
            $pagerfanta->setMaxPerPage($postsPerPage);
            $pagerfanta->setCurrentPage($page);

            return $this->render('@clean_blog_theme/full/blog_listing.html.twig', [
                'pager' => $pagerfanta,
                'current_page' => $page,
            ]);
        });
    }

    public function searchAction(Request $request): Response
    {
        return $this->repository->sudo(function () use ($request) {
            $queryText = $request->query->get('q', '');
            $page = $request->query->getInt('page', 1);
            $postsPerPage = $this->getPostsPerPage();

            $query = new LocationQuery();
            $criteria = [
                new Criterion\ContentTypeIdentifier('aanwik_clean_blog_post'),
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
            ];

            if (!empty($queryText)) {
                $criteria[] = new Criterion\FullText($queryText);
            }

            $query->filter = new Criterion\LogicalAnd($criteria);
            $query->sortClauses = [new SortClause\DatePublished(Query::SORT_DESC)];

            $pagerfanta = new Pagerfanta(new ContentSearchAdapter($query, $this->searchService));
            $pagerfanta->setMaxPerPage($postsPerPage);
            $pagerfanta->setCurrentPage($page);

            return $this->render('@clean_blog_theme/listing/search.html.twig', [
                'pager' => $pagerfanta,
                'query' => $queryText,
            ]);
        });
    }

    public function archiveAction(int $year, int $month, int $page = 1): Response
    {
        return $this->repository->sudo(function () use ($year, $month, $page) {
            $postsPerPage = $this->getPostsPerPage();

            // Start and end of the month
            $startDate = new \DateTime("$year-$month-01 00:00:00");
            $endDate = clone $startDate;
            $endDate->modify('last day of this month 23:59:59');

            $query = new LocationQuery();
            $query->filter = new Criterion\LogicalAnd([
                new Criterion\ContentTypeIdentifier('aanwik_clean_blog_post'),
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                new Criterion\DateMetadata(Criterion\DateMetadata::PUBLISHED, Criterion\Operator::BETWEEN, [$startDate->getTimestamp(), $endDate->getTimestamp()]),
            ]);
            $query->sortClauses = [new SortClause\DatePublished(Query::SORT_DESC)];

            $pagerfanta = new Pagerfanta(new ContentSearchAdapter($query, $this->searchService));
            $pagerfanta->setMaxPerPage($postsPerPage);
            $pagerfanta->setCurrentPage($page);

            return $this->render('@clean_blog_theme/listing/archive.html.twig', [
                'pager' => $pagerfanta,
                'year' => $year,
                'month' => $month,
                'month_name' => $startDate->format('F'),
            ]);
        });
    }

    public function categoryAction(Request $request, ?object $content = null, ?object $location = null, ?int $id = null, ?string $slug = null, int $page = 1): Response
    {
        $page = $request->query->getInt('page', $page);
        return $this->repository->sudo(function () use ($content, $id, $slug, $page) {
            if ($content instanceof \Ibexa\Contracts\Core\Repository\Values\Content\Content) {
                $category = $content;
                $id = $category->id;
            } elseif ($slug !== null) {
                try {
                    // Ensure the slug has a leading slash if needed by Ibexa URLAliasService
                    $url = '/' . ltrim($slug, '/');
                    $urlAlias = $this->urlAliasService->lookup($url);
                    $locationId = (int) $urlAlias->destination;
                    
                    $location = $this->locationService->loadLocation($locationId);
                    $category = $this->contentService->loadContentByContentInfo($location->contentInfo);
                    $id = $category->id;
                    
                    // Validate it's a category content type
                    if ($category->contentType->identifier !== 'aanwik_clean_blog_category') {
                        throw new NotFoundHttpException('Not a category.');
                    }
                } catch (\Exception $e) {
                    throw new NotFoundHttpException('Category not found: ' . $slug, $e);
                }
            } elseif ($id !== null) {
                $category = $this->contentService->loadContent($id);
            } else {
                throw new \InvalidArgumentException('Category content, ID or slug must be provided.');
            }

            $postsPerPage = $this->getPostsPerPage();

            $query = new LocationQuery();
            $query->filter = new Criterion\LogicalAnd([
                new Criterion\ContentTypeIdentifier('aanwik_clean_blog_post'),
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                new Criterion\Field('categories', Criterion\Operator::IN, [$id]),
            ]);
            $query->sortClauses = [new SortClause\DatePublished(Query::SORT_DESC)];

            $pagerfanta = new Pagerfanta(new ContentSearchAdapter($query, $this->searchService));
            $pagerfanta->setMaxPerPage($postsPerPage);
            $pagerfanta->setCurrentPage($page);

            return $this->render('@clean_blog_theme/full/category.html.twig', [
                'pager' => $pagerfanta,
                'category' => $category,
            ]);
        });
    }

    public function tagAction(Request $request, ?object $tag = null, ?string $slug = null, int $page = 1): Response
    {
        $page = $request->query->getInt('page', $page);
        return $this->repository->sudo(function () use ($tag, $slug, $page) {
            if ($tag instanceof \Netgen\TagsBundle\API\Repository\Values\Tags\Tag) {
                $id = $tag->id;
            } elseif ($slug !== null) {
                $tags = $this->tagsService->loadTagsByKeyword($slug, 'eng-GB'); // Assuming eng-GB as primary
                if (count($tags) === 0) {
                    throw new NotFoundHttpException('Tag not found: ' . $slug);
                }
                $tag = $tags[0];
                $id = $tag->id;
            } else {
                throw new \InvalidArgumentException('Tag or slug must be provided.');
            }

            $postsPerPage = $this->getPostsPerPage();

            $query = new LocationQuery();
            $query->filter = new Criterion\LogicalAnd([
                new Criterion\ContentTypeIdentifier('aanwik_clean_blog_post'),
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                new TagId($id),
            ]);
            $query->sortClauses = [new SortClause\DatePublished(Query::SORT_DESC)];

            $pagerfanta = new Pagerfanta(new ContentSearchAdapter($query, $this->searchService));
            $pagerfanta->setMaxPerPage($postsPerPage);
            $pagerfanta->setCurrentPage($page);

            return $this->render('@clean_blog_theme/tag/view.html.twig', [
                'pager' => $pagerfanta,
                'tag' => $tag,
            ]);
        });
    }

    private function getPostsPerPage(): int
    {
        try {
            $contentService = $this->contentService;
            $contentInfo = $contentService->loadContentInfoByRemoteId('aanwik_clean_blog_settings_global');
            $content = $contentService->loadContent($contentInfo->id);
            $value = $content->getFieldValue('posts_per_page');
            if ($value !== null && (string) $value !== '') {
                $perPage = (int) (string) $value;
                if ($perPage > 0) {
                    return $perPage;
                }
            }
        } catch (\Exception $e) {
            // Settings not found, use default
        }

        return 10; // Default fallback
    }
}
