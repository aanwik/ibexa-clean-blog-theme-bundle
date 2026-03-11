<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\Controller;

use Aanwik\IbexaCleanBlogThemeBundle\Entity\ContactSubmission;
use Aanwik\IbexaCleanBlogThemeBundle\Form\Type\ContactFormType;
use Doctrine\ORM\EntityManagerInterface;
use Ibexa\Core\MVC\Symfony\View\ContentView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class CleanBlogContactController extends AbstractController
{
    private EntityManagerInterface $em;
    private MailerInterface $mailer;

    public function __construct(EntityManagerInterface $em, MailerInterface $mailer)
    {
        $this->em = $em;
        $this->mailer = $mailer;
    }

    public function showContactAction(ContentView $view, Request $request): ContentView
    {
        $form = $this->createForm(ContactFormType::class, null, [
            'action' => $request->getUri(),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);
        $submitted = false;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Save to database
            $submission = new ContactSubmission();
            $submission->setName($data['name']);
            $submission->setEmail($data['email']);
            $submission->setPhone($data['phone'] ?? null);
            $submission->setSubject($data['subject']);
            $submission->setMessage($data['message']);
            $submission->setIpAddress($request->getClientIp());

            $this->em->persist($submission);
            $this->em->flush();

            // Send email notifications
            $this->sendNotificationEmails($submission);

            $submitted = true;

            // Create a fresh form for display after submission
            $form = $this->createForm(ContactFormType::class);
        }

        $view->addParameters([
            'contact_form' => $form->createView(),
            'form_submitted' => $submitted,
        ]);

        return $view;
    }

    private function sendNotificationEmails(ContactSubmission $submission): void
    {
        try {
            // Email to admin
            $adminEmail = (new Email())
                ->from('noreply@cleanblog.com')
                ->to('admin@cleanblog.com')
                ->subject('[Clean Blog] New Contact: ' . $submission->getSubject())
                ->html($this->renderAdminEmailHtml($submission));

            $this->mailer->send($adminEmail);

            // Confirmation email to submitter
            $userEmail = (new Email())
                ->from('noreply@cleanblog.com')
                ->to($submission->getEmail())
                ->subject('Thank you for contacting us - ' . $submission->getSubject())
                ->html($this->renderUserEmailHtml($submission));

            $this->mailer->send($userEmail);
        } catch (\Exception $e) {
            // Log but don't fail the form submission
        }
    }

    private function renderAdminEmailHtml(ContactSubmission $submission): string
    {
        return <<<HTML
        <div style="font-family: 'Open Sans', Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #0085A1; color: #ffffff; padding: 20px 30px; border-radius: 8px 8px 0 0;">
                <h2 style="margin: 0;">New Contact Form Submission</h2>
            </div>
            <div style="background: #f8f9fa; padding: 25px 30px; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 8px 8px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; font-weight: 700; color: #495057; width: 100px;">Name:</td>
                        <td style="padding: 8px 0; color: #212529;">{$submission->getName()}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-weight: 700; color: #495057;">Email:</td>
                        <td style="padding: 8px 0;"><a href="mailto:{$submission->getEmail()}" style="color: #0085A1;">{$submission->getEmail()}</a></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-weight: 700; color: #495057;">Phone:</td>
                        <td style="padding: 8px 0; color: #212529;">{$submission->getPhone()}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-weight: 700; color: #495057;">Subject:</td>
                        <td style="padding: 8px 0; color: #212529;">{$submission->getSubject()}</td>
                    </tr>
                </table>
                <div style="margin-top: 15px; padding: 15px; background: #ffffff; border-radius: 6px; border: 1px solid #e9ecef;">
                    <strong style="color: #495057;">Message:</strong>
                    <p style="margin: 8px 0 0; color: #212529; line-height: 1.6;">{$submission->getMessage()}</p>
                </div>
                <p style="margin-top: 15px; font-size: 12px; color: #6c757d;">IP Address: {$submission->getIpAddress()} | Submitted: {$submission->getCreatedAt()->format('M j, Y g:i A')}</p>
            </div>
        </div>
        HTML;
    }

    private function renderUserEmailHtml(ContactSubmission $submission): string
    {
        return <<<HTML
        <div style="font-family: 'Open Sans', Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #0085A1; color: #ffffff; padding: 20px 30px; border-radius: 8px 8px 0 0;">
                <h2 style="margin: 0;">Thank You for Contacting Us!</h2>
            </div>
            <div style="background: #f8f9fa; padding: 25px 30px; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 8px 8px;">
                <p style="color: #212529; line-height: 1.6;">Hi <strong>{$submission->getName()}</strong>,</p>
                <p style="color: #212529; line-height: 1.6;">Thank you for reaching out to us. We have received your message and will get back to you as soon as possible.</p>
                <div style="margin: 20px 0; padding: 15px; background: #ffffff; border-radius: 6px; border: 1px solid #e9ecef;">
                    <strong style="color: #495057;">Your message:</strong>
                    <p style="margin: 8px 0 0; color: #6c757d; line-height: 1.6;">{$submission->getMessage()}</p>
                </div>
                <p style="color: #6c757d; font-size: 13px;">— The Clean Blog Team</p>
            </div>
        </div>
        HTML;
    }
}
