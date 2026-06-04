<?php

declare(strict_types=1);

namespace App\Domain\Media;

use RuntimeException;
use Throwable;

final class InlineImageMediaMutationException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $userMessage,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function uploadFailed(Throwable $throwable): self
    {
        return new self(
            'Inline images could not be fully persisted.',
            'Der Text wurde gespeichert, aber die Bilder konnten nicht vollständig übernommen werden. Bitte prüfe die Bilder und lade sie erneut hoch.',
            $throwable,
        );
    }

    public static function invalidRemoval(): self
    {
        return new self(
            'Inline image removal referenced media outside the owning model.',
            'Der Text wurde gespeichert, aber die Bildauswahl war ungültig. Es wurden keine Bilder geändert.',
        );
    }

    public static function imageLimitExceeded(): self
    {
        return new self(
            'Inline image limit exceeded after locked media reload.',
            'Der Text wurde gespeichert, aber es sind maximal vier Bilder möglich. Es wurden keine Bilder geändert.',
        );
    }

    public function userMessage(): string
    {
        return $this->userMessage;
    }
}
