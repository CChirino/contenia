<?php

namespace App\Enums;

enum PostStatus: string
{
    case Drafted = 'drafted';
    case Approved = 'approved';
    case Rendered = 'rendered';
    case ClientApproved = 'client_approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Failed = 'failed';

    /**
     * Grafo de transiciones válidas (spec §4, fase 2 portal del cliente).
     */
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, match ($this) {
            self::Drafted => [self::Approved],
            self::Approved => [self::Rendered],
            // Rendered → client_approved (cliente aprueba), → drafted (cliente
            // rechaza), → approved (el operador edita → re-render).
            self::Rendered => [self::ClientApproved, self::Drafted, self::Approved],
            // ClientApproved → scheduled; → approved si el operador edita un
            // post ya aprobado por el cliente (vuelve a revisión tras render).
            self::ClientApproved => [self::Scheduled, self::Approved],
            self::Scheduled => [self::Published, self::Failed],
            self::Failed => [self::Scheduled],
            self::Published => [],
        }, true);
    }
}
