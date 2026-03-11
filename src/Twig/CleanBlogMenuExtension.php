<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\Twig;

use Ibexa\Contracts\Core\Repository\SearchService;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\SortClause;
use Ibexa\Contracts\Core\Repository\Repository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CleanBlogMenuExtension extends AbstractExtension
{
    private SearchService $searchService;
    private Repository $repository;

    public function __construct(SearchService $searchService, Repository $repository)
    {
        $this->searchService = $searchService;
        $this->repository = $repository;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('clean_blog_menu', [$this, 'getMenuItems']),
        ];
    }

    /**
     * @param string $type 'header' or 'footer'
     */
    public function getMenuItems(string $type = 'header'): array
    {
        $fieldIdentifier = $type === 'header' ? 'show_in_header_menu' : 'show_in_footer_menu';

        return $this->repository->sudo(function () use ($fieldIdentifier) {
            $query = new Query();
            $query->filter = new Criterion\LogicalAnd([
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                new Criterion\Field($fieldIdentifier, Criterion\Operator::EQ, true)
            ]);
            
            // Sort by position or name. Name is safer across multiple content types.
            $query->sortClauses = [new SortClause\ContentName(Query::SORT_ASC)];

            $searchHits = $this->searchService->findContent($query)->searchHits;

            $items = [];
            foreach ($searchHits as $hit) {
                $content = $hit->valueObject;
                $items[] = [
                    'name' => $content->getName(),
                    'locationId' => $content->contentInfo->mainLocationId,
                ];
            }

            return $items;
        });
    }
}
