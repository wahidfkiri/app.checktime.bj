<?php

namespace App\Console\Commands;

use App\Services\MailtrapService;
use Illuminate\Console\Command;

class SendTestEmail extends Command
{
    protected $signature = 'email:send-test {email} {--subject=Test Email} {--file=}';
    protected $description = 'Envoyer un email de test via Mailtrap';

    public function handle(MailtrapService $mailtrapService)
    {
        $email = $this->argument('email');
        $subject = $this->option('subject');
        $file = $this->option('file');

        $content = "<h1>Test Email</h1><p>Ceci est un email de test envoyé via Mailtrap.</p>";

        if ($file && file_exists($file)) {
            $result = $mailtrapService->sendWithFileAttachment(
                fromEmail: config('mail.from.address'),
                fromName: config('mail.from.name'),
                toEmail: $email,
                toName: $email,
                subject: $subject,
                htmlContent: $content,
                filePath: $file
            );
        } else {
            $result = $mailtrapService->send(
                fromEmail: config('mail.from.address'),
                fromName: config('mail.from.name'),
                toEmail: $email,
                toName: $email,
                subject: $subject,
                htmlContent: $content
            );
        }

        if ($result['success']) {
            $this->info('Email envoyé avec succès !');
        } else {
            $this->error('Erreur: ' . $result['error']);
        }
    }
}