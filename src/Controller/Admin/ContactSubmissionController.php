<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\Controller\Admin;

use Aanwik\IbexaCleanBlogThemeBundle\Entity\ContactSubmission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ContactSubmissionController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function listAction(Request $request): Response
    {
        $submissions = $this->em->getRepository(ContactSubmission::class)
            ->findBy([], ['createdAt' => 'DESC']);

        $unreadCount = $this->em->getRepository(ContactSubmission::class)
            ->count(['isRead' => false]);

        return $this->render('@ibexadesign/clean_blog/admin/contact_list.html.twig', [
            'submissions' => $submissions,
            'unread_count' => $unreadCount,
        ]);
    }

    public function viewAction(int $id): Response
    {
        $submission = $this->em->getRepository(ContactSubmission::class)->find($id);

        if (!$submission) {
            throw $this->createNotFoundException('Submission not found.');
        }

        // Mark as read
        if (!$submission->isRead()) {
            $submission->setIsRead(true);
            $this->em->flush();
        }

        return $this->render('@ibexadesign/clean_blog/admin/contact_view.html.twig', [
            'submission' => $submission,
        ]);
    }

    public function deleteAction(int $id): Response
    {
        $submission = $this->em->getRepository(ContactSubmission::class)->find($id);

        if ($submission) {
            $this->em->remove($submission);
            $this->em->flush();
            $this->addFlash('success', 'Submission deleted successfully.');
        }

        return $this->redirectToRoute('clean_blog.admin.contact_submissions');
    }
}
