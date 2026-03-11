<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\Controller;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\SearchService;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Netgen\TagsBundle\API\Repository\TagsService;
use Symfony\Component\HttpFoundation\Response;
use Ibexa\Contracts\Core\Repository\Repository;
use Twig\Environment;

class CleanBlogFolderController
{
    private ContentService $contentService;
    private LocationService $locationService;
    private SearchService $searchService;
    private TagsService $tagsService;
    private Repository $repository;
    private Environment $twig;

    public function __construct(
        ContentService $contentService,
        LocationService $locationService,
        SearchService $searchService,
        TagsService $tagsService,
        Repository $repository,
        Environment $twig
    ) {
        $this->contentService = $contentService;
        $this->locationService = $locationService;
        $this->searchService = $searchService;
        $this->tagsService = $tagsService;
        $this->repository = $repository;
        $this->twig = $twig;
    }

    public function viewLocationAction(Location $location): Response
    {
        $content = $this->contentService->loadContent($location->contentInfo->id);
        $remoteId = $content->contentInfo->remoteId;

        // Define templates for specific frontend folders
        switch ($remoteId) {
            case 'aanwik_folder_categories':
                $query = new Query();
                $query->filter = new Criterion\LogicalAnd([
                    new Criterion\ContentTypeIdentifier('aanwik_clean_blog_category'),
                    new Criterion\Visibility(Criterion\Visibility::VISIBLE)
                ]);
                $query->sortClauses = [new SortClause\ContentName(Query::SORT_ASC)];
                $categories = $this->searchService->findContent($query)->searchHits;

                return new Response($this->twig->render('@clean_blog_theme/full/folder_categories.html.twig', [
                    'location' => $location,
                    'content' => $content,
                    'categories' => $categories,
                ]));

            case 'aanwik_folder_tags':
                $tags = $this->repository->sudo(function () {
                    return $this->tagsService->loadTagChildren(null, 0, -1); 
                });
                return new Response($this->twig->render('@clean_blog_theme/full/folder_tags.html.twig', [
                    'location' => $location,
                    'content' => $content,
                    'tags' => $tags,
                ]));

            case 'aanwik_folder_authors':
                $authors = $this->repository->sudo(function () {
                    // 1. Get unique author IDs from all blog posts
                    $postQuery = new Query();
                    $postQuery->filter = new Criterion\ContentTypeIdentifier('aanwik_clean_blog_post');
                    $postQuery->limit = 1000;
                    $postHits = $this->searchService->findContent($postQuery)->searchHits;
                    
                    $authorIds = [];
                    foreach ($postHits as $hit) {
                        $authorRel = $hit->valueObject->getFieldValue('post_author');
                        if ($authorRel && $authorRel->destinationContentId) {
                            $authorIds[] = $authorRel->destinationContentId;
                        }
                    }
                    $authorIds = array_unique($authorIds);

                    if (empty($authorIds)) {
                        return [];
                    }

                    // 2. Load those users
                    $userQuery = new Query();
                    $userQuery->filter = new Criterion\LogicalAnd([
                        new Criterion\ContentTypeIdentifier('user'),
                        new Criterion\ContentId($authorIds)
                    ]);
                    $userQuery->sortClauses = [new SortClause\ContentName(Query::SORT_ASC)];
                    return $this->searchService->findContent($userQuery)->searchHits;
                });

                return new Response($this->twig->render('@clean_blog_theme/full/folder_authors.html.twig', [
                    'location' => $location,
                    'content' => $content,
                    'authors' => $authors,
                ]));

            case 'aanwik_folder_blog':
                $query = new Query();
                $query->filter = new Criterion\LogicalAnd([
                    new Criterion\ContentTypeIdentifier('aanwik_clean_blog_post'),
                    new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                    new Criterion\ParentLocationId($location->id)
                ]);
                $query->sortClauses = [new SortClause\DatePublished(Query::SORT_DESC)];
                $query->limit = 100;
                $blogPosts = $this->searchService->findContent($query)->searchHits;

                return new Response($this->twig->render('@clean_blog_theme/full/folder_blog.html.twig', [
                    'location' => $location,
                    'content' => $content,
                    'blog_posts' => $blogPosts,
                ]));

            case 'aanwik_folder_archives':
                // Simple implementation: fetch recent posts.
                // A true archive would group by month, which is complex in pure twig.
                // We will fetch up to 50 recent posts.
                $query = new Query();
                $query->filter = new Criterion\LogicalAnd([
                    new Criterion\ContentTypeIdentifier('aanwik_clean_blog_post'),
                    new Criterion\Visibility(Criterion\Visibility::VISIBLE)
                ]);
                $query->sortClauses = [new SortClause\DatePublished(Query::SORT_DESC)];
                $query->limit = 50;
                $archives = $this->searchService->findContent($query)->searchHits;

                return new Response($this->twig->render('@clean_blog_theme/full/folder_archives.html.twig', [
                    'location' => $location,
                    'content' => $content,
                    'archives' => $archives,
                ]));
        }

        // Generic fallback for other folders
        return new Response($this->twig->render('@clean_blog_theme/full/folder.html.twig', [
            'location' => $location,
            'content' => $content,
        ]));
    }
}
