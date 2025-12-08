<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Entity\Email;
use DateTime;
use Doctrine\ORM\EntityManager;
use Exception;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\BodyRendererInterface;

class EmailSendingService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config,
        private readonly MailerInterface $mailer,
        private readonly BodyRendererInterface $bodyRenderer,
    ) {}

    public function run(): void
    {
        $emails = $this->entityManager->getRepository(Email::class)->findAll();
        $errors = 0;
        foreach ($emails as $email) {
            try {
                $this->send(
                    to: $email->getEmail(),
                    subject: $email->getSubject(),
                    htmlTemplate: $email->getHtmlTemplate(),
                    activationLink: $email->getActivationLink(),
                    expirationDate: $email->getExpirationDate(),
                    resetLink: $email->getResetLink()
                );
                $this->entityManager->remove($email);
                $this->entityManager->flush();
            } catch (Exception $e) {
                $errors += 1;
                error_log($e->getMessage());
            }
        }

        if ($errors) {
            echo <<<RESULT
            --------------------------
            EMAIL SENDING SERVICE
            --        
            ERRORS: $errors       
            --------------------------\n
            RESULT;
        }
    }

    public function queue(
        string $to,
        string $subject,
        string $htmlTemplate,
        ?string $activationLink = null,
        ?DateTime $expirationDate = null,
        ?string $resetLink = null
    ): void {
        $email = new Email();
        $email->setEmail($to);
        $email->setSubject($subject);
        $email->setHtmlTemplate($htmlTemplate);
        $email->setActivationLink($activationLink);
        $email->setExpirationDate($expirationDate);
        $email->setResetLink($resetLink);

        $this->entityManager->persist($email);
        $this->entityManager->flush();
    }

    public function send(
        string $to,
        string $subject,
        string $htmlTemplate,
        ?string $activationLink = null,
        ?DateTime $expirationDate = null,
        ?string $resetLink = null
    ): void {
        $appName = $this->config->get('app_name');

        $context = [
            'appName' => $appName,
            'website' => $this->config->get('app_url'),
        ];

        if ($activationLink) {
            $context['activationLink'] = $activationLink;
        }
        if ($expirationDate) {
            $context['expirationDate'] = $expirationDate;
        }
        if ($resetLink) {
            $context['resetLink'] = $resetLink;
        }

        $message = (new TemplatedEmail())
            ->from($this->config->get('mailer.from'))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($htmlTemplate)
            ->context($context);

        $this->bodyRenderer->render($message);

        $this->mailer->send($message);
    }
}
