<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\Component\Dashboard;

use Aanwik\IbexaCleanBlogThemeBundle\Entity\ContactSubmission;
use Doctrine\ORM\EntityManagerInterface;
use Ibexa\TwigComponents\Component\TemplateComponent;
use Twig\Environment;

class ContactSubmissionsDashboardBlock extends TemplateComponent
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        Environment $twig,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct($twig, '@ibexadesign/clean_blog/admin/dashboard/contact_submissions_block.html.twig');
        $this->entityManager = $entityManager;
    }

    public function render(array $parameters = []): string
    {
        $parameters['submissions'] = $this->entityManager->getRepository(ContactSubmission::class)
            ->findBy([], ['createdAt' => 'DESC'], 5);

        $parameters['unread_count'] = $this->entityManager->getRepository(ContactSubmission::class)
            ->count(['isRead' => false]);

        return parent::render($parameters);
    }
}
