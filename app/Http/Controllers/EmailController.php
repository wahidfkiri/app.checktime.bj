<?php

namespace App\Services;

use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Illuminate\Http\UploadedFile;
use Exception;

class MailtrapService
{
    // NE PAS TYPER CETTE PROPRIÉTÉ
    private $clientInstance;
    private bool $isSandbox;
    private ?int $inboxId;

    public function __construct()
    {
        $this->isSandbox = config('mailtrap.use_sandbox', true);
        $this->inboxId = config('mailtrap.inbox_id') ? (int) config('mailtrap.inbox_id') : null;
        $this->clientInstance = null; // Initialiser à null
    }

    /**
     * Récupérer ou initialiser le client
     */
    private function getClient()
    {
        if ($this->clientInstance === null) {
            $this->clientInstance = MailtrapClient::initSendingEmails(
                apiKey: config('mailtrap.api_key'),
                isSandbox: $this->isSandbox,
                inboxId: $this->inboxId
            );
        }
        return $this->clientInstance;
    }

    /**
     * Envoyer un email simple
     */
    public function send(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlContent,
        ?string $textContent = null,
        array $attachments = []
    ): array {
        try {
            $email = (new MailtrapEmail())
                ->from(new Address($fromEmail, $fromName))
                ->to(new Address($toEmail, $toName))
                ->subject($subject)
                ->html($htmlContent);

            if ($textContent) {
                $email->text($textContent);
            }

            foreach ($attachments as $attachment) {
                $this->addAttachment($email, $attachment);
            }

            $response = $this->getClient()->send($email);

            return [
                'success' => true,
                'response' => $response,
                'message' => 'Email envoyé avec succès'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Erreur lors de l\'envoi de l\'email'
            ];
        }
    }

    /**
     * Envoyer un email avec pièce jointe depuis un fichier local
     */
    public function sendWithFileAttachment(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlContent,
        string $filePath,
        ?string $fileName = null,
        ?string $mimeType = null
    ): array {
        try {
            if (!$fileName) {
                $fileName = basename($filePath);
            }

            $attachments = [
                [
                    'path' => $filePath,
                    'name' => $fileName,
                    'mime' => $mimeType
                ]
            ];

            return $this->send(
                $fromEmail,
                $fromName,
                $toEmail,
                $toName,
                $subject,
                $htmlContent,
                null,
                $attachments
            );

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Erreur lors de l\'envoi de l\'email avec pièce jointe'
            ];
        }
    }

    /**
     * Envoyer un email avec pièces jointes depuis des fichiers uploadés
     */
    public function sendWithUploadedFiles(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlContent,
        array $uploadedFiles
    ): array {
        try {
            $attachments = [];

            foreach ($uploadedFiles as $file) {
                if ($file instanceof UploadedFile) {
                    $attachments[] = [
                        'path' => $file->getRealPath(),
                        'name' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType()
                    ];
                }
            }

            return $this->send(
                $fromEmail,
                $fromName,
                $toEmail,
                $toName,
                $subject,
                $htmlContent,
                null,
                $attachments
            );

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Erreur lors de l\'envoi de l\'email avec pièces jointes'
            ];
        }
    }

    /**
     * Méthode privée pour ajouter une pièce jointe à l'email
     */
    private function addAttachment(MailtrapEmail $email, array $attachment): void
    {
        try {
            if (isset($attachment['path']) && file_exists($attachment['path'])) {
                $fileName = $attachment['name'] ?? basename($attachment['path']);
                $mimeType = $attachment['mime'] ?? mime_content_type($attachment['path']);
                
                $file = new File($attachment['path']);
                $dataPart = new DataPart(
                    $file->getBody(),
                    $fileName,
                    $mimeType ?: 'application/octet-stream'
                );
                $email->addPart($dataPart);
            }
        } catch (Exception $e) {
            \Log::error('Erreur lors de l\'ajout de la pièce jointe: ' . $e->getMessage());
        }
    }

    /**
     * Envoyer un email avec pièce jointe depuis une URL
     */
    public function sendWithUrlAttachment(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlContent,
        string $fileUrl,
        string $fileName,
        ?string $mimeType = null
    ): array {
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'mailtrap_');
            $fileContent = file_get_contents($fileUrl);
            file_put_contents($tempFile, $fileContent);

            if (!$mimeType) {
                $mimeType = mime_content_type($tempFile) ?: 'application/octet-stream';
            }

            $attachments = [
                [
                    'path' => $tempFile,
                    'name' => $fileName,
                    'mime' => $mimeType
                ]
            ];

            $result = $this->send(
                $fromEmail,
                $fromName,
                $toEmail,
                $toName,
                $subject,
                $htmlContent,
                null,
                $attachments
            );

            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Erreur lors du téléchargement ou de l\'envoi du fichier'
            ];
        }
    }

    /**
     * Vérifier si le mode Sandbox est activé
     */
    public function isSandbox(): bool
    {
        return $this->isSandbox;
    }

    /**
     * Obtenir l'ID de la boîte de réception Sandbox
     */
    public function getInboxId(): ?int
    {
        return $this->inboxId;
    }
}